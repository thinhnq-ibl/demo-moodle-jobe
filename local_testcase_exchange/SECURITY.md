# Security policy

## Supported versions

Security fixes are applied to the latest published beta or stable release.

## Reporting a vulnerability

Do not disclose suspected vulnerabilities in a public issue. Email the maintainer privately at [nqt900@gmail.com](mailto:nqt900@gmail.com). Use a subject beginning with `[SECURITY] local_testcase_exchange`.

Include the affected version, reproduction steps, impact and any suggested remediation. Avoid attaching production credentials, student source code or personal data.

General bugs and feature requests belong in the [public issue tracker](https://github.com/thinhnq-ibl/demo-moodle-jobe/issues), not in the security mailbox.

## Deployment expectations

- Serve Moodle and Jobe over trusted networks; expose Jobe publicly only behind authentication and rate limiting.
- Use a dedicated least-privilege MariaDB account for `testcase_store`.
- Keep Jobe off the database network.
- Store secrets in the deployment secret manager or `.env`; never commit them.
- Restrict the health page to site administrators and review capabilities to trusted staff.
