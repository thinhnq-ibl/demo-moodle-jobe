with open('local_testcase_exchange/db/install.xml', 'r', encoding='utf-8') as f:
    content = f.read()

target = '        <FIELD NAME="input_fingerprint" TYPE="char" LENGTH="64" NOTNULL="true" SEQUENCE="false"/>\n        <FIELD NAME="oracle_output" TYPE="text" NOTNULL="true" SEQUENCE="false"/>'
replacement = '        <FIELD NAME="input_fingerprint" TYPE="char" LENGTH="64" NOTNULL="true" SEQUENCE="false"/>\n        <FIELD NAME="expected_output" TYPE="text" NOTNULL="false" SEQUENCE="false"/>\n        <FIELD NAME="oracle_output" TYPE="text" NOTNULL="true" SEQUENCE="false"/>'

if target not in content:
    target = target.replace('\n', '\r\n')
    replacement = replacement.replace('\n', '\r\n')

if target in content:
    content = content.replace(target, replacement, 1)
    with open('local_testcase_exchange/db/install.xml', 'w', encoding='utf-8') as f:
        f.write(content)
    print('SUCCESS')
else:
    print('TARGET NOT FOUND')
