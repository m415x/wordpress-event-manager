# WEM-32 — Staff capability provisioning contract

Status: **frozen for implementation** (2026-10-07).
Jira: WEM-32. This contract provisions WEM-13 staff capabilities without replacing the WEM-13 authorization kernel.

## Purpose

WEM-32 makes staff onboarding operable on new installations without requiring ad-hoc capability manipulation. Provisioning is an administrative mechanism only. Runtime authority remains capability-based and event-scoped.

## Authority model

WEM business authorization must never depend on a WordPress role name or a WEM preset name.

The WEM-13 runtime authority remains:

`authenticated user -> required WEM capability -> canonical guest event -> authorized event scope`

Global administration remains the existing WEM-13 exception:

`manage_options -> global WEM event scope`

The global-admin path still requires all WEM-13 resource and transition invariants.

## Canonical provisioning states

WEM administratively manages exactly three canonical states:

- **None**: neither `wem_view_event_guests` nor `wem_operate_event_guests`.
- **Viewer**: `wem_view_event_guests` only.
- **Operator**: both `wem_view_event_guests` and `wem_operate_event_guests`.

These are provisioning presets, not authorization identities. Applying a preset converges the two WEM capabilities to the selected canonical state.

WEM must not add these capabilities to WordPress built-in roles. Existing roles such as subscriber, editor or administrator are not replaced or silently rewritten.

## Non-canonical external state

A user can be modified by WordPress, another plugin, code or an administrator outside the WEM provisioning API.

If the effective WEM capability combination is not one of the three canonical states — for example `wem_operate_event_guests` without `wem_view_event_guests` — WEM must detect and report a non-canonical state.

Detection must not silently normalize, grant or revoke capabilities.

An explicit administrative action applying None, Viewer or Operator may converge that user to the selected canonical state.

Runtime authorization continues to evaluate actual capabilities. A preset/status projection never grants authority independently.

## Coexistence with WordPress roles and capabilities

Provisioning mutates only the two WEM operational user capabilities owned by this contract.

It must preserve:

- the user's existing WordPress role or roles;
- built-in capabilities;
- unrelated custom capabilities;
- capabilities owned by other plugins.

WEM-32 does not introduce an arbitrary role builder and does not make Viewer/Operator replacement WordPress roles.

## Event scope separation

Event scope remains independently persisted and administered only through:

`WEM_Authorization::set_authorized_event_ids()`

No WEM-32 UI or provisioning service may write `wem_authorized_event_ids` directly.

Scope alone never authorizes. Capability alone never provides cross-event authority.

Revoking or downgrading a capability must affect authorization immediately even if event scope remains persisted. Persisted scope may remain after capability revocation.

## Lifecycle

WEM-32 must verify:

1. **User creation** — an ordinary new WordPress user receives no WEM capability automatically.
2. **Grant Viewer** — adds view only and preserves all unrelated role/capability state.
3. **Viewer to Operator** — adds operate while retaining view and scope.
4. **Operator to Viewer** — removes operate immediately while retaining view and scope.
5. **Revoke to None** — removes both managed WEM capabilities; persisted scope does not authorize.
6. **Deleted user** — provisioning and scope do not survive as usable WEM authority for a missing user.
7. **Non-canonical state** — detected without silent normalization; an explicit preset application may converge it.

Repeated explicit application of a canonical preset must be idempotent.

## Activation, reactivation and deactivation

WEM-32 does not require a provisioning installer merely because provisioning exists.

Activation and reactivation must not grant, revoke or rewrite any user's WEM provisioning. Deactivation must not remove capabilities, roles or rewrite users.

If implementation discovers a real persistent/versioned artifact that requires installation or upgrade handling, work must stop and that need must be justified before adding an installer or version state.

## Administrative surface

The existing WEM user-profile administration is the bounded integration point.

Only an administrator satisfying the existing administrative boundary may edit WEM provisioning and event scope.

The UI may present None / Viewer / Operator and authorized events together, but their writes remain separate:

- provisioning goes through the WEM provisioning API;
- event scope goes through `WEM_Authorization::set_authorized_event_ids()`.

A detected non-canonical state must be surfaced rather than silently converted merely by rendering or saving unrelated profile data.

## WEM-13/WEM-14 compatibility

WEM-32 must not widen public access, bypass event scope or alter movement semantics.

Viewer remains read-only. Operator may invoke WEM-13-authorized transitions only within scope. Anonymous/public invitation and legacy QR paths remain fail-closed.

The WEM-14 movement ledger continues to record only accepted transitions from already-authorized operators.

## Existing CPT administration debt

`includes/class-cpt-manager.php` currently uses `administrator` as a role-like capability check and in the `invitado` CPT capability map.

That is not part of WEM-32 operational provisioning and must not be reused by this implementation. Redesign of generic guest administration/CPT capabilities remains WEM-35 scope.

## Explicit non-goals

- Changing WEM authorization to role-based checks.
- Granting WEM capabilities to built-in WordPress roles.
- Replacing a user's WordPress role.
- Redesigning the `invitado` CPT capability model.
- Granular import/export authorization.
- Public invitation/token/QR access.
- Legacy role migration.
