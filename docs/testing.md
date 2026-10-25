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

## Inventory and infrastructure reuse

Before starting `wp-env` in **any** branch or worktree, inspect all owners, ports and resources without modifying them:

```bash
docker compose ls -a
docker ps -a --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
docker network ls
docker volume ls
docker ps --format 'table {{.Names}}\t{{.Ports}}'
wp-env status --config=.wp-env.phpunit.json
```

Review Compose labels for exact ownership, without assuming names or ports are permanent:

```bash
docker inspect <exact-container-name> --format '{{index .Config.Labels "com.docker.compose.project"}}'
docker inspect <exact-container-name> --format '{{range .Mounts}}{{println .Type .Name .Destination}}{{end}}'
```

Default to reusing an identified WEM environment, with independent disposable database/schema and recorded test connection identity. Separate manual WordPress data from all PHPUnit data; merely changing the port or Git worktree does **not** establish database isolation. Never run a test against a live/manual or another project's database. Before reusing persistent PHPUnit data, assess test pollution and reuse only proven-safe fixtures; do not silently reset.

Use `pnpm wp:phpunit:start` only after verifying ownership, occupancy and the existing configuration; `pnpm wp:phpunit:stop` stops that selected environment without removing volumes. Prefer GitHub Actions for reproducible unattended verification. A new isolated stack is exceptional: clean installation, migration, real concurrent connections, backup restoration, packaging or demonstrated inability to isolate on existing resources. Inventory and document the reason before starting one.

Before selectively stopping inactive WEM containers, check current processes, related Compose dependencies and uncollected logs/artifacts. Do not stop services owned by other projects. Destruction requires exact resource/owner identification, preserved evidence and express authorization: never run `docker system prune`, `docker volume prune`, `docker compose down -v` or wildcard/mass deletion. Treat names/ports shown in current handoffs as observations, not durable allocation rules.

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

**WEM-87 integration partition:** `pnpm tdd integration` first classifies every `tests/Integration/**/*Test.php` file into WordPress Core-managed `WP_UnitTestCase` versus durable InnoDB `PHPUnit\\Framework\\TestCase`. An unclassified or ambiguous new test causes `ERROR`, never silent omission. The runner invokes each nonempty group in a distinct **sequential PHPUnit CLI process** (WordPress bootstrap once per group), aggregating failures; GREEN requires both groups to pass and the PHP syntax gate. The canonical focused file commands remain a single PHPUnit invocation. `--filter` and `-v` remain accepted, including when a filter selects tests in only one group. No `@runTestsInSeparateProcesses`, no new Compose project, and no alteration to product transaction guards.

Regression: `bash tests/Runner/integration-partition.test.sh` exercises classifier completeness, rejection of unknown tests, both group invocations and failure propagation with mocks. The runner regression and complete partitioned integration suite were verified locally (Jira 14167, 14193). GitHub Actions run #1308 on SHA `7a953b124f00e53a5d102915a551656022603f89` confirmed PHP quality and WordPress integration GREEN 2/2 before merge. This does not substitute for post-merge verification on `dev`.

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

## Evidence validity and local artifacts

A result is authoritative only for its recorded implementation/configuration SHA and environment. Reuse it when relevant inputs are unchanged; rerun the affected gate if code, tests, configuration, environment or the closure contract invalidates coverage. A documentation-only change does not itself require rerunning browser/manual product acceptance. Record the exact run or locally observed command and its source, not an inferred PASS.

Before deleting or resetting any ignored/untracked `.db`, `.zip`, `.log` or exported artifact, inventory and classify it. Retain required evidence outside Git without guest data or secrets in public logs; request approval for destructive cleanup. Keep PHPUnit and manual WordPress data isolated.

## Source of truth

For test commands and environment boundaries, this file and the current scripts/configuration are authoritative. For story-specific acceptance evidence and historical runs, use Jira and the current handoff. Chat transcripts are not project authority.
