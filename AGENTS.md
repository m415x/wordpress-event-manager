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

## Source of truth and fresh-chat bootstrap
1. Read repository `AGENTS.md` and `docs/README.md` on the current `dev` head; read any nested agent instructions.
2. Inspect the current relevant PHP, JS/TS, schemas, tests and lockfiles. Source and tests establish actual behavior.
3. Consult `docs/architecture/` for current domain invariants, and the current `docs/handoffs/` operational handoff.
4. Read Jira story acceptance criteria, issue relationships, comments and current statuses.
5. Explicitly reconcile discrepancies. Never quietly infer a missing decision from chat memory.
6. Report current behavior, reusable infrastructure, risks, open decisions and task decomposition before implementation.
7. Every completed story must publish enough durable evidence to resume in a fresh chat.

## Engineering boundaries
- WordPress remains the host: PHP and WordPress APIs own server-side business rules and authorization.
- Browser UI is not a security boundary. Public invitation capabilities and authenticated staff capabilities must be separate, explicit and tested.
- Never treat a WordPress nonce as authentication or authorization. Check actor, event scope and capabilities server-side before sensitive reads or mutations.
- QR codes may convey an invitation secret; scanning them does not independently authorize a check-in.
- Seat availability and reservations must be validated atomically on the server, not inferred from visual canvas state.
- Audit history must be append-only when introduced. Preserve distinctions between historical facts and current projections.
- No credential, secret, personal guest data or production database export in Git.

## Testing and verification
- Use RED -> GREEN -> refactor for new/changed behavior where a focused automated test can express the contract economically.
- First establish a focused expected-failure test and confirm RED for the intended reason. Environmental/bootstrap failures do not count.
- Change the smallest coherent production behavior needed for GREEN. Record actual outcomes; request diagnostics only on unexpected failures.
- Prefer targeted tests during implementation; reserve full suite, static analysis, security-relevant verification, plugin activation walkthrough and acceptance review for story closure.
- WEM-5/6/7 established PHPUnit, compact Docker TDD and CI. Interactive commands: `pnpm tdd:red tests/Unit/ExampleTest.php` (expected assertion RED), `pnpm tdd tests/Unit/ExampleTest.php` (tests and PHP syntax GREEN), and equivalent `tests/Integration/` paths. Use `-v`/`--verbose` to reveal diagnostics. Default mode output is `RED`, `GREEN` or `ERROR`. The refined pnpm frontend and verbosity controls are pending local verification under WEM-8; do not attribute the prior WEM-6 gate results to them until rerun.
- WordPress local runtime uses Docker/`wp-env`; any local config must avoid credentials and environment secrets. Maintain pnpm as the canonical JS package manager; commit `pnpm-lock.yaml`, not an npm lockfile once pnpm setup is migrated.
- **Database isolation is mandatory:** `.wp-env.test.json` (manual browser/admin, port 8890) and `.wp-env.phpunit.json` (WordPress PHPUnit, port 8892) must stay independent. Start the PHPUnit site using `pnpm wp:phpunit:start` before first run; `pnpm tdd` and CI integration must use only `.wp-env.phpunit.json`, never the manual site's CLI. WordPress test bootstrap can alter site options, including plugin activation. Do not reset, drop or auto-repair the manual site's database after PHPUnit; first run `pnpm test:wp-isolation` and confirm its plugin stays Active after the integration suite.
- When asking the user for commands, use Bash-compatible syntax.

## Docs and closure
- Repository docs and agent instructions are written in English. Product language/localization decisions are separate.
- `README.md` (when introduced) and `docs/README.md` link current material. Put durable contracts in `docs/architecture/`, temporary operational handoff in `docs/handoffs/`.
- Avoid speculative large epics, transcript archives, and unrelated cleanup. Track deferred decisions as Jira work.
- Closure subtask: reconcile approved subtasks and acceptance criteria, run actual applicable full gates and manual walkthrough, record passing/failing evidence, update durable docs and handoff, then mark the story `Listo` and merge to `dev`.

## WEM-2 active baseline
- Story: WEM-2, Foundation and v2.2.1 baseline; initial historical `main` SHA `74116d06ccf40f3316cee7a33b034df45f8efa7b`.
- WEM-3 audited source and observed a reported local `wp-env` CLI bootstrap fatal. Its findings and exact legacy identifiers live in WEM-3 Jira evidence.
- Do not conflate a successful Docker start with successful WordPress plugin boot.
- WEM-4 establishes this workflow; WEM-5/6/7 establish testing and CI; WEM-8 explicitly migrates identity; WEM-9 reconciles legacy behavior; WEM-10 performs closure.
