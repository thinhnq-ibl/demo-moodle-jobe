# Release readiness

This beta is suitable for a controlled pilot, but still requires full integration evidence before Marketplace upload. Moodle CodeSniffer passes with no errors or warnings, and the Mustache templates render on Moodle 4.4. Security reports go to `nqt900@gmail.com`; source and issues use `thinhnq-ibl/demo-moodle-jobe`; Vietnamese is intentionally packaged. Before publishing a stable Marketplace release, the maintainer must reconcile repository/plugin licensing and legacy public documentation, complete the remote Plugin CI matrix, run privacy erasure tests on a disposable installation, test all advertised Moodle/PHP versions, and complete student/teacher E2E, concurrency, Jobe outage, backup and restore exercises.

Build the installable archive from the repository root with `./scripts/package-plugin.sh`. Install and upgrade from that exact archive on a disposable Moodle site before attaching it to a release.
