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

- [ ] Replace the placeholder vulnerability-reporting text in `SECURITY.md` with a permanent private contact.
- [ ] Publish the source repository and public issue tracker; add both URLs to the Marketplace listing.
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
- [ ] Create screenshots, concise/full English Marketplace descriptions and supported-version matrix.
- [ ] Obtain consent for shipping the Vietnamese language pack or move it to AMOS after approval.

## Latest automated audit — 2026-10-07

- PHP syntax: pass for all plugin PHP files.
- Moodle upgrade: pass at version `2026100704`.
- Privacy provider: autoloaded with metadata, plugin and user-list interfaces; two external locations reported.
- Privacy discovery smoke test: existing users resolve to their course context; course user list returned users `3,4`.
- Normalizer smoke test: 3/3 pass.
- Docker Compose validation and shell syntax: pass.
- ZIP packaging: pass; `local_testcase_exchange-2.1.0-beta1.zip` contains only the plugin tree.
- Moodle CodeSniffer: **not yet passing** — 641 errors and 272 warnings. The largest sources are mixed PHP/HTML pages (`index.php`, `policy.php`, `review.php`) and missing method PHPDoc. Refactor these pages to Moodle forms/renderers/Mustache, then require a clean CI run before Marketplace upload.
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
