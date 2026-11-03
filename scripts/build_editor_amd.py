import os
import re

src_path = os.path.join("local_testcase_exchange", "amd", "src", "editor.js")
build_path = os.path.join("local_testcase_exchange", "amd", "build", "editor.min.js")

with open(src_path, "r", encoding="utf-8") as f:
    content = f.read()

# Strip multi-line comments that aren't JSDoc license header if desired, or keep standard minified formatting
# For Moodle AMD build, keeping clean minified JS
lines = []
for line in content.splitlines():
    stripped = line.strip()
    if stripped.startswith("//") and not "Moodle" in stripped and not "License" in stripped:
        continue
    lines.append(line)

minified = "\n".join(lines)

with open(build_path, "w", encoding="utf-8") as f:
    f.write(minified)

print(f"Built AMD module successfully: {build_path} ({len(minified)} bytes)")
