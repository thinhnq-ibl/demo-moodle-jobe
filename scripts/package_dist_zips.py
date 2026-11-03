import os
import zipfile
import hashlib

os.makedirs('dist', exist_ok=True)

def zip_dir(src_dir, zip_path, root_name):
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(src_dir):
            # Ignore git, cache, temp files
            dirs[:] = [d for d in dirs if not d.startswith('.') and d != '__pycache__']
            for file in files:
                if file.startswith('.') or file.endswith('.pyc'):
                    continue
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, src_dir)
                archive_name = os.path.join(root_name, rel_path)
                zf.write(full_path, archive_name)
    print(f"Created {zip_path} ({os.path.getsize(zip_path)} bytes)")

# 1. Package local_testcase_exchange
zip_dir('local_testcase_exchange', 'dist/local_testcase_exchange.zip', 'local_testcase_exchange')
zip_dir('local_testcase_exchange', 'dist/moodle-local_testcase_exchange-2.3.1-beta.zip', 'testcase_exchange')

# 2. Package quizaccess_testcaseexchange
zip_dir('quizaccess_testcaseexchange', 'dist/quizaccess_testcaseexchange.zip', 'quizaccess_testcaseexchange')
zip_dir('quizaccess_testcaseexchange', 'dist/moodle-quizaccess_testcaseexchange-2.3.1-beta.zip', 'testcaseexchange')

# 3. Calculate SHA256 checksums
checksums = []
for fname in sorted(os.listdir('dist')):
    if fname.endswith('.zip'):
        p = os.path.join('dist', fname)
        with open(p, 'rb') as f:
            h = hashlib.sha256(f.read()).hexdigest()
        checksums.append(f"{h}  {fname}\n")

with open('dist/SHA256SUMS', 'w', encoding='utf-8') as f:
    f.writelines(checksums)

print("Updated dist/SHA256SUMS successfully.")
