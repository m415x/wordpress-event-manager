# Current operational handoff — WEM-73 integrated; delivery custody gates open

Updated: 2026-10-21. Start from GitHub `dev`, Jira and current code; this handoff is orientation, not higher authority.

## Canonical integration baseline

- `dev`: WEM-73 PR #8 merged at `404748ee759480c3f4ae013ed2bdcaa16f1411bf`.
- WEM-73 candidate HEAD: `9ae68e79182bd3c802e0afc41792f9f4744580fa`, GitHub Actions `39624597563` GREEN 2/2. Post-merge push `php-quality.yml` run #1022 GREEN 2/2; Jira 13821.
- WEM-73 source-focused ZIP: 69 runtime files; SHA-256 `f24f5933227f8faf987a8c93fd3c99a592b27debdcb1e431b4c134635a266b6e`. Acceptance: clean WP ZIP installation, two-guest private URL/CSV, duplicate operation denied; separate persistence / SQL recon / encrypted archive restore synthetic PASS. See [durable WEM-73 contract](../architecture/private-bulk-invitations-wem73.md) and Jira 13792–13795, 13809–13821.
- WEM-33 PR #6 merged previously at `79b6ec8dde1b0302f47ee0617e9361675ac04c55`; WEM-33 public read-only bearer resolver is implemented. Do not resurrect outdated branch-only WEM-33 closure claims.
- Earlier integrated stories WEM-2, 12, 13, 14 and 32 remain covered by the docs index and architecture contracts. `main` is not the integration target.
- This documentation branch `docs/WEM-73-post-merge-handoff` is docs-only and **must not** be mistaken for the unchanged candidate HEAD/ZIP. No QR integration was made.

## Functional delivery status

The verified minimum WEM-73 path is WP-CLI administrative bulk credential issuance and private retrieval of an Excel-compatible CSV of invitation bearer URLs, intended after CSV guest import. It is not an admin GUI with bulk selection, not QR images, and not seat selection. Each invitation is for one person.

Public bearer possession grants only the WEM-33 minimal read-only public view. Check-in remains an authenticated staff operation under WEM-13/14. No public CSV retrieval and no token output in normal CLI success logs.

**No operational GO for real guest credentials yet.** Appoint custodian and replacement; confirm server PHP 8.3, private persistent POSIX volume, 0700/0600, administrator/host access, off-host encrypted backup with key custody, consistent MySQL backup/restore, sensitive HTTP/access/error log policy, confidential delivery channel, documented retention and deletion deadline. Synthetic Docker volume restore is not a full WordPress restore. See WEM-73 contract/runbook.

## Immediate next work

1. Reconcile the docs-only PR against remote `dev`; verify no `includes/`, plugin bootstrap, tests, build scripts, lockfiles or runtime ZIP content changed.
2. Verify docs-only PR CI when relevant; merge to `dev` only after approved review, then update Jira issue WEM-73 with documentary SHA, post-merge CI result, and whether operational gates are still open.
3. Preserve the functional candidate as shipped and do not quietly integrate WEM-34 (local QR), seating, guest self check-in or unrelated UX.
4. Keep temporary test files (such as `.wp-env.wem73-custody.json`, `wem73-custody-guests.php`, `wem73-verify-persistent.php`) untracked/out of the delivered plugin; no bearer data in repository.
5. Read Jira current statuses before transitioning WEM-73 or related closure subtasks; technical integration and operational readiness are distinct decisions.

## RED/GREEN evidence and fresh-chat continuity

For every bounded TDD subtask, record Jira RED and GREEN comments **as they occur**, each with issue/subtask key, relevant HEAD SHA, exact focused test/command, observed expected-failure reason or passing summary, environment, and any blocker/next bounded action. Do not record generic invented PASS, tokens, guest PII, credentials or full bearer logs. An infrastructure failure is ERROR, never legitimate RED. For audit/docs-only work, log verification and disposition without manufacturing TDD RED. Link PR, CI run and ZIP digest to their exact SHAs; leave a fresh-chat-safe handoff before context loss.

## Test environment and constraints

- Manual WordPress `.wp-env.test.json`: 8890; PHPUnit `.wp-env.phpunit.json`: 8892, isolated. See `docs/testing.md`.
- Verified WEM-73 custody used an isolated synthetic `wp-env` at 8898 with Docker named volume; it must not be interpreted as a durable production deployment.
- WEM-8 new-installation-only boundary; WEM-13 staff event scope and anonymous protections remain required. PHP minimum in the merged plugin is 8.3.
- No blanket proof of infrastructure request-log redaction, protected real-event delivery, backup key escrow, or post-event destruction yet.
