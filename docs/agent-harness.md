# WEM agent harness — compact operational cycle

This guide explains sequencing, not additional product rules. Follow [AGENTS.md](../AGENTS.md) for normative constraints, [testing.md](testing.md) for executable commands and environment isolation, Jira for acceptance and execution evidence, and GitHub for commits, PRs and CI.

1. **Bootstrap:** resolve current remote `dev` HEAD; read `AGENTS.md`, this index, relevant contracts and `docs/handoffs/current.md`. Fetch the *full* Jira issue, comments, blockers and relationships; check existing work before creating issues. Report authorized scope and pending decisions.
2. **Candidate:** create a bounded branch from verified `dev`. Record the target issue, intended contract and current candidate SHA. Avoid unrelated cleanup.
3. **TDD iteration:** demonstrate targeted expected assertion failure (RED), then the minimal fix and targeted GREEN. Keep separate Jira comments with actual SHA, command, observed output/reason, environment and evidence source per `AGENTS.md`. Environmental failures are ERROR, not RED. At each GREEN checkpoint link verified evidence, residual gates and the next approved step.
4. **Gate and PR:** run only relevant focused gates during development and the applicable full closure gates in `docs/testing.md`; reuse valid unchanged-SHA evidence. Verify PR HEAD and CI on that SHA. Record manual acceptance, limitations and packaged artifact digest where relevant; don't claim gates not observed.
5. **Integrate:** review bounded diff, merge authorized PR into `dev`, resolve merge SHA and post-merge CI. Distinguish candidate, PR HEAD, merge, and later documentation commits; never relabel old evidence as current-head proof.
6. **Reconcile:** update Jira status/evidence and `docs/handoffs/current.md` with implementation behavior, RED/GREEN pointers, actual SHAs, pending verification, blockers and next authorized action. Confirm remote `dev` again before declaring closure. A docs-only edit requires documentation review and applicable CI, not manufactured behavioral RED or repeated manual gates.

Classify unexpected findings and protect local artifacts using `AGENTS.md`. Do not reset a WordPress environment or delete ignored files to clear a gate without checking evidence and approval. The next agent should be able to continue using only current GitHub, Jira and durable documents.
