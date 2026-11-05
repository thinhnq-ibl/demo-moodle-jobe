with open('local_testcase_exchange/styles.css', 'r', encoding='utf-8') as f:
    c = f.read()

old_badge = """.local-testcase-exchange-root .badge {
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}"""

new_badge = """.local-testcase-exchange-root .badge {
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: normal !important;
    word-break: break-word !important;
    max-width: 100% !important;
    text-align: left !important;
}"""

c = c.replace(old_badge, new_badge)

old_table = """.tce-history-table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}"""

new_table = """.tce-history-table-container {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
    max-width: 100% !important;
    width: 100% !important;
    display: block;
}"""

c = c.replace(old_table, new_table)

with open('local_testcase_exchange/styles.css', 'w', encoding='utf-8') as f:
    f.write(c)

print('Updated styles.css with badge wrap and table container scroll!')
