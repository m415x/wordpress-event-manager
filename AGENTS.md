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

## Docker/wp-env infrastructure reuse — mandatory
- Reuse existing WEM Docker/wp-env infrastructure by default. A new story, branch, subtask or TDD cycle does **not** justify a new Compose project. Inventory WEM and non-WEM Compose projects, containers, networks, named volumes, published ports and their running/stopped state before startup.
- Prefer independent disposable databases/schemas within reusable infrastructure when effective isolation can be proved. The WordPress manual/admin database must **never** be used for PHPUnit, transactional destructive probes or test fixtures. Preserve independently owned databases and evidence.
- Start services only when required; stop *only identified inactive WEM services* after checking live workloads and pending evidence. Never stop, remove or alter Docker resources belonging to other projects.
- Prefer GitHub Actions for reproducible non-interactive gates. Create a temporary isolated instance only for justified clean installations, migrations, real multi-connection concurrency, backup recovery, packaging verification or a demonstrated isolation incompatibility. Document the owner and reason.
- Never use `docker system prune`, `docker volume prune`, `docker compose down -v`, wildcard or mass deletion. Before any targeted destructive removal inventory exact resources and ownership, preserve required evidence, and obtain express approval. See `docs/agent-harness.md` for lifecycle and `docs/testing.md` for inventory commands.

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

## TDD traceability and verified handoff (post WEM-73)
- For each accepted subtask, record RED and GREEN as separate Jira comments with issue key, branch/commit SHA, exact targeted test command, observed failure assertion or successful test summary, environment, scope and next action. Logs are evidence, not a transcript archive; redact tokens, guest PII and credentials.
- Use **ERROR**, never RED, for bootstrap, configuration, Docker/DB or tooling failures; never invent test results from a proposed command. Documentation-only reviews do not require artificial RED/GREEN cycles.
- At each candidate/final gate, link the exact candidate SHA, CI run, merge SHA, artifact SHA-256, manual acceptance and unresolved operational limitations in Jira and `docs/handoffs/current.md`.
- The integrated WEM-73 baseline and private custody runbook are at `docs/architecture/private-bulk-invitations-wem73.md`. Production operation still requires real server, owner, backup, transport, logging and deletion evidence. The WP-CLI command accepts an explicit administrative actor ID: host/CLI access is itself privileged, not proof of the person invoking it.
- Fresh chat first resolves current remote `dev`, then verifies that docs and Jira reflect that HEAD; never use the historical WEM-2 section below as the latest baseline.

## Recoverable checkpoints and evidence identity
- After each accepted GREEN, make the checkpoint recoverable in the owning Jira subtask: exact implementation SHA, covered assertion/contract, command and observed result or link to its earlier evidence, remaining checks, and the next authorized RED or other bounded action. Preserve the separate RED/GREEN comments already required above; do not duplicate their full logs in the checkpoint.
- Classify unexpected findings before acting: **behavioral defect** (a reproducible contract violation, tracked and fixed within approved scope or filed as separate Jira work), **technical debt** (known maintainability limitation with explicit disposition), **infrastructure/tooling ERROR** (not an expected behavioral RED; diagnose or block), or **product/security blocker** (stop and obtain a decision). Record owner, impact, disposition and next action in existing Jira work; never silently broaden scope or create duplicate issues.
- Keep evidence identities distinct: **candidate** is the tested implementation SHA, **PR HEAD** is the exact commit submitted to CI/review, **merge SHA** is the integration commit on `dev`, and **post-merge documentation SHA** records later documentation changes only. Evidence is reusable only for the code/configuration it exercised; do not attribute old results to newer commits. Link runs and manual results to their actual source and scope.
- Before destructive cleanup or environment reset, inventory local untracked/ignored artifacts (including databases, ZIPs, logs and test exports), classify reproducible versus required evidence, retain necessary redacted checksums/log references in an appropriate safe location, and obtain authorization for any destructive operation. Never commit guest data, tokens, credentials or private CSVs.
- On PR merge, re-read remote `dev`, check the actual merge and applicable post-merge CI, reconcile Jira and `docs/handoffs/current.md`, and record outstanding gates and the next authorized work. A fresh chat must not infer readiness from a prior-chat statement or a PR candidate alone. See `docs/agent-harness.md` for the compact workflow; `docs/testing.md` owns executable verification commands.

## Integrated WEM-2 Foundation baseline
- Foundation v2.2.1 was merged into **`dev`**, not `main`, through [PR #1](https://github.com/m415x/wordpress-event-manager/pull/1) on 2026-10-02; exact integration merge commit: `0b32514cb20ded251e21da70cc1b3f8681c1b6fd`. Historical `main` source before Foundation: `74116d06ccf40f3316cee7a33b034df45f8efa7b`. See `docs/handoffs/foundation-baseline.md` for acceptance evidence and pending post-merge Jira/CI status.
- WEM-3 audited an earlier plugin bootstrap defect; WEM-11 subsequently fixed and verified actual WordPress activation, modular hooks and temporary fail-closed sensitive routes. **Do not conflate a successful Docker start or registration with public authorization or safe public check-in.** All guest-sensitive AJAX/shortcode routes must remain unavailable until WEM-13 independently proves actor- and event-scoped permissions.
- WEM-4 establishes delivery workflow; WEM-5/6/7 establish PHPUnit, compact TDD and CI; WEM-8 explicitly migrates naming for **new installations only**; WEM-9 reconciles the legacy source; WEM-10 owns Foundation closeout. WEM-12 CSV counters, WEM-13 authorization and WEM-14 movement history are deferred independent issues.
- Fresh chats must read current remote `dev` first, compare its HEAD against verified CI and Jira, then select/approve the next story; don't continue directly from the obsolete Foundation story branch.
