# WordPress Event Manager — Agent Rules

## Product and repositories
- Product: WordPress Event Manager (WEM), a WordPress plugin for event invitations, access control, and seating.
- GitHub: `m415x/wordpress-event-manager`.
- Jira: `https://trail-running-workout-planning.atlassian.net/jira/software/projects/WEM/boards/67` (project key `WEM`).
- Do not assume older chats, downloaded legacy snapshots, or the current plugin readme describe implemented behavior. Inspect code and verify relevant assertions.

## Naming invariant — mandatory
- All maintained code, classes, hooks, PHP globals, prefixes, text domains, shortcodes, metadata keys, assets, documentation, UI and product branding must use neutral WordPress Event Manager naming.
- No references to the previous agency/product brand or its abbreviated identifiers are permitted in the delivered modernized plugin.
- Existing historical identifiers currently embedded in the v2.2.1 baseline require an explicit, tested migration, tracked in WEM-8. Do not perform a blind search-and-replace: consider saved WordPress options, post meta, shortcodes and persisted content.
- This rule prohibits introducing new legacy identifiers even temporarily. Historical audit evidence stays in Jira, not copied into new source documents.

## Git and Jira delivery
- `main`: stable/release history; no story commits unless explicitly approved.
- `dev`: canonical integration branch. Start a new feature branch from its current head.
- Story branches: `feat/WEM-<story-key>-<short-topic>` or a type-appropriate equivalent.
- Merge a verified and closed story into `dev`. Do not merge incomplete work into `main`.
- Use conventional commits (gitmoji is permitted). Keep commits task-scoped.
- Jira hierarchy: Epic > Story > bounded Subtasks, with one explicit final story-closure subtask. Jira owns statuses, acceptance criteria, blockers, and actual execution evidence.
- Transition work to `En curso` before implementation, and to `Listo` only after required evidence; never mistake a proposed command for an executed one.
- Before creating new story subtasks or branches, reconcile implementation with durable docs and the full Jira issue, then request approval for bounded decomposition.

## Tool autonomy and human intervention
- **Use connected systems directly whenever they can answer the question.** GitHub is the authority for remote branches, commits, PR state and CI/job results; Jira is the authority for issue state, relationships and recorded acceptance evidence. Query them yourself instead of asking the user to open the same page, copy statuses or provide screenshots.
- Do **not** request visual confirmation of GitHub Actions, PR state, branch SHA or Jira status when the connector can retrieve that evidence. A user screenshot may supplement evidence but is not required merely to confirm remotely accessible data.
- Ask the user to intervene only when the required evidence genuinely exists outside available connectors/tools: local Docker/wp-env execution, browser-only/manual UI behavior, an unavailable credentialed session, physical/device behavior, or a product/domain decision that requires human choice.
- When local execution is necessary, give the smallest useful Bash-compatible command (or bounded manual walkthrough), state the expected result, and wait for the observed output. Never claim local GREEN without user/runtime evidence.
- If a connector/tool cannot expose a required datum, say exactly what is missing before requesting manual evidence. Do not ask for a manual check first and then query the same remote evidence afterward.
- Reuse valid evidence tied to the same code/configuration when nothing relevant changed. Do not rerun tests, CI, walkthroughs or merges merely to recreate evidence; rerun when the code/configuration affecting that gate changed, when the closure contract explicitly requires current-head evidence, or when prior evidence is ambiguous/invalid.
- Once a bounded story/decomposition and its TDD cycle are approved, perform connector-side Git/Jira operations and advance **RED → GREEN → next RED** without asking for routine confirmations. Stop for unexpected results, material discrepancies, real product/security decisions, destructive actions or scope changes.

## Source of truth and fresh-chat bootstrap
A fresh chat is only an entry point. All operational detail must be recoverable from the repository and Jira.

1. Start from **remote `dev`**, not a remembered branch or prior-chat SHA. Verify current HEAD, relevant open/merged PRs and CI through the available GitHub connector.
2. Read `AGENTS.md` and `docs/README.md` at that HEAD, then inspect relevant source, tests, lockfiles and configuration. Source/tests establish actual behavior.
3. Read current durable contracts in `docs/architecture/`, the operational handoff in `docs/handoffs/current.md`, and any baseline document linked from the docs index.
4. Query Jira directly. For candidate work, inspect description, acceptance criteria, comments/evidence, parent/child relations, blockers and current status; do not select work solely by issue number, backlog order or a satisfied technical dependency.
5. Reconcile repository, CI, docs and Jira. If a likely next story still depends on product discovery, an unresolved decision or an operational blocker, **do not materialize or implement it**. Identify another safely independent candidate when one exists.
6. Before implementation, report a concise diagnosis: verified baseline, discrepancies, stories that can actually advance, blocking decisions and the recommended next intervention. Do not create issues/branches or mutate Jira during this diagnostic unless the user has already approved that bounded action.
7. Never use chat memory as project authority. Every completed story must leave enough durable repository/Jira evidence for this bootstrap to work without the previous conversation.

## Engineering boundaries
- WordPress remains the host: PHP and WordPress APIs own server-side business rules and authorization.
- Browser UI is not a security boundary. Public invitation capabilities and authenticated staff capabilities must be separate, explicit and tested.
- Never treat a WordPress nonce as authentication or authorization. Check actor, event scope and capabilities server-side before sensitive reads or mutations.
- QR codes may convey an invitation secret; scanning them does not independently authorize a check-in.
- Seat availability and reservations must be validated atomically on the server, not inferred from visual canvas state.
- Audit history must be append-only when introduced. Preserve distinctions between historical facts and current projections.
- No credential, secret, personal guest data or production database export in Git.

## Testing and verification
- Use RED → GREEN → refactor for new/changed behavior when a focused automated test can express the contract economically. Do not manufacture RED for audits, documentation-only reconciliation or administrative Jira work.
- First establish a focused expected assertion failure and confirm RED for the intended reason; bootstrap/environment errors are not behavioral RED.
- After an approved cycle, implement the smallest coherent change for GREEN and continue directly to the next approved RED. Request diagnostics only on unexpected failures or when evidence is insufficient to distinguish product code from infrastructure.
- Prefer focused tests during implementation. Run full applicable suites/static/security gates and manual walkthroughs at story closure, but reuse already-valid exact-SHA evidence unless a relevant change invalidated it.
- Canonical interactive commands are `pnpm tdd:red tests/Unit/ExampleTest.php` and `pnpm tdd tests/Unit/ExampleTest.php`, with equivalent Integration paths. Use `-v`/`--verbose` only when diagnostics are needed. Normal output is `RED`, `GREEN` or `ERROR`.
- WordPress local runtime uses Docker/`wp-env`; pnpm is the canonical JS package manager. Never commit credentials, local database exports or production data.
- **Database isolation is mandatory:** `.wp-env.test.json` is the manual browser/admin site on port 8890; `.wp-env.phpunit.json` is the WordPress PHPUnit site on port 8892. `pnpm tdd` and CI integration must use only the PHPUnit configuration. Never point WordPress Core tests at the manual site.
- Current commands and gate scope live in `docs/testing.md`; do not recover obsolete commands from old Jira comments or chat history.
- When user execution is genuinely required, use Bash-compatible commands and ask only for the missing local result.

## Docs and closure
- Repository docs and agent instructions are written in English. Product language/localization decisions are separate.
- `README.md` (when introduced) and `docs/README.md` link current material. Put durable contracts in `docs/architecture/`, temporary operational handoff in `docs/handoffs/`.
- Avoid speculative large epics, transcript archives, and unrelated cleanup. Track deferred decisions as Jira work.
- Closure subtask: reconcile approved subtasks and acceptance criteria, run actual applicable full gates and manual walkthrough, record passing/failing evidence, update durable docs and handoff, then mark the story `Listo` and merge to `dev`.

## Integrated WEM-2 Foundation baseline
- Foundation v2.2.1 was merged into **`dev`**, not `main`, through [PR #1](https://github.com/m415x/wordpress-event-manager/pull/1) on 2026-10-02; exact integration merge commit: `0b32514cb20ded251e21da70cc1b3f8681c1b6fd`. Historical `main` source before Foundation: `74116d06ccf40f3316cee7a33b034df45f8efa7b`. See `docs/handoffs/foundation-baseline.md` for acceptance evidence and pending post-merge Jira/CI status.
- WEM-3 audited an earlier plugin bootstrap defect; WEM-11 subsequently fixed and verified actual WordPress activation, modular hooks and temporary fail-closed sensitive routes. **Do not conflate a successful Docker start or registration with public authorization or safe public check-in.** All guest-sensitive AJAX/shortcode routes must remain unavailable until WEM-13 independently proves actor- and event-scoped permissions.
- WEM-4 establishes delivery workflow; WEM-5/6/7 establish PHPUnit, compact TDD and CI; WEM-8 explicitly migrates naming for **new installations only**; WEM-9 reconciles the legacy source; WEM-10 owns Foundation closeout. WEM-12 CSV counters, WEM-13 authorization and WEM-14 movement history are deferred independent issues.
- Fresh chats must read current remote `dev` first, compare its HEAD against verified CI and Jira, then select/approve the next story; don't continue directly from the obsolete Foundation story branch.
