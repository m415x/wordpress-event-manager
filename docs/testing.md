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

The first install will resolve packages from `composer.json` if no lockfile exists. Commit the generated `composer.lock` after review so subsequent installs are deterministic. Do not commit `vendor/` or credentials. Do not claim reproducibility from a dependency manifest alone.

## Focused standalone PHPUnit

```bash
wp-env run cli --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit --testsuite unit
```

The initial smoke test verifies that the plugin entrypoint exists and declares ordinary WordPress header fields. It deliberately does **not** require/activate WordPress or instantiate the plugin. It is expected to be green even while the known historical plugin bootstrap defect remains unresolved.

## Integration tests and TDD runner
A WordPress-backed integration test suite is not yet established in this initial slice. It will use a separate database/configuration and must not load production credentials. Compact RED/GREEN commands belong to WEM-6; lint/CI to WEM-7. Record only results actually executed.

## Known blockers
WEM-3 records the historical autoload filename mismatch and incomplete module hook registration. Neither problem is fixed by this harness.
