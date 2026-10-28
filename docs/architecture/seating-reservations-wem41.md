# WEM-41 — Seating and individual reservations: approved MVP contract

Status: product-domain discovery approved for beta 2026-10-29. Jira WEM-41 comments 13850, 13853, 13855, 13858 and 13861 are decision evidence. This document authorizes bounded implementation stories I1–I6, not unverified runtime behavior.

## Invariants and authority

- Exactly one person and one seat per invitation; each valid guest belongs to exactly one canonical `evento` term.
- `wem_event_tables`: stable ID, canonical event term ID, positive capacity, display label and optional conceptual layout metadata. Layout is never reservation authority.
- `wem_table_reservations`: single authoritative association of guest and table, at most one active reservation per guest. Availability and member lists derive from active reservations, never browser counts, duplicate guest table metadata or table member lists. Enforce table/guest same event.
- Serialize concurrent reservations and use database transactions plus physical uniqueness defenses; recheck table capacity, eligibility, current reservation and event scope under lock. A failed or racing operation leaves no partial mutation; administrative reassignment locks affected resources in stable order.
- Confirmed reservations cannot be edited by their bearer holder. Only an authorized administrator may cancel or reassign, with append-only evidence of actor, operation, guest, old/new table, timestamp and reason/disposition.
- Participation cancellation is an explicit persistent ineligible state. It atomically releases the active seat with an audit record; every seat-selection attempt rechecks eligibility. Bearer rotation/reissue/revocation does not release seats, and participation cancellation does not automatically rotate/reissue/revoke a WEM-33 credential. Explicit credential revocation is a separate administrator action. Even a technically active bearer cannot reserve for an ineligible guest.
- Check-in/checkout/reentry and the WEM-14 ledger remain independent of seating.

## Public authorization and privacy

- Preserve WEM-33's existing own-guest minimal read-only projection. A **separate** least-privilege seating boundary resolves an active WEM-33 bearer to its guest and canonical event, then authorizes only availability, same-event member projection and that eligible guest's initial reservation. It never grants staff operations, cross-event guest listing, admin correction or check-in.
- Public event table projection: table label, capacity, occupied/free count, and one label per active reservation. With explicit affirmative, revocable visibility preference attached to the canonical guest + event, render `Nombre A.` **only** if independently approved canonical structured first-name/surname data exists and is validated. Otherwise render `Integrante privado`, including when consent is absent, withdrawn, or the identity source is ambiguous/incomplete. The current `wem_nombre` is a free-text complete name, not an approved structured surname source: never infer a surname by splitting/counting its words or use `post_title`/ticket metadata. Without a verified structured source, all public member labels remain private; the visible-name capability is explicitly deferred functional debt, not an implemented acceptance. No new identity authority, column, or migration is authorized in I3/T4. Consent defaults to false, does not change occupancy and is not tied to bearer generation/table. The reservation confirmation UI offers a discreet clear unchecked opt-in; consent withdrawal remains available without editing the reservation.
- Administrator-only bounded view may show full members when independently authenticated/authorized. Public responses must exclude IDs, ticket, contact, organization, private notes, check-in state, audit and bearer values. Enforce event isolation, restricted queries, no-store/no-referrer/noindex, escaped output, secret-safe logs, and abuse/rate-limiting assessment. Security tests cover absent/invalid/rotated/revoked bearer, canceled participation, cross-event lookups, no consent and withdrawal.
- MVP UI is in Spanish: a responsive table grid or conceptual arrangement showing availability and permitted members. No architectural measurements, drag-and-drop, QR scanner integration or WhatsApp delivery.

## CSV compatibility: versioned single authority

Current WEM-12 legacy CSV header is `titulo,nombre,organizacion,mesa,evento,observaciones,checkin`. Existing `includes/class-import-export.php` imports `mesa` directly into `wem_mesa` and exports that post meta. **That legacy free-text value is not a seat reservation**.

- Continue accepting `mesa` on historical guest import solely as reconciliation data, never creating/updating active reservations. Warn operators explicitly that importing `mesa` does not assign seats.
- The new semantic field is `table`; when a modern export is introduced it is derived exclusively from the active structured reservation, never `wem_mesa`. Do not implement two new exporters merely for beta.
- Any administrative bulk table assignment must validate identity, event, capacity, permission and conflicts through the same transactional reservation service. No direct post-meta-to-reservation synchronization and no silent auto-migration; reconcile old values explicitly with reports before authorized assignments.
- Preserve the historical CSV ingestion contract and regression coverage, add explicit format/semantic distinctions and tests before changing import/export behavior. Do not silently reinterpret imported historical `mesa` as canonical seating.

## Approved bounded delivery and acceptance

I1. **Persistence and capacities** — dedicated tables, consent/eligibility and append-only audit schema, installation/rerun and event/unique constraints (RED/GREEN).
I2. **Transactional reservations** — initial single-seat reservation, concurrency, overcapacity/duplicate/cross-event rejection, admin cancellation/reassignment and atomic participation cancellation service (RED/GREEN).
I3. **Public projection and privacy** — separate bearer-scoped read and initial-reservation authorization, consent persistence/revocation, minimized members and negative security tests (RED/GREEN).
I4. **Spanish minimal selector** — responsive table grid, member labels, confirmation opt-in, conflict/empty/full/denied UI and accessibility walkthrough (RED/GREEN + manual acceptance).
I5. **Administrative management** — table CRUD/capacity, bounded full member view, authorized correction/cancellation with audit and CSV semantic compatibility (RED/GREEN).
I6. **Integrated acceptance** — synthetic multi-guest and concurrent tests, privacy/HTTP regression, clean installation ZIP, full applicable gates, real UI walkthrough, exact-SHA CI, Jira/handoff and release reconciliation.

Dependencies: I1 → I2 → I3 → I4; I5 after I2 (and I1); I6 after I3/I4/I5. WEM-35 owns general guest/event admin UX and explicitly excludes seating; WEM-37 owns release baseline. Do not reopen WEM-33/34/73/72.

## Evidence and handoff

Use AGENTS.md and docs/agent-harness.md. Distinct Jira RED and GREEN observations must include exact commit SHA, focused command, expected failure/success, environment and next checkpoint. Candidate, PR, merge, docs and CI have separate identities; rerun only invalidated applicable gates. Do not claim implementation until it exists in source and passes its gates.
