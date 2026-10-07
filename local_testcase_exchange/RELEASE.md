# Release readiness

This beta is suitable for a controlled pilot, but is not ready for Marketplace upload. Moodle CodeSniffer currently reports unresolved coding-style/PHPDoc issues, concentrated in the mixed PHP/HTML pages. Before publishing a stable Marketplace release, the maintainer must make Plugin CI clean, provide a private vulnerability contact and public issue tracker, run privacy tests on a clean installation, test all advertised Moodle/PHP versions, and complete student/teacher E2E, concurrency, Jobe outage, backup and restore exercises.

Build the installable archive from the repository root with `./scripts/package-plugin.sh`. Install and upgrade from that exact archive on a disposable Moodle site before attaching it to a release.
