# WEM-13 — Staff event-scoped authorization contract

Status: **approved and frozen before runtime implementation** (2026-10-05).
Jira: WEM-13. This contract replaces no WEM-11 public fail-closed guard by itself.

## Purpose

WEM-13 authorizes operational guest reads and check-in state transitions for WordPress staff while preserving public deny-all behavior. Possessing or scanning an invitation identifies an invitation but never grants authority to operate check-in.

## Actors

### Unauthenticated visitor

Has no WordPress identity and no operational authority.

- May not list guests.
- May not read guest-specific operational data.
- May not mutate check-in state.
- Public WEM shortcodes and AJAX remain fail-closed during WEM-13.

### Authenticated user without WEM capability

Authentication alone grants no WEM authority.

- May not list guests.
- May not read guest-specific operational data through WEM operational surfaces.
- May not mutate check-in state.

### Event staff

A WordPress-authenticated user with the required WEM capability and an explicit set of authorized `evento` term IDs.

Authorization requires both:
1. the operation-specific WEM capability; and
2. membership of the guest's canonical event `term_id` in the user's authorized event scope.

View and operate capabilities are distinct. Read authority does not imply mutation authority.

### Global administrator

Has global WEM event scope, but does not bypass resource invariants.

A global administrator may operate across events only when:
- the target is a valid `invitado`;
- the guest resolves to exactly one canonical `evento` term;
- the requested state transition is valid;
- any applicable CSRF requirement is satisfied.

## Canonical event scope

An operational guest must belong to **exactly one** `evento` taxonomy term.

- zero event terms: deny;
- more than one event term: deny;
- exactly one event term: that term's numeric `term_id` is the canonical authorization scope.

Event slugs are routing/presentation inputs only. A request-supplied slug, page context, shortcode attribute or filter may not override the guest's canonical event scope.

The server must derive the resource event from the guest before authorizing guest-specific reads or mutations.

## Authorization chain

Sensitive server operations follow this conceptual sequence:

`identity → operation capability → resource → canonical event → actor event scope → request-integrity checks → operation invariants`

No individual request datum grants authority.

Specifically, none of the following authorizes by itself:

- WordPress authenticated state;
- `post_id`;
- WordPress nonce;
- ticket value;
- event slug;
- shortcode/page context;
- QR scan.

A nonce is a CSRF/request-integrity mechanism, not authentication or authorization.

## Operational capabilities

WEM-13 separates at least two authority classes:

- **view event guests** — list/read the bounded operational guest view for authorized events;
- **operate event guests** — perform allowed check-in state transitions for authorized events.

Exact WordPress capability identifiers are implementation details of WEM-13 T2, but their semantic separation is frozen here.

User-to-event assignment must be exposed through a WEM-owned scope API. Initial persistence may use WordPress user metadata, but consumers must not authorize by reading storage directly.

## Sensitive surfaces

### Guest listing

`wem_list_ajax` and `[wem_list]` are staff/admin event-scoped surfaces.

Requirements:

- authenticated WEM actor;
- valid view capability;
- explicit valid event context for list operations;
- event within actor scope;
- server query constrained to that event before search/filter criteria are applied.

Anonymous/nopriv listing remains fixed deny-all.

### Guest state mutations

Check-in, checkout and re-entry are staff/admin event-scoped operations.

Requirements:

- authenticated WEM actor;
- operate capability;
- valid guest resource;
- guest has exactly one event;
- canonical event is within actor scope, or actor has global scope;
- valid nonce/CSRF protection;
- valid state transition.

Anonymous/nopriv mutation remains fixed deny-all.

### Private observations

`wem_observaciones` is admin-only in WEM-13.

Operational staff listing and staff-facing guest views must not disclose private observations.

### Public invitation and QR surfaces

All public guest-facing WEM surfaces remain fail-closed throughout WEM-13.

The future public credential contract is frozen only at the architectural level:

- opaque;
- non-enumerable;
- bound to one invitation;
- never equivalent to staff authority.

WEM-13 does **not** implement that token, a public invitation view, token lifecycle, secure/local QR generation or guest-driven check-in. Legacy QR shortcodes remain fail-closed.

## Denial and isolation requirements

Authorization must fail closed when identity, capability, resource, event cardinality or scope is invalid.

Cross-event access must not be recoverable by manipulating:

- guest IDs;
- event slugs;
- query text;
- table filters;
- tickets;
- nonces.

Denied mutation requests must leave check-in/check-out state, timestamps and operator data unchanged.

Responses for unauthorized guest-specific requests should avoid unnecessary disclosure about whether a guest exists outside the actor's scope.

## Global administration and existing admin tools

WEM-13 does not redesign WordPress roles or granular import/export permissions.

Existing global administrative guest management remains privileged. The new event-scoped operational authorization model must not implicitly grant staff access to global CSV import/export or private observations.

## Explicit non-goals

Out of WEM-13 scope:

- public opaque-token implementation;
- public invitation view;
- secure/local QR implementation;
- credential issuance, rotation or revocation lifecycle;
- guest/self check-in;
- append-only movement ledger (WEM-14);
- complete WordPress role redesign;
- granular import/export permission redesign;
- historical migration;
- legacy compatibility.

## WEM-11 relationship

WEM-11 remains the active public security boundary until specific WEM-13 staff/admin paths are implemented and verified.

WEM-13 may relax deny-all only for authenticated, capability-checked, event-scoped staff/admin behavior covered by its tests. Public AJAX/shortcodes and legacy QR remain fail-closed at WEM-13 closure.
