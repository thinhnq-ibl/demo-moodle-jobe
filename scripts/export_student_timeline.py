#!/usr/bin/env python3
"""
Student Learning & Differential Testing Timeline Exporter.
Exports chronological student activity records combining:
1. Differential testing playground runs (with code revision step)
2. Quiz attempts & submissions for grade
3. Contributed testcases
4. Accepted/reviewed testcases
5. Ratings and teacher comments
6. Claimed/received reward testcases

Usage:
    python3 scripts/export_student_timeline.py --username student1 --course 6 --quiz 41 --format csv -o timeline.csv
    python3 scripts/export_student_timeline.py --course 6 --format json -o timeline_course6.json
"""

import argparse
import csv
import json
import sys
import pymysql


def build_timeline_query(course_id=None, quiz_id=None, user_id=None, username=None):
    where_clauses = []
    params = []

    if course_id:
        where_clauses.append("tl.course_id = %s")
        params.append(course_id)
    if quiz_id:
        where_clauses.append("tl.quiz_id = %s")
        params.append(quiz_id)
    if user_id:
        where_clauses.append("tl.user_id = %s")
        params.append(user_id)
    if username:
        where_clauses.append("u.username = %s")
        params.append(username)

    where_sql = ("WHERE " + " AND ".join(where_clauses)) if where_clauses else ""

    query = f"""
    SELECT 
        tl.event_time,
        tl.user_id,
        u.username,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        tl.course_id,
        tl.quiz_id,
        tl.question_id,
        tl.event_type,
        tl.event_name,
        tl.code_version,
        tl.status,
        tl.rating,
        tl.comment,
        tl.details,
        tl.code_snapshot,
        tl.test_input,
        tl.test_output
    FROM (
        -- 1. Playground test runs (Contribute Zone)
        SELECT 
            tr.created_at AS event_time,
            tr.user_id,
            tr.course_id,
            tr.quiz_id,
            tr.question_id,
            'PLAYGROUND_TEST' AS event_type,
            'Chạy thử nghiệm (Contribute Zone)' AS event_name,
            COALESCE(scr.step_number, 1) AS code_version,
            tr.student_outcome AS status,
            NULL AS rating,
            NULL AS comment,
            CONCAT('Mục đích: ', COALESCE(tr.purpose, 'N/A'), ' | Phân loại: ', COALESCE(tr.category, 'N/A'), 
                   ' | Kết quả SV: ', tr.student_outcome, ' | Kết quả Oracle: ', tr.oracle_outcome) AS details,
            tr.student_code_snapshot AS code_snapshot,
            tr.input_raw AS test_input,
            tr.student_run_output AS test_output
        FROM testcase_store.testcase_runs tr
        LEFT JOIN testcase_store.student_code_revisions scr ON tr.code_revision_id = scr.id

        UNION ALL

        -- 2. Testcase contributions
        SELECT 
            tc.submitted_at AS event_time,
            tc.user_id,
            tc.course_id,
            tc.quiz_id,
            tc.question_id,
            'TESTCASE_CONTRIBUTE' AS event_type,
            'Đề xuất đóng góp Testcase' AS event_name,
            NULL AS code_version,
            tc.status AS status,
            NULL AS rating,
            NULL AS comment,
            CONCAT('Testcase #', tc.id, ' | Mục đích: ', COALESCE(tc.purpose, 'N/A'), ' | Phân loại: ', COALESCE(tc.category, 'N/A')) AS details,
            NULL AS code_snapshot,
            tc.input_normalized AS test_input,
            tc.oracle_output AS test_output
        FROM testcase_store.testcase_contributions tc

        UNION ALL

        -- 3. Testcase reviews, accept/reject, rating & teacher comment
        SELECT 
            cr.created_at AS event_time,
            tc.user_id,
            tc.course_id,
            tc.quiz_id,
            tc.question_id,
            'TESTCASE_REVIEW' AS event_type,
            CASE 
                WHEN cr.to_status = 'approved' AND cr.rating IS NOT NULL THEN 'Testcase được Duyệt & Chấm điểm'
                WHEN cr.to_status = 'approved' THEN 'Testcase được Duyệt (Accepted)'
                WHEN cr.to_status = 'rejected' THEN 'Testcase bị Từ chối (Rejected)'
                ELSE 'Thẩm định & Đánh giá Testcase'
            END AS event_name,
            NULL AS code_version,
            cr.to_status AS status,
            cr.rating AS rating,
            cr.review_note AS comment,
            CONCAT('Testcase #', tc.id, ' [', cr.from_status, ' -> ', cr.to_status, ']', 
                   IF(cr.rating IS NOT NULL, CONCAT(' | Đánh giá: ', cr.rating, ' sao (', COALESCE(cr.rating_reason, ''), ')'), ''),
                   IF(cr.review_note IS NOT NULL AND cr.review_note != '', CONCAT(' | Nhận xét GV: ', cr.review_note), '')) AS details,
            NULL AS code_snapshot,
            tc.input_normalized AS test_input,
            tc.oracle_output AS test_output
        FROM testcase_store.contribution_reviews cr
        JOIN testcase_store.testcase_contributions tc ON cr.contribution_id = tc.id

        UNION ALL

        -- 4. Testcase rewards
        SELECT 
            COALESCE(trw.claimed_at, trw.created_at) AS event_time,
            trw.receiver_user_id AS user_id,
            trw.course_id,
            trw.quiz_id,
            trw.question_id,
            'TESTCASE_REWARD' AS event_type,
            'Nhận testcase thưởng (Kho chung)' AS event_name,
            NULL AS code_version,
            IF(trw.is_claimed = 1, 'claimed', 'unclaimed') AS status,
            NULL AS rating,
            NULL AS comment,
            CONCAT('Nhận Testcase #', trw.contribution_id, ' từ nguồn: ', trw.reward_source, 
                   IF(trw.is_claimed = 1, ' (Đã mở khóa)', ' (Chưa mở khóa)')) AS details,
            NULL AS code_snapshot,
            NULL AS test_input,
            NULL AS test_output
        FROM testcase_store.testcase_rewards trw

        UNION ALL

        -- 5. Quiz submissions
        SELECT 
            FROM_UNIXTIME(qa.timefinish) AS event_time,
            qa.userid AS user_id,
            q.course AS course_id,
            qa.quiz AS quiz_id,
            0 AS question_id,
            'QUIZ_SUBMIT' AS event_type,
            'Nộp bài thi Quiz lấy điểm' AS event_name,
            NULL AS code_version,
            qa.state AS status,
            NULL AS rating,
            NULL AS comment,
            CONCAT('Lần nộp thi #', qa.attempt, ' | Điểm đạt: ', COALESCE(qa.sumgrades, 0), ' / ', q.sumgrades) AS details,
            NULL AS code_snapshot,
            NULL AS test_input,
            NULL AS test_output
        FROM moodle.mdl_quiz_attempts qa
        JOIN moodle.mdl_quiz q ON qa.quiz = q.id
        WHERE qa.timefinish > 0
    ) AS tl
    JOIN moodle.mdl_user u ON tl.user_id = u.id
    {where_sql}
    ORDER BY tl.event_time ASC;
    """
    return query, params


def main():
    parser = argparse.ArgumentParser(description="Export student learning and testing timeline.")
    parser.add_argument("--host", default="127.0.0.1", help="Database host (default: 127.0.0.1)")
    parser.add_argument("--port", type=int, default=3306, help="Database port (default: 3306)")
    parser.add_argument("--user", default="root", help="Database user (default: root)")
    parser.add_argument("--password", default="rootpassword", help="Database password (default: rootpassword)")
    parser.add_argument("--course", type=int, help="Course ID filter")
    parser.add_argument("--quiz", type=int, help="Quiz ID filter")
    parser.add_argument("--userid", type=int, help="User ID filter")
    parser.add_argument("--username", help="Username filter (e.g. student1)")
    parser.add_argument("--format", choices=["csv", "json"], default="csv", help="Export format (default: csv)")
    parser.add_argument("--include-code", action="store_true", help="Include student code snapshot in CSV")
    parser.add_argument("-o", "--output", help="Output file path (default: print to stdout)")

    args = parser.parse_args()

    try:
        conn = pymysql.connect(
            host=args.host,
            port=args.port,
            user=args.user,
            password=args.password,
            database="moodle",
            cursorclass=pymysql.cursors.DictCursor,
            charset="utf8mb4"
        )
    except Exception as e:
        sys.stderr.write(f"Database connection error: {e}\n")
        sys.exit(1)

    query, params = build_timeline_query(
        course_id=args.course,
        quiz_id=args.quiz,
        user_id=args.userid,
        username=args.username
    )

    with conn.cursor() as cursor:
        cursor.execute(query, params)
        rows = cursor.fetchall()
    conn.close()

    # Convert datetime objects to string
    for r in rows:
        if r.get("event_time"):
            r["event_time"] = str(r["event_time"])

    out_file = open(args.output, "w", encoding="utf-8", newline="") if args.output else sys.stdout

    try:
        if args.format == "json":
            json.dump(rows, out_file, ensure_ascii=False, indent=2)
            out_file.write("\n")
        else:
            fields = [
                "event_time", "username", "fullname", "course_id", "quiz_id", "question_id",
                "event_type", "event_name", "code_version", "status", "rating", "comment", "details"
            ]
            if args.include_code:
                fields.extend(["code_snapshot", "test_input", "test_output"])

            writer = csv.DictWriter(out_file, fieldnames=fields, extrasaction="ignore")
            writer.writeheader()
            for r in rows:
                writer.writerow(r)
    finally:
        if args.output:
            out_file.close()
            print(f"✅ Exported {len(rows)} timeline events to: {args.output}")


if __name__ == "__main__":
    main()
