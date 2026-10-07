# Security policy

## Supported versions

Security fixes are applied to the latest published beta or stable release.

## Reporting a vulnerability

Do not disclose suspected vulnerabilities in a public issue. Contact the maintainer through the private security-reporting channel of the public source repository before Marketplace publication. The repository owner must configure that channel and replace this paragraph with its permanent URL or email before release.

Include the affected version, reproduction steps, impact and any suggested remediation. Avoid attaching production credentials, student source code or personal data.

## Deployment expectations

- Serve Moodle and Jobe over trusted networks; expose Jobe publicly only behind authentication and rate limiting.
- Use a dedicated least-privilege MariaDB account for `testcase_store`.
- Keep Jobe off the database network.
- Store secrets in the deployment secret manager or `.env`; never commit them.
- Restrict the health page to site administrators and review capabilities to trusted staff.
