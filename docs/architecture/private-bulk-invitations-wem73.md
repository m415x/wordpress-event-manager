# WEM-73 — Private bulk invitation URLs/CSV: integrated contract and operational custody

Status: implemented and merged to `dev` on 2026-10-21; **not** blanket production-release approval.
Scope: one invitation per person, issuing opaque bearer URL credentials and privately exporting/retrieving an Excel-compatible CSV through authorized WP-CLI. No QR, seating, guest self-check-in, public CSV download, or mass-selection UI.

## Integrated baseline and verification

- Candidate HEAD: `9ae68e79182bd3c802e0afc41792f9f4744580fa`.
- PR #8: `https://github.com/m415x/wordpress-event-manager/pull/8`; integrated merge SHA on `dev`: `404748ee759480c3f4ae013ed2bdcaa16f1411bf`.
- Candidate GitHub Actions run `39624597563`: PHP 8.3 lint/analysis/unit and WordPress isolated integration GREEN.
- Post-merge workflow `php-quality.yml`, push to `dev`, run **#1022**, both required jobs GREEN (GitHub Actions screenshot evidence, Jira comment 13821).
- Independently installed ZIP from candidate: 69 runtime files; SHA-256 `f24f5933227f8faf987a8c93fd3c99a592b27debdcb1e431b4c134635a266b6e`. Synthetic clean-install issuance/retrieval and repeat-operation fail-closed accepted; Jira comments 13792–13795.
- Separate synthetic Docker named-volume acceptance, identical source/retrieved CSV and two distinct valid bearer URLs; SQL guests 4 and 5 each retained exactly one credential. Jira comments 13809–13810.
- Synthetic encrypted archive and restore to independent volume `wem73-private-restore-test`: recursive content comparison PASS; directory mode 0700, recovered CSV mode 0600. Encrypted archive SHA-256 `b3a02e333b43d2e369cdb97833a3fbf24f33697a9714ecdc81bf2e1d6338a565`. Jira comments 13812–13813. **The archive contains only synthetic data**.
- Premerge five-file scope review and PHP minimum 8.3 change: Jira comments 13817–13818. Merge and post-merge CI: 13819 and 13821.

## Runtime and security constraints

- WEM-33 public bearer resolver remains a separate, read-only authority; staff AJAX boundaries are unchanged.
- WEM-73 administrative commands run only under WP-CLI. Use `wp wem invitations issue --actor=<administrator-id> --event=<event-term-id> --operation=<unique-id> --private-dir=/absolute/private/path` and `wp wem invitations retrieve --actor=<administrator-id> --operation=<same-id> --private-dir=/absolute/private/path --output=/absolute/private/delivery.csv`.
- The authenticated administrative actor must have `manage_options`. A supplied numeric actor is **not** independently proof of the actual invoking OS or WP-CLI user: host access to WP-CLI and private storage is a privileged operational boundary.
- Paths must resolve outside WordPress webroot/document root. Storage directory and private destination parent must be restricted to mode 0700; artifacts mode 0600. Use an actual Linux filesystem with enforceable POSIX permissions. A Windows NTFS wp-env bind mount that appears as mode 0777 is **not suitable**.
- Do not echo bearer URLs, use `--debug`, shell xtrace, print CSV, copy it into uploads, or place it in a Git repository, HTTP endpoint, CDN, shared Drive, public link, or ordinary application log. Secrets can still appear through OS/process inspection, backups, browser/proxy/access logs of subsequently visited bearer URLs; review infrastructure logging/redaction before launch.
- Failed or ambiguous issuance/commit requires reconciliation against both SQL and private custody before retrying. A second `issue` with the same operation must fail closed; do not rotate/reissue to work around lost exports. WEM-33 bearer material is unrecoverable from digest-only SQL without WEM-73's preserved private custody.
- A protected recovered CSV may be delivered only through a separately approved confidential channel, with access limited to designated personnel.

## Required real-event runbook — open operational gates

1. Designate one accountable **custodian** and one explicitly authorized replacement; record their identities privately with the event operator, not in Git. Restrict host/WP-CLI permissions to those operators and validate WordPress administrator authority.
2. Confirm actual destination uses PHP 8.3, working WordPress plugin ZIP, restricted persistent Linux storage outside webroot and no symlink/public mapping; validate write/read/backup under the effective execution UID without exposing bearer values.
3. Import and reconcile the event guest list before issuing; one invite equals one person/seat. Confirm event term, guest count and duplicate policy. Use a unique operation ID and record IDs/counts only.
4. Generate encrypted, access-controlled off-host/off-device backups of both custody volume/files **and a temporally consistent WordPress MySQL database**, with key material held separately. Verify restore to an isolated environment and SQL/CSV reconciliation before delivery. AES-256-CBC+PBKDF2 synthetic demonstration does not itself provide authenticated encryption or establish a production backup policy.
5. Check web-server/proxy/WordPress error/access logs, shell history, job logs, backup-agent logs and third-party analytics. No bearer exposure through CLI stdout/stderr or public CSV retrieval is allowed. The public invitation bearer may appear in request URLs unless logs redact query strings.
6. Recover/export to a new locked-down private file; verify count, token uniqueness and active digest matches without printing bearer values. Deliver only via an explicitly approved secure channel.
7. Record the event date, retention deadline, custody owner, backup/restore checkpoints and a planned destruction procedure. **The retention period is not yet decided.** After operational need and incident/retention requirements have ended, remove live artifacts, delivery copies, backup copies and keys per documented policy; record evidence without secrets.

## Remaining limitations / handoff

- Real server PHP compatibility, custodian identity, off-device encrypted backup + relational DB recovery, delivery transport, logging controls and post-event destruction deadline remain **unverified for production**.
- Named Docker volume persistence and an independently restored file archive are not proof of disaster recovery of the full WordPress system.
- WEM-34 local QR integration and seat selection are separate stories and were not merged as part of WEM-73.
- Preserve exact-source evidence and the original functional SHA; later documentation commits must not be represented as an unchanged candidate ZIP.
