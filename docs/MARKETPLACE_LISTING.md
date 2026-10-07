# Moodle Marketplace listing draft

## Identity

- Component: `local_testcase_exchange`
- Display name: CodeRunner Testcase Exchange
- Release: `2.1.0-beta1`
- Maintainer: Nguyen Quoc Thinh
- Contact: `nqt900@gmail.com`
- Source: <https://github.com/thinhnq-ibl/demo-moodle-jobe>
- Bug tracker: <https://github.com/thinhnq-ibl/demo-moodle-jobe/issues>
- License: GNU GPL v3 or later for the plugin directory

## Short description

Let students privately explore CodeRunner testcases, contribute distinct cases for teacher review, and unlock approved peer testcases without changing the official grading tests.

## Full description

CodeRunner Testcase Exchange adds a course-level workflow for deliberate testcase design. Students select an existing CodeRunner question in a Quiz, enter their current program, testcase input, predicted output, purpose and reflection, then run both their program and the teacher reference solution through the configured CodeRunner/Jobe sandbox.

Private runs remain visible only to their owner and authorised staff. A student may explicitly propose a successful run for sharing. The plugin normalises input, detects exact duplicates within the course/Quiz/question scope, records immutable review history, and supports teacher approval or requests for further explanation. An optional one-for-one policy unlocks an approved testcase from another student without modifying CodeRunner's official grading suite.

Quiz and question policies control availability, review mode, oracle visibility, reward mode, rate limits, input size, input mapping and normalisation. Administrators can configure the external MariaDB repository and inspect DB/Jobe health without exposing credentials.

## Requirements

- Moodle 4.3 or 4.4.
- PHP 8.2 for the currently tested Docker deployment.
- CodeRunner question type and its adaptive behaviour dependency.
- One or more Jobe sandbox nodes configured in CodeRunner.
- A separate MariaDB/MySQL database and dedicated account for testcase data.

The external testcase repository is currently MariaDB/MySQL-specific. PostgreSQL is not supported for that separate repository; Moodle's own database remains accessed only through Moodle APIs.

## Privacy summary

The plugin stores Moodle user IDs, testcase inputs and outputs, contribution/review/reward data, timestamps and source hashes in the configured external repository. Program source, reference source and testcase input are sent to configured Jobe nodes for execution. Moodle Privacy API discovery, export and deletion are implemented. See `PRIVACY.md` in the plugin package.

## Beta limitations

- Intended for controlled pilots until the full CI, role-based E2E, privacy erasure, concurrency and outage matrices are complete.
- Approved contributions never enter the official CodeRunner grading tests automatically.
- The plugin does not currently import source directly from a latest Quiz attempt; students paste their current source into the private exploration form.
