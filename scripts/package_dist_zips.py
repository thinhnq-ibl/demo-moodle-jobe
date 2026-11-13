import os
import re
import zipfile
import hashlib

os.makedirs('dist', exist_ok=True)


def get_release(version_file):
    with open(version_file, 'r', encoding='utf-8') as f:
        m = re.search(r"\$plugin->release\s*=\s*'([^']+)'", f.read())
    if not m:
        raise RuntimeError(f"Could not find $plugin->release in {version_file}")
    return m.group(1)


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
                archive_name = os.path.join(root_name, rel_path).replace('\\', '/')
                zf.write(full_path, archive_name)
    print(f"Created {zip_path} ({os.path.getsize(zip_path)} bytes)")


local_release = get_release('local_testcase_exchange/version.php')
quiz_release = get_release('quizaccess_testcaseexchange/version.php')

# Remove stale versioned release zips
for fname in os.listdir('dist'):
    if fname.startswith('moodle-') and fname.endswith('.zip'):
        os.remove(os.path.join('dist', fname))

# 1. Package local_testcase_exchange
zip_dir('local_testcase_exchange', 'dist/local_testcase_exchange.zip', 'local_testcase_exchange')
zip_dir('local_testcase_exchange', f'dist/moodle-local_testcase_exchange-{local_release}.zip', 'testcase_exchange')

# 2. Package quizaccess_testcaseexchange
zip_dir('quizaccess_testcaseexchange', 'dist/quizaccess_testcaseexchange.zip', 'quizaccess_testcaseexchange')
zip_dir('quizaccess_testcaseexchange', f'dist/moodle-quizaccess_testcaseexchange-{quiz_release}.zip', 'testcaseexchange')

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
