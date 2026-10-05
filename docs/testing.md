# Testing and verification

This document is the **current operational testing guide** for WordPress Event Manager. Historical setup evidence belongs in Jira and the handoffs; do not recover obsolete commands from old conversations.

## Principles

- Work from the repository root in Git Bash.
- Docker Desktop must be running for `wp-env`.
- Use pnpm scripts as the canonical entrypoints.
- Never use production credentials or databases.
- Manual WordPress and PHPUnit WordPress are intentionally separate environments.
- A written command, configured workflow or proposed test is not evidence. Record only observed results.
- Reuse valid evidence for the same relevant code/configuration. Do not rerun gates merely to recreate screenshots or logs.

## Environments

| Configuration | Port | Purpose |
| --- | ---: | --- |
| `.wp-env.test.json` | 8890 | Manual/admin/browser walkthroughs and real HTTP smoke |
| `.wp-env.phpunit.json` | 8892 | PHPUnit and WordPress Core test bootstrap |

The PHPUnit environment must never share the manual site's database. WordPress Core tests can mutate WordPress options, including plugin activation.

### Manual WordPress

```bash
pnpm wp:start
pnpm wp:status
pnpm wp:verify:anon
pnpm wp:stop
```

- `wp:start` starts the manual site and activates WEM only when needed.
- `wp:verify:anon` checks the two sensitive anonymous AJAX routes over **real HTTP** and requires HTTP 403 plus the fixed deny-all JSON.
- `wp:stop` preserves site data; these helpers expose no reset/delete action.

### Isolated PHPUnit WordPress

Start once before local PHPUnit work, or again after stopping the environment:

```bash
pnpm wp:phpunit:start
```

This starts `.wp-env.phpunit.json` and restores locked Composer dependencies inside its CLI container.

Stop it without deleting data:

```bash
pnpm wp:phpunit:stop
```

## TDD commands

Focused expected RED:

```bash
pnpm tdd:red tests/Unit/ExampleTest.php
pnpm tdd:red tests/Integration/ExampleTest.php
```

Focused GREEN:

```bash
pnpm tdd tests/Unit/ExampleTest.php
pnpm tdd tests/Integration/ExampleTest.php
```

Suite-level GREEN:

```bash
pnpm tdd unit
pnpm tdd integration
```

Add `-v` or `--verbose` only when diagnostics are needed.

Runner semantics:

- `RED`: PHPUnit produced an expected assertion failure, with no bootstrap/fatal error.
- `GREEN`: focused PHPUnit passed and the runner's PHP syntax gate passed.
- `ERROR`: unexpected pass in RED mode, assertion failure in GREEN mode, bootstrap/infrastructure error, or syntax failure.

After a story/decomposition and its TDD cycle are approved, continue **RED → GREEN → next RED** without routine user confirmation. Stop when the observed result is unexpected or a real product/security/scope decision is needed.

## Helper regression checks

```bash
pnpm test:runner
pnpm test:wp-scripts
pnpm test:wp-isolation
```

- `test:runner` checks the shell runner's classification behavior with mocks; it is not PHP product coverage.
- `test:wp-scripts` checks local WordPress helper behavior without Docker.
- `test:wp-isolation` statically guards the manual/PHPUnit configuration boundary.

## PHP quality gates

Run inside the isolated PHPUnit environment:

```bash
wp-env run cli --config=.wp-env.phpunit.json \
  --env-cwd=wp-content/plugins/wordpress-event-manager \
  composer validate --strict --no-interaction

wp-env run cli --config=.wp-env.phpunit.json \
  --env-cwd=wp-content/plugins/wordpress-event-manager \
  composer run lint:php

wp-env run cli --config=.wp-env.phpunit.json \
  --env-cwd=wp-content/plugins/wordpress-event-manager \
  composer run analyse:php
```

Scope matters:

- PHP syntax is broader than PHPCS/PHPStan.
- PHPCS and PHPStan certify only the paths configured in `phpcs.xml.dist` and `phpstan.neon.dist`.
- Do not describe those tools as a whole-plugin audit unless their configurations are intentionally expanded and verified.

## CI

GitHub Actions workflow: `.github/workflows/php-quality.yml`.

Use GitHub directly for remote CI evidence:

- inspect the exact commit SHA and associated workflow run;
- verify both jobs and their conclusions;
- inspect failed job logs when needed;
- do **not** ask the user for a screenshot or copy/paste of GitHub Actions when the connector exposes the same evidence.

User intervention is appropriate only when the required result is outside available remote tooling, such as a local Docker run or a manual browser/UI walkthrough.

A previous CI success remains useful evidence for its exact relevant code/configuration. Story closure may still require a newer run if subsequent changes affect CI-scanned files or the closure contract requires current-head verification.

## Security-relevant manual verification

Automated WordPress tests do not substitute for real HTTP transport or browser/session behavior when those are acceptance requirements.

For the current WEM-11 fail-closed baseline:

- both sensitive AJAX actions must reject anonymous and authenticated access with HTTP 403 and fixed unavailable JSON;
- all four sensitive shortcodes must remain unavailable until WEM-13 changes the authorization contract;
- never weaken these guards merely to make a walkthrough possible.

Use the manual environment for browser/admin verification. Ask the user only for behavior that cannot be observed through connected GitHub/Jira tooling or automated repository tests.

## Compatibility and data safety

- WEM-8 remains **new-installations-only**. Do not introduce legacy aliases, dual-read paths or migrations unless a later Jira story explicitly changes that contract.
- No production guest data, credentials or database exports belong in tests or Git.
- Do not reset/destroy the manual WordPress site to repair a test failure; diagnose environment isolation first.

## Source of truth

For test commands and environment boundaries, this file and the current scripts/configuration are authoritative. For story-specific acceptance evidence and historical runs, use Jira and the current handoff. Chat transcripts are not project authority.
