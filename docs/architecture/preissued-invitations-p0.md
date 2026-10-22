# P0 — Preissued invitations with later irreversible assignment (2026-10-22)

Status: approved architectural direction; production issuance **NOT** authorized. WEM-82/T2 remains suspended. This is a distinct new capability; WEM-33, WEM-34, and WEM-73 are not reopened.

## Verified integrated boundaries
- WEM-33 issue/rotate/revoke/reissue store active token digest per **existing** guest_id; its public read-only resolver requires a real `invitado` with exactly one canonical `evento`. Never change that resolver's authority to accept an arbitrary preissued token.
- WEM-73 bulk CLI discovers existing published `invitado` rows under one event; its private custody requirements and operation reconciliation remain mandatory. It does **not** issue anonymous inventory and does not render QR or print artifacts.
- WEM-34 **feature branch only** `feat/WEM-34-local-secure-invitation-qr` contains `WEM_Local_QR_Encoder` (chillerlan/php-qrcode + ext-mbstring, SVG); its admin delivery expects a guest. This implementation needs a reviewed forward-port of the encoder and Composer runtime/packaging to a branch based on current dev, without merging its old branch wholesale. No remote QR provider is permitted.

## New persistence (not fictional WordPress guests)
Introduce event-bound invitation inventory separately from guest credentials: immutable invitation id, event_term_id, status unassigned/assigned/revoked (with separately authorized rotate if supported), token_digest indexed unique, exactly one active token generation, issued_by, issued_at; nullable assigned_guest_id with one-time assignment timestamp, assigned_by and append-only assignment audit. The actual bearer must exist only in restricted private custody or controlled printable artifact; a digest is not recoverable.

## Transactional one-time binding
With server-authenticated administrator authority, lock unassigned invitation row and canonical guest row in a globally documented stable order, validate exactly one canonical `evento`, same event, guest eligible, guest not already attached to any inventory invitation, and no conflicting WEM-33 active credential. Record assigned_guest_id exactly once and an append-only audit row in the **same** transaction. Deny repeated assignment (even same guest), cross-event assignment, duplicate guest, invalid guest, revoked inventory, and any status corruption; no side-effects on DENY. Never silently rotate or revoke preprinted bearer. Define bridge to WEM-33 resolver separately: unassigned must fail closed with uniform public response; assigned resolves only the correct guest under least privilege and cannot grant checkin or seating while unassigned.

## Private batch output and print
One-to-one `inventory invitation id -> canonical URL -> locally encoded QR SVG -> print slot`, backed by a non-secret external-facing human slot identifier or opaque print reference, NOT DB ID as bearer. Create all artifacts atomically with custody reconciliation before delivery; no bearer in stdout, logs, source, git, webroot, public image URL or analytics. Printable contact sheet/PDF must be held in protected custody because every QR IS a bearer. Verify each SVG payload against the exact URL in private manifest in a synthetic acceptance test, count and deduplicate, prove scan-resolution before printing. Do not treat a URL CSV, SVG or PDF as equivalent.

## Required RED/GREEN and operational gates
1. Schema uniqueness/immutable event + active digest/assigned guest protections; installation/rerun and independently-connected InnoDB concurrency.
2. Unauthorized/duplicate/cross-event/revoked assignment denies with no mutation; valid one-time bind committed with append-only audit; injected failure rollback.
3. Public unassigned fail-closed, assigned minimal projection; seat/check-in denied until real assignment and other existing auth checks pass.
4. Local QR encoder packaged runtime, no network calls; token->URL->QR verified on synthetic fixtures; reproducible private print artifact and secure custody.
5. WEM-73 operational real-issuance gates: designated custodian/replacement, PHP/host, private 0700/0600 Linux storage, temporally consistent encrypted off-host DB+files backup/restore, URL-query logs redaction, confidential delivery, retention/deletion deadline. Until these pass: **NO REAL EVENT ISSUANCE, NO PRODUCTION GO**.

Do not modify old WEM-73 bulk CLI to accept anonymous guest records. This document defines required new capability; it does not claim functional implementation or completed testing.
