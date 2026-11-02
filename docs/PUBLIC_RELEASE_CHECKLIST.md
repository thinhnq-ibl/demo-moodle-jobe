# Public release checklist — `local_testcase_exchange` (v2.3.1-beta)

## Completed in the repository

- [x] Plugin component, dependency, minimum Moodle version, maturity and release are declared.
- [x] English README, changelog, security policy and privacy notice are included.
- [x] Jobe credentials are configurable and no real secret is stored in plugin source.
- [x] Granular capabilities, login checks, context checks and sesskey checks are present.
- [x] Jobe ephemeral execution declared through Moodle Privacy API.
- [x] Privacy discovery, export and erasure cover private runs, contributions, reviews and rewards.
- [x] Native schema migration is versioned and idempotent (`db/upgrade.php` v2026110201).
- [x] Compound policy key `uq_quiz_question` on `mdl_local_tce_policies` ensures isolation between Quizzes.
- [x] Jobe and MariaDB use separate Docker networks.
- [x] A deterministic ZIP packaging script includes only the plugin directory.
- [x] Unit and audit coverage exists for normalization, fingerprint stability, capability walls and deduplication.
- [x] Passed 4-Part Release Readiness Audit:
  - Test A: Isolation & Migration (Quiz-scoped policy & compound unique index).
  - Test B: Permission & Privacy (Capability walls, Zero oracle leakage, Privacy API).
  - Test C: CodeRunner Compatibility (`python3` vs `python3_w_input` execution parity).
  - Test D: Concurrent Deduplication & Race Condition Defense (Canonical deduplication & claim idempotency).

## Required before uploading to Moodle Marketplace

- [x] Security contact configured as `nqt900@gmail.com`.
- [x] Public source repository configured as `https://github.com/thinhnq-ibl/demo-moodle-jobe`.
- [x] Public issue tracker configured as `https://github.com/thinhnq-ibl/demo-moodle-jobe/issues`.
- [x] Reconcile the repository-level MIT license with the plugin's GPL v3.0+ declaration (scoped by directory in root `LICENSE`).
- [ ] Confirm maintainer name/copyright information in every PHP file.
- [ ] Run Moodle Plugin CI (`moodle-plugin-ci`) with PHP versions supported by each advertised Moodle release.
- [ ] Separate git repositories for publishing: `moodle-local_testcase_exchange` and `moodle-quizaccess_testcaseexchange`.
- [ ] Provide optional Jobe Dockerfile variant with pre-installed scientific packages (`numpy`, `pandas`, `scipy`).
- [ ] Capture Marketplace screenshots from the final pilot UI.
- [x] Draft concise/full English Marketplace descriptions and supported-version matrix in `docs/MARKETPLACE_LISTING.md`.
- [x] Release owner decided to keep the Vietnamese language pack in the ZIP.

## Latest automated audit — 2026-11-02

- Release version: `2.3.1-beta` (build `2026110201`).
- Moodle upgrade: pass via CLI upgrade to `2026110201`.
- Schema: `mdl_local_tce_policies` with compound unique index `uq_quiz_question (quiz_id, question_id)`.
- 4-Part Release Audit:
  - Test A: PASSED.
  - Test B: PASSED.
  - Test C: PASSED.
  - Test D: PASSED.
