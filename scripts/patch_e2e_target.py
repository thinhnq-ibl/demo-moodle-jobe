import re

with open('e2e/capture_after_state.js', 'r', encoding='utf-8') as f:
    c = f.read()

# Replace all occurrences of course=2&question=76:794 with course=3&question=2:119
c = c.replace('course=2&question=76:794', 'course=3&question=2:119')
c = c.replace('course=2', 'course=3')

# Code and input adjustments for is_prime
old_code = "await studentPage.fill('#student_code', 'def find_max(numbers):\\n    return max(numbers)');"
new_code = "await studentPage.fill('#student_code', 'def is_prime(n):\\n    if n <= 1:\\n        return False\\n    for i in range(2, int(n**0.5) + 1):\\n        if n % i == 0:\\n            return False\\n    return True');"
c = c.replace(old_code, new_code)

old_input = "await studentPage.fill('#test_input', `print(find_max([10, ${uniqueVal}, 55, 3]))`);"
new_input = "await studentPage.fill('#test_input', `print(is_prime(${uniqueVal}))`);"
c = c.replace(old_input, new_input)

old_pred = "await studentPage.fill('#predicted_output', `${uniqueVal}`);"
new_pred = "await studentPage.fill('#predicted_output', 'False');"
c = c.replace(old_pred, new_pred)

with open('e2e/capture_after_state.js', 'w', encoding='utf-8') as f:
    f.write(c)

print('Updated e2e test target to Course 3, Quiz 2, Question 119 successfully!')
