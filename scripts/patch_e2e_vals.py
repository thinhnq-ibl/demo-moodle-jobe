with open('e2e/capture_after_state.js', 'r', encoding='utf-8') as f:
    c = f.read()

c = c.replace(
    'const uniqueVal = Math.floor(Math.random() * 8000) + 2000;',
    'const uniqueVal = (Math.floor(Math.random() * 4000) + 1000) * 2;'
)

c = c.replace(
    "question=76:794&tab=review",
    "question=2:119&tab=review"
)

with open('e2e/capture_after_state.js', 'w', encoding='utf-8') as f:
    f.write(c)

print('Updated capture_after_state.js values cleanly!')
