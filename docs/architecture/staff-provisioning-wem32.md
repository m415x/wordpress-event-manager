# WEM-32 — Staff capability provisioning contract

Status: **frozen for implementation** (2026-10-07).
Jira: WEM-32. This contract provisions the operational capabilities already enforced by WEM-13; it does not replace the WEM-13 authorization kernel.

## Authority model

WEM operational authority remains capability-based.

Business rules must not authorize by:
- WordPress role name;
- WEM preset name;
- persisted preset metadata;
- event scope alone.

Effective scoped staff authorization remains:

`authenticated user -> required WEM capability -> canonical guest event -> authorized event scope`

The existing `manage_options` branch remains WEM-13's global-administrator boundary and still does not bypass guest/event/transition invariants.

## Canonical provisioning presets

WEM manages three canonical provisioning states:

- **None** — neither WEM operational capability;
- **Viewer** — `wem_view_event_guests`;
- **Operator** — `wem_view_event_guests` plus `wem_operate_event_guests`.

These presets are only a provisioning mechanism. Runtime authority continues to be determined from the effective capabilities through `user_can()`.

WEM provisioning must coexist with the user's existing WordPress role or roles and all non-WEM capabilities. Applying or revoking a WEM preset must not silently replace roles such as `subscriber`, `editor` or roles supplied by other plugins.

No WEM operational capability is automatically granted to any built-in WordPress role.

## Non-canonical external states

Users may be modified by code outside WEM. A combination that does not map to None, Viewer or Operator is **non-canonical**.

Example: `wem_operate_event_guests` present without `wem_view_event_guests`.

WEM must detect and surface a non-canonical state without silently normalizing it. Merely reading/rendering provisioning state must not mutate capabilities.

An explicit administrator action applying None, Viewer or Operator may converge the WEM-managed capabilities to that canonical state.

## Event scope

Event scope remains independent from capability provisioning.

All WEM scope writes must continue through:

`WEM_Authorization::set_authorized_event_ids()`

Rules:
- scope without the required capability never authorizes;
- capability without matching event scope never authorizes scoped staff access;
- capability revocation takes effect immediately even if authorized event IDs remain persisted;
- changing a preset does not implicitly rewrite event scope;
- `manage_options` retains the existing WEM-13 global event scope behavior.

## Lifecycle

### User creation

A new ordinary WordPress user starts with no WEM authority. Creation must not imply Viewer or Operator from the user's WordPress role.

### Grant Viewer

Explicitly applying Viewer converges the two WEM-managed capabilities to view=yes, operate=no while preserving roles, non-WEM capabilities and event scope.

### Grant Operator

Explicitly applying Operator converges to view=yes, operate=yes while preserving roles, non-WEM capabilities and event scope.

### Viewer / Operator changes

Operator -> Viewer removes operate authority immediately and preserves view authority and event scope.

Viewer -> Operator adds operate authority without rewriting event scope.

### Revoke

Applying None removes both WEM operational capabilities. Persisted event scope may remain, but it grants no authority.

### Deleted user

WEM does not transfer or retain operational authority for a deleted WordPress user. Existing scope APIs already reject nonexistent user IDs; no WEM identity-reuse mechanism is introduced.

## Activation, reactivation and deactivation

WEM-32 introduces no mandatory provisioning installer.

Because the approved presets are direct user capabilities and no new role or other versioned persistent provisioning artifact is required, plugin activation/reactivation must not grant, revoke, normalize or otherwise rewrite user provisioning.

Deactivation must not remove WEM capabilities, delete event scope or rewrite users.

If implementation discovers a real requirement for persistent schema/versioned provisioning state, work must stop and that requirement must be justified before adding an installer or migration.

## Administrative surface

The existing WEM user-profile administration is the bounded surface for provisioning and event-scope assignment.

It may expose None / Viewer / Operator, but:
- saving a preset must use a WEM-owned provisioning API;
- saving event scope must use `WEM_Authorization::set_authorized_event_ids()`;
- a non-canonical external state must be visible and must not be normalized without an explicit preset choice;
- Viewer/Operator do not gain global CSV import/export, private-observation administration or generic CPT administration by virtue of the preset.

## Existing CPT administration debt

`includes/class-cpt-manager.php` currently uses the literal `administrator` as a role-like capability check for menu visibility and the `invitado` CPT capability map.

This is not a WEM-32 blocker because it governs the generic administrative CPT surface, not WEM-13 operational authorization. WEM-32 must not expand that pattern or implicitly grant Viewer/Operator CPT administration authority.

The debt remains tracked in WEM-35 for an explicit administrative capability/UX decision.

## Verification boundary

WEM-32 must cover:
- canonical and non-canonical provisioning-state detection;
- explicit None / Viewer / Operator convergence;
- creation, grant, Viewer <-> Operator, revoke and deleted-user lifecycle;
- role/non-WEM-capability preservation;
- scope/capability independence and immediate revocation;
- unchanged `manage_options` global boundary;
- WEM-13/WEM-14 operational regressions using real provisioning;
- activation/reactivation/deactivation non-mutation;
- public/anonymous fail-closed behavior.

T1 is contract/documentation work and deliberately has no artificial RED.
