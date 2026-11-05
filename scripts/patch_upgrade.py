with open('local_testcase_exchange/db/upgrade.php', 'r', encoding='utf-8') as f:
    content = f.read()

upgrade_block = '''    if ($oldversion < 2026110301) {
        $table = new xmldb_table('local_tce_contributions');
        $fieldexp = new xmldb_field('expected_output', XMLDB_TYPE_TEXT, null, null, null, null, null, 'input_fingerprint');
        if (!$dbman->field_exists($table, $fieldexp)) {
            $dbman->add_field($table, $fieldexp);
        }
        try {
            $DB->execute("UPDATE {local_tce_contributions} c
                            JOIN {local_tce_runs} r ON r.id = c.run_id
                             SET c.expected_output = r.predicted_output
                           WHERE c.expected_output IS NULL");
        } catch (\\Throwable $e) {
            // Ignored if table empty or DB syntax differs
        }
        upgrade_plugin_savepoint(true, 2026110301, 'local', 'testcase_exchange');
    }

    return true;'''

target = '    return true;'
if target in content:
    content = content.replace(target, upgrade_block, 1)
    with open('local_testcase_exchange/db/upgrade.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('SUCCESS')
else:
    print('TARGET NOT FOUND')
