#!/usr/bin/env python3
"""
Convert existing Moodle CodeRunner XML question bank to the 3-Suite architecture:
- Suite 1: Sample (Test 1): useasexample="1", display="SHOW", extra="sample"
- Suite 2: Reward Bank Seeds (Tests 2, 3): useasexample="0", display="SHOW", extra="reward_bank"
- Suite 3: Hidden Grading Suite (Tests 4+): useasexample="0", display="HIDE", extra="grading"
"""

import sys
import xml.etree.ElementTree as ET

def convert_quiz_xml(input_path, output_path):
    print(f"Reading: {input_path}")
    tree = ET.parse(input_path)
    root = tree.getroot()

    questions = root.findall('.//question[@type="coderunner"]')
    print(f"Found {len(questions)} CodeRunner questions.")

    total_converted_tests = 0
    suite1_count = 0
    suite2_count = 0
    suite3_count = 0

    for q in questions:
        qname = q.find('name/text')
        name_str = qname.text if qname is not None else 'Unknown'

        testcases = q.findall('.//testcases/testcase')
        num_tests = len(testcases)

        for idx, tc in enumerate(testcases, start=1):
            total_converted_tests += 1

            # Find or create display tag
            disp = tc.find('display')
            if disp is None:
                disp = ET.SubElement(tc, 'display')
            disp_text = disp.find('text')
            if disp_text is None:
                disp_text = ET.SubElement(disp, 'text')

            # Find or create extra tag
            extra = tc.find('extra')
            if extra is None:
                extra = ET.SubElement(tc, 'extra')
            extra_text = extra.find('text')
            if extra_text is None:
                extra_text = ET.SubElement(extra, 'text')

            if idx == 1:
                # Suite 1: Sample
                tc.set('useasexample', '1')
                disp_text.text = 'SHOW'
                extra_text.text = 'sample'
                suite1_count += 1
            elif idx in (2, 3) and num_tests >= 3:
                # Suite 2: Reward Bank
                tc.set('useasexample', '0')
                disp_text.text = 'SHOW'
                extra_text.text = 'reward_bank'
                suite2_count += 1
            else:
                # Suite 3: Hidden Grading
                tc.set('useasexample', '0')
                disp_text.text = 'HIDE'
                extra_text.text = 'grading'
                suite3_count += 1

    tree.write(output_path, encoding='utf-8', xml_declaration=True)
    print(f"\nSuccessfully wrote converted XML to: {output_path}")
    print(f"Summary:")
    print(f" - Total CodeRunner questions: {len(questions)}")
    print(f" - Total Testcases processed: {total_converted_tests}")
    print(f" - Suite 1 (Sample / useasexample=1): {suite1_count}")
    print(f" - Suite 2 (Reward Bank / extra=reward_bank): {suite2_count}")
    print(f" - Suite 3 (Grading / display=HIDE): {suite3_count}")

if __name__ == '__main__':
    src = sys.argv[1] if len(sys.argv) > 1 else '/Users/nguyenquocthinh/Downloads/các_câu_hỏi_420300384803_HK1_2025_2026_Mặc_định_cho_Quản_lý_chung.xml'
    dst = sys.argv[2] if len(sys.argv) > 2 else '/Users/nguyenquocthinh/Documents/demo-moodle-jobe/ngan_hang_de_3_suites_chuan.xml'
    convert_quiz_xml(src, dst)
