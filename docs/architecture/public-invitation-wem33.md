# WEM-33 — Secure public invitation credential and read-only view

Status: **implementation complete; WEM-62 closure acceptance in progress**.
Jira: WEM-33. This story introduces a public read-only invitation capability without weakening WEM-13 authenticated staff authorization or WEM-14 movement-history invariants.

## Purpose

WEM-33 allows possession of one opaque invitation credential to authorize one bounded public read-only view for one invitation.

The public credential is not staff authority and never authorizes check-in, checkout, re-entry, guest listing or any other mutation.

## Public credential

The credential is a bearer token with these mandatory properties:

- generated with a cryptographically secure random number generator;
- 256 bits of random entropy;
- opaque and non-enumerable;
- not derived from or encoding `post_id`, guest ID, ticket, event slug, timestamps or any other visible identity;
- stored only as derived lookup/verification material; the bearer secret itself is never persisted in cleartext;
- the complete bearer token is exposed only by the administrative operation that creates it and there is no recovery/read-back API for the secret.

The initial implementation uses a deterministic SHA-256 digest of the high-entropy bearer token for indexed lookup. This is a lookup digest, not a human-password hash.

## Authority model

The bearer token is the **only public authority**.

None of the following grants, replaces or amplifies public authority:

- WordPress login state;
- WordPress nonce;
- guest/post ID;
- ticket/post title;
- event slug;
- page or shortcode context;
- WEM Viewer/Operator capability;
- staff event scope.

Administrative issue/rotate/revoke/reissue operations require `manage_options` in WEM-33. Viewer/Operator provisioning remains operational staff authority only.

The WEM-33 public surface must remain independent of `[wem_checkin]`, `[wem_list]`, `wem_checkin_ajax` and `wem_list_ajax`.

## Credential lifecycle

The lifecycle is:

`unissued -> active -> rotated`
`unissued -> active -> revoked`

An invitation has at most one active credential.

- **issue** creates the first active generation. Issue is rejected when an active credential already exists.
- **rotate** atomically invalidates the current active generation as `rotated` and creates a new active generation.
- **revoke** atomically invalidates the current active generation as `revoked` and leaves no active credential.
- **reissue** is explicit after no active credential exists. It creates a **new token, digest and generation**.
- A row that became `rotated` or `revoked` is historical and immutable with respect to activation. It never becomes `active` again.
- WEM-33 introduces no time-based expiration. A credential remains valid until explicit rotate or revoke.

## Persistence contract

Credential authority lives in a dedicated WEM table, not WordPress post meta.

The storage model must support:

- lookup by unique token digest;
- immutable credential generations per guest;
- explicit lifecycle status;
- administrative issuance/invalidation evidence;
- one active credential per invitation;
- transactional rotate/revoke/reissue behavior;
- new-installation schema creation and rerun safety.

The frozen semantic invariant is:

- `status=active` iff the row carries `active_slot=1`;
- `status=rotated` or `status=revoked` requires `active_slot=NULL`.

A unique `(guest_id, active_slot)` index is the physical concurrency defense for the single-active invariant. The application API must independently validate the same status/active-slot mapping and reject inconsistent writes.

The first implementation may use this bounded shape:

- `sequence BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`;
- `guest_id BIGINT UNSIGNED NOT NULL`;
- `generation BIGINT UNSIGNED NOT NULL`;
- `token_digest CHAR(64) NOT NULL`;
- `status VARCHAR(16) NOT NULL`;
- `active_slot TINYINT UNSIGNED NULL`;
- `issued_at DATETIME NOT NULL`;
- `issued_by_user_id BIGINT UNSIGNED NOT NULL`;
- `invalidated_at DATETIME NULL`;
- `invalidated_by_user_id BIGINT UNSIGNED NULL`;
- unique digest;
- unique `(guest_id, generation)`;
- unique `(guest_id, active_slot)`;
- guest/status lookup index.

The guest is the serialization unit. Lifecycle operations may serialize the canonical `wp_posts` guest row so the `unissued` state has a stable lock even before a credential row exists.

WEM remains new-installations-only. A real credential table justifies an installer in WEM-33; this does not establish a general legacy migration promise.

## Public resolver

The public resolver is a dedicated WEM surface.

Conceptual chain:

`bearer token -> bounded syntax validation -> digest -> active credential lookup -> invitado validation -> exactly-one evento validation -> public projection`

Routing requirements:

- must work with WordPress **simple permalinks**;
- must not depend on pretty permalinks;
- must not require an event slug;
- must not require a manually created WordPress page;
- must resolve authority only from the bearer credential.

Missing, malformed, unknown, rotated, revoked or otherwise inconsistent credentials are publicly indistinguishable. Guest deletion and zero/multiple canonical event terms also converge to the same public denial.

No denial response should disclose which resolution step failed.

## Minimal public projection

The allowlist is exactly:

- `guest_name`: value from `wem_nombre`;
- `event_name`: display name of the guest's exactly-one canonical `evento` term.

If `wem_nombre` is absent, presentation may use generic copy or omit the name. It must **never** fall back to `post_title`, because the current runtime uses post title as ticket identity in existing flows.

WEM-33 must not expose:

- guest/post IDs;
- ticket or post title;
- event term ID or event slug;
- organization;
- table/seat field;
- private observations;
- check-in/checkout/re-entry state or timestamps;
- staff/operator identity;
- event scopes or capabilities;
- movement ledger history;
- other guests;
- mutation controls.

The exclusion of `wem_mesa` is intentional; seating semantics remain outside WEM-33.

## Public HTTP hardening

Successful and denied public responses must avoid accidental bearer-URL leakage.

Mandatory response policy:

- `Referrer-Policy: no-referrer`;
- `Cache-Control: private, no-store`;
- no third-party resource that receives the current invitation URL or bearer credential.

Anti-indexing headers and a restrictive self-contained content policy should be used where technically compatible with the dedicated renderer.

The public page must not load external analytics, fonts, scripts, images or QR providers.

## Security and compatibility boundaries

Bearer possession grants only the WEM-33 read-only projection.

It never grants:

- `wem_list_ajax` access;
- `wem_checkin_ajax` access;
- check-in;
- checkout;
- re-entry;
- event-scope administration;
- staff provisioning;
- credential administration.

Existing WEM-13 anonymous operational guards remain mandatory.

## Explicit non-goals

WEM-33 does not implement:

- QR generation or Google Charts changes — WEM-34;
- guest/self check-in — WEM-38;
- general event/guest admin UX — WEM-35;
- granular CSV import/export authorization — WEM-36;
- seating/reservation domain;
- legacy token migration;
- implicit expiration.

## Verification expectations

Implementation proceeds through WEM-57 through WEM-62:

1. dedicated schema and invariants;
2. atomic lifecycle;
3. public resolver;
4. minimal projection;
5. HTTP/security hardening;
6. real story closure.

Each behavioral slice uses focused RED -> GREEN where applicable. Final acceptance includes clean-install/rerun verification, simple-permalink routing, issue -> read -> rotate -> revoke -> explicit reissue, indistinguishable denial behavior, header verification, no third-party leakage, no public mutation authority and preservation of existing anonymous operational guards.


## Implementation and closure candidate

WEM-57 through WEM-61 are implemented and verified on the feature branch.

- Dedicated credential storage, digest uniqueness, generation uniqueness and single-active physical defense are implemented.
- Lifecycle operations implement initial issue, atomic rotate, revoke and explicit reissue with `manage_options` authority, guest-row serialization and semantic status/active-slot validation.
- Public resolution accepts only a 43-character Base64URL bearer, hashes it with SHA-256 and resolves only an active credential for a valid `invitado` with exactly one `evento`.
- The simple-permalink route is `?wem_invitation=<bearer>`; ticket, event slug, guest ID and mixed query authority fail closed.
- The projection exposes only `guest_name` from `wem_nombre` and `event_name`; the renderer is escaped, self-contained and read-only.
- Public invitation requests emit `Referrer-Policy: no-referrer`, `Cache-Control: private, no-store` and `X-Robots-Tag: noindex, nofollow, noarchive`.
- The last fully verified pre-closure implementation HEAD is `fe2ae3e15afc63733cfa1338b1c8faf010803285`; GitHub Actions run `37795451779` completed successfully in both required jobs.

WEM-62 remains open until the real WordPress lifecycle/browser walkthrough, anonymous operational guards and final exact-HEAD closure gates are recorded. QR, self-check-in, WEM-35 admin UX and WEM-36 CSV remain outside this story.
