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

`composer.lock` is tracked, so normal `composer install` resolves locked dependency versions. Do not commit `vendor/` or credentials.

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

`wp-env` currently emits a deprecation warning for starting both development and test services with one config file. The new `--config` explicitly provides separate isolation. Avoid resetting/destroying the development database.

Compact RED/GREEN commands belong to WEM-6; lint/CI to WEM-7. Record only results actually executed.

## Known blockers
WEM-3 records the historical autoload filename mismatch and incomplete module hook registration. Neither problem is fixed by this harness.
