# WEM-14 — Immutable guest movement ledger and state projection

Status: **contract implemented; closure verification pending** (2026-10-07).
Jira: WEM-14. This contract extends the WEM-13 authenticated staff/admin transition boundary without replacing or duplicating its authorization rules.

## Purpose

WEM-14 introduces durable append-only history for accepted guest movement transitions while retaining an efficient mutable projection for current operational state.

The ledger is the historical source of truth. The projection answers the current-state question only.

## Historical ledger

Accepted movements are stored in a dedicated WEM database table, never in WordPress post meta.

Each row is immutable after commit and contains at least:

- a WEM-generated UUID `movement_id` unique across the installation;
- an internal monotonic sequence used for durable total ordering;
- `guest_id`;
- canonical `event_term_id` captured at the time of the movement;
- `movement_type`: `checkin`, `checkout` or `reentry`;
- server-side `occurred_at`;
- durable WordPress `actor_user_id`;
- bounded technical `source` (initially `staff_web`);
- `decision=accepted`.

`occurred_at` is descriptive time, not the sole ordering mechanism. The monotonic sequence defines total durable ledger order.

The persistence API for movement facts is append/read oriented. WEM-14 must not introduce mutation or deletion of committed movement facts.

## Mutable projection

Existing current-state metadata may remain the bounded read projection used by the operational UI:

- `wem_checkin`;
- `wem_checkin_at`;
- `wem_checkin_by`;
- `wem_checkout`;
- `wem_checkout_at`;
- `wem_checkout_by`.

These values may be overwritten or cleared to represent current state and compatibility with existing reads. They are not historical evidence after WEM-14.

If ledger and projection disagree, WEM must not silently rebuild, infer or overwrite either representation during an operational transition. The ledger retains historical authority and the transition fails in a controlled manner so the discrepancy can be reconciled explicitly.

## Authorization boundary

WEM-13 remains the sole authorization kernel for current authenticated staff/admin operations.

The operational path is conceptually:

`request integrity -> WEM_Authorization precondition -> canonical event -> guest serialization -> state reread -> transition validation -> atomic ledger append + projection update`

Ledger/domain persistence must not duplicate WordPress capability, actor-scope or event-scope authorization rules.

A guest that does not resolve through the WEM-13 canonical-event invariant produces no movement:

- zero `evento` terms: deny;
- multiple `evento` terms: deny;
- exactly one term: that numeric `term_id` may be captured in an accepted movement after authorization and transition validation.

Request event slugs, ticket values, page context and QR data are not historical identity or authorization.

## Movement identity and event reassignment

A movement permanently records both `guest_id` and the canonical `event_term_id` present when that movement occurred.

Historical records are never rewritten because an invitation is later reassigned.

Event reassignment is valid only while the guest is operationally `outside`. Reassignment while `inside` is invalid. A later movement after a valid outside-state reassignment records the new canonical event term while all earlier movements retain their original event term.

## Transition semantics

The operational state machine is:

- `checkin`: `outside -> inside`;
- `checkout`: `inside -> outside`;
- `reentry`: `outside_after_checkout -> inside`.

`reentry` is a first-class movement type and must not be rewritten as a generic second `checkin`.

A valid history may therefore contain:

`checkin -> checkout -> reentry -> checkout -> reentry ...`

Invalid transitions append nothing and change no projection.

## Atomicity

One accepted transition consists of exactly one ledger append plus the corresponding projection update.

They are a single database transaction:

1. begin transaction;
2. serialize/lock the operational guest;
3. reread the persisted current state inside that serialization boundary;
4. verify ledger/projection consistency required for the transition;
5. validate the requested state transition;
6. append exactly one immutable movement;
7. update the mutable projection;
8. commit.

Any error during append or projection update rolls the whole transaction back. WEM must never expose either a committed movement with stale projection or a projection mutation without its corresponding movement.

## Serialization, idempotency and concurrency

The serialization unit is the operational guest, not the browser request and not merely the event.

Every transition must serialize the guest before state reread, validation, append and projection update.

Concurrent requests therefore resolve against locked/reread state. If two requests race for a transition that can only be accepted once, at most one may commit. The other observes the updated state and is rejected as an invalid transition without appending another movement.

WEM-14 does not introduce a client-provided idempotency key. A future API/offline/public protocol may define one separately.

## Actor, source and decision

- `actor_user_id` is the durable WordPress user ID of the already-authorized operator. Display name is presentation only.
- `source` records the technical entry path and grants no authority. WEM-14 initially defines only `staff_web` for the authenticated WEM-13 operational surface.
- `decision` is `accepted` for every movement in this ledger.

The movement ledger contains accepted domain transitions only.

Rejected authorization attempts, invalid requests and invalid transitions are not movements. A future audit/security log may record them under a separate contract, privacy policy and retention model.

## New-installation-only boundary

WEM-14 does not reconstruct history.

No implementation in this story may:

- infer past movements from mutable post meta;
- fabricate deleted checkout history;
- create synthetic movements for unknown prior activity;
- backfill legacy installations;
- promise migration of historical guest movement data.

The movement ledger becomes authoritative for transitions recorded after its schema and runtime integration exist on a new installation.

Schema creation itself must be deterministic, idempotent and verifiable against a real WordPress database; that is not historical-data migration.

## Failure and reconciliation

Data disagreement is an integrity condition, not an invitation to autorepair.

When a transition detects ledger/projection inconsistency relevant to the requested operation:

- append nothing;
- mutate no projection;
- fail in a controlled manner;
- preserve evidence for explicit reconciliation.

Automatic history synthesis or silent projection correction is outside WEM-14.

## Public boundary and non-goals

WEM-14 does not widen the WEM-13 public boundary.

Out of scope:

- public invitation/token implementation;
- QR authorization or guest self-check-in;
- rejected-attempt audit/security logging;
- legacy history migration;
- automatic ledger/projection repair;
- complete WordPress role redesign.

Anonymous/public and legacy QR paths remain fail-closed until independently specified and verified.


## Implemented runtime shape

The WEM-14 implementation now consists of:

- `WEM_Movement_Schema` creating the dedicated `wem_guest_movements` table through the plugin activation path;
- `WEM_Movement_Ledger` exposing append/read persistence with canonical movement validation and UUID generation;
- `WEM_Movement_Service` owning guest-serialized transactional checkin, checkout, reentry and outside-only event reassignment;
- post-meta projection writes that distinguish a real write failure from WordPress's unchanged-value `false` return;
- rollback cache invalidation so post-rollback reads observe restored database state;
- AJAX operational integration that preserves the WEM-13 authorization and nonce preconditions before invoking the movement service.

Focused integration evidence covers schema installation/rerun, append/read persistence, canonical ledger validation, transaction rollback, drift detection, guest serialization, valid and invalid transition cycles, event reassignment history preservation, and authorized/rejected AJAX boundaries.

Final story closure still requires the WEM-30 full-suite, quality, HTTP/manual and CI/merge evidence. No statement in this section supersedes Jira execution evidence.
