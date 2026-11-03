# Privacy information

The plugin processes personal data directly within dedicated Moodle database tables (`{local_tce_*}`) and sends execution payloads to configured Jobe sandbox nodes for sandboxed differential testing.

Stored data may include:
- Moodle user IDs (student runners, contributors, reviewers, reward recipients)
- Testcase inputs and predicted outputs
- Program execution outputs (student and reference oracle outputs)
- Purpose/reflection text and teacher review notes
- Assigned/claimed reward records
- Incremental code revisions/snapshots and timestamps

Program source, the CodeRunner reference answer, and testcase inputs are sent to the configured Jobe server for sandboxed execution. They are not permanently stored on the Jobe execution nodes by this plugin.

The Moodle Privacy API provider (`\local_testcase_exchange\privacy\provider`) fully implements the GDPR Privacy subsystem, supporting data metadata discovery, export (`export_user_data`), and deletion (`delete_data_for_user`, `delete_data_for_all_users_in_context`) by course context.
