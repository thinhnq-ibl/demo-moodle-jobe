# Privacy information

The plugin processes personal data in a separately configured MariaDB database (`testcase_store`) and sends execution payloads to configured Jobe sandbox nodes.

Stored data may include Moodle user IDs, testcase inputs, predicted and actual outputs, purpose/reflection text, review history, rewards, timestamps, and a SHA-256 hash of submitted source. The plugin does not intentionally persist complete source code in `testcase_store`.

Program source, the CodeRunner reference answer and testcase input are sent to Jobe for sandbox execution. Retention and logging on independently operated Jobe nodes are controlled by those operators.

The Moodle Privacy API provider supports discovery, export and deletion by course context. Deleting the plugin from Moodle does not automatically drop the external database; administrators must retain or erase it according to their institutional retention policy.
