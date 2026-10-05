# Current operational handoff — post WEM-12

Updated: 2026-10-05. This is an orientation document; remote GitHub + Jira + current source/tests remain authoritative.

## Current baseline
- Canonical integration branch: `dev`; stable `main` remains separate.
- Foundation WEM-1/WEM-2 is closed. PR #1 integrated Foundation into `dev`.
- WEM-12 CSV import accounting is **Listo** in Jira. PR #2 merged into `dev` at `20cc59a3b0c2bf1259f4e2ad716addc084ebec1a`. WEM-15, WEM-16, WEM-17 and WEM-18 are also **Listo**.
- WEM-13 (event-scoped authorization) and WEM-14 (immutable movement history/state projection) are **Por hacer**. Do not assume either is automatically next: inspect full Jira descriptions, comments, relationships, product decisions and blockers before choosing work.
- WEM-11 fail-closed security remains binding until WEM-13 explicitly replaces it with tested authorization.

## Fresh-chat behavior
A fresh chat should not carry operational workflow in its prompt. Follow `AGENTS.md`.

- Start from remote `dev`, current CI/PR state, source/tests, docs and Jira.
- Use GitHub/Jira connectors directly for remote evidence. Do not ask the user to open GitHub Actions, copy Jira statuses or provide screenshots when the connector can retrieve that same information.
- Ask the user only for genuinely local/manual evidence (Docker/wp-env/browser UI) or a real product/domain decision.
- Do not repeat tests/CI/walkthroughs merely to recreate evidence when the same relevant SHA/configuration is already covered.
- Before implementation, present a concise readiness diagnosis and proposed next story/decomposition. Do not create new issues/branches or change Jira state until that bounded action is approved.
- After TDD work is approved, continue RED → GREEN → next RED without routine confirmation. Stop for unexpected results, decisions, discrepancies, destructive actions or scope changes.

## Current testing workflow
See `docs/testing.md` for authoritative commands.

- Manual WordPress: `.wp-env.test.json`, port 8890.
- PHPUnit WordPress: `.wp-env.phpunit.json`, port 8892.
- Never run WordPress Core PHPUnit against the manual site.
- Use `pnpm tdd:red ...`, `pnpm tdd ...`, `pnpm test:runner`, `pnpm test:wp-scripts` and `pnpm test:wp-isolation` according to scope.
- Remote CI status should be inspected directly through GitHub tooling.

## Product boundaries still in force
- WEM-8 compatibility remains **new-installations-only**; no implicit legacy migration or aliases.
- Sensitive AJAX and shortcode/QR paths remain unavailable under WEM-11 until WEM-13 defines and verifies real actor/event authorization.
- WEM-14 has not introduced append-only movement history yet.
- No production deployment or production guest data is implied by completed local/CI evidence.

## Next intervention
Reconstruct remote `dev` and Jira. Compare WEM-13, WEM-14 and any newer issues by actual readiness, not backlog order. If a likely next story is waiting on product discovery or a decision, leave it untouched and identify another independent story that can safely advance.
