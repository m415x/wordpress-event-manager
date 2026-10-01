# Testing (Foundation)

This document describes the initial *standalone PHP unit-test harness*. It does not assert that the historical plugin boots or passes WordPress integration tests.

## Local requirements
- Docker Desktop running.
- WordPress environment started with `wp-env`.
- Work from the repository root in Bash.

## Installation (inside Docker)

```bash
wp-env run cli --env-cwd=wp-content/plugins/wordpress-event-manager composer install --no-interaction
```

`composer.lock` is tracked. When `composer.json` introduces or changes dependencies, update the lockfile explicitly and commit it. Do not commit `vendor/` or credentials.

For the WordPress integration bootstrap, WordPress Core requires PHPUnit Polyfills. The wp-env WordPress test framework currently invokes an API removed in PHPUnit 10. WEM-5 therefore uses PHPUnit `^9.6` with `yoast/phpunit-polyfills:^2.0` to match that framework (rather than claiming PHPUnit 11 compatibility). After pulling the manifest change, run this one-time full dependency reconciliation in the dedicated Docker CLI:

```bash
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager composer update --with-all-dependencies --no-interaction
```

Review and commit the resulting `composer.lock` so subsequent `composer install` restores exactly the tested dependency set.

## Focused standalone PHPUnit

```bash
wp-env run cli --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit --testsuite unit
```

The initial smoke test verifies that the plugin entrypoint exists and declares ordinary WordPress header fields. It deliberately does **not** require/activate WordPress or instantiate the plugin. It is expected to be green even while the known historical plugin bootstrap defect remains unresolved.

## WordPress integration test harness (requires actual local verification)
`wp-env` supplies the WordPress PHPUnit files at `WP_TESTS_DIR`. Run integration tests only against the separate disposable environment selected explicitly using `--config=.wp-env.test.json`; it has its own Docker containers and data, and never uses production credentials.

Start the dedicated test environment from the plugin root:

```bash
wp-env start --config=.wp-env.test.json
```

Then run the WordPress integration suite:

```bash
wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit -c phpunit.integration.xml.dist
```

The initial WordPress integration test uses WordPress' own `get_plugin_data()` API, but **does not activate** the historical plugin (known WEM-3 boot defect). The WordPress test framework may reset its dedicated disposable database; never point this command to the development or production database. This first integration suite has not yet been executed; treat unexpected failures as harness setup issues until diagnosed.

The dedicated `.wp-env.test.json` explicitly sets `testsEnvironment: false`, because each configuration otherwise also launches a secondary test site on port 8889. The existing development configuration may already own that port. The dedicated WordPress environment uses port 8890 and its own Docker database; WordPress' PHP test bootstrap must still be verified with the actual CLI integration run. Do not reset/destroy the development database.

Compact RED/GREEN commands belong to WEM-6; lint/CI to WEM-7. Record only results actually executed.

## Known blockers
WEM-3 records the historical autoload filename mismatch and incomplete module hook registration. Neither problem is fixed by this harness.
