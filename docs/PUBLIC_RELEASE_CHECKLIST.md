# Public release checklist — `local_testcase_exchange`

## Completed in the repository

- [x] Plugin component, dependency, minimum Moodle version, maturity and release are declared.
- [x] English README, changelog, security policy and privacy notice are included.
- [x] DB/Jobe credentials are configurable and no real secret is stored in plugin source.
- [x] Granular capabilities, login checks, context checks and sesskey checks are present.
- [x] External DB/Jobe data processing is declared through Moodle Privacy API.
- [x] Privacy discovery, export and erasure cover private runs, contributions, reviews and rewards.
- [x] External schema migration is versioned and idempotent.
- [x] Jobe and MariaDB use separate Docker networks.
- [x] A deterministic ZIP packaging script includes only the plugin directory.
- [x] Unit coverage exists for normalization and fingerprint stability.

## Required before uploading to Moodle Marketplace

- [x] Security contact configured as `nqt900@gmail.com`.
- [x] Public source repository configured as `https://github.com/thinhnq-ibl/demo-moodle-jobe`.
- [x] Public issue tracker configured as `https://github.com/thinhnq-ibl/demo-moodle-jobe/issues`.
- [ ] Confirm maintainer name/copyright information in every PHP file.
- [ ] Run Moodle Plugin CI/CodeChecker with PHP versions supported by each advertised Moodle release.
- [ ] Run PHPUnit and Privacy API tests on a clean Moodle installation.
- [ ] Install from the generated ZIP, upgrade from the last release, and uninstall on a disposable site.
- [ ] Test with developer debugging at `DEVELOPER` and confirm the PHP/server logs are clean.
- [ ] Run student, non-editing teacher, editing teacher and administrator capability tests.
- [ ] Test Java, Python and C CodeRunner questions, Jobe failover and complete Jobe outage.
- [ ] Run concurrent duplicate/reward tests and verify transaction rollback paths.
- [ ] Export and erase a test student's data through Moodle's privacy UI; inspect `testcase_store` afterwards.
- [ ] Restore the pre-migration backup into a disposable database and rehearse rollback.
- [ ] Decide and document production retention periods for private runs, reviews and Jobe logs.
- [ ] Use production secrets and TLS endpoints; do not publish the development `.env` values.
- [x] Draft concise/full English Marketplace descriptions and supported-version matrix in `docs/MARKETPLACE_LISTING.md`.
- [ ] Capture Marketplace screenshots from the final pilot UI.
- [x] Release owner decided to keep the Vietnamese language pack in the ZIP.
- [ ] Reconcile the repository-level MIT license with the plugin's GPL v3 declaration, or clearly scope the licenses by directory.
- [ ] Remove or rewrite public root documentation that exposes demo credentials and describes the retired DB-writing Jobe architecture.
- [ ] Decide whether to retain the full-stack repository name or create the Marketplace-recommended `moodle-local_testcase_exchange` repository before the first stable release.

## Latest automated audit — 2026-10-07

- PHP syntax: pass for all plugin PHP files.
- Moodle upgrade: pass at version `2026100704`.
- Privacy provider: autoloaded with metadata, plugin and user-list interfaces; two external locations reported.
- Privacy discovery smoke test: existing users resolve to their course context; course user list returned users `3,4`.
- Normalizer smoke test: 3/3 pass.
- Docker Compose validation and shell syntax: pass.
- ZIP packaging: pass; `local_testcase_exchange-2.1.0-beta1.zip` contains only the plugin tree.
- Moodle CodeSniffer: pass with **0 errors and 0 warnings** after moving the dashboard, policy and review markup to Mustache and documenting service methods.
- Mustache smoke render: pass for `dashboard`, `policy` and `review` templates in Moodle 4.4.
- PHPUnit runner: not installed in the current Docker image; the added test must be executed by Moodle Plugin CI.

## Commands

```bash
docker compose config --quiet
bash -n entrypoint.sh scripts/package-plugin.sh
docker exec moodle_app php /var/www/html/admin/cli/upgrade.php --non-interactive
docker exec moodle_app php /var/www/html/admin/cli/purge_caches.php
docker exec moodle_app vendor/bin/phpunit local/testcase_exchange/tests
./scripts/package-plugin.sh
```

Do not mark the release stable until every unchecked item above has an owner and evidence attached to the release record.
