# WEM-75/T1 — Transactional seating reservation protocol (approved scope)

Source: WEM-41 and `docs/architecture/seating-reservations-wem41.md`. Scope: domain service only; no public bearer endpoint, administrative UI or WEM-72 harness changes.

## Physical authority
- `wem_event_tables(id,event_term_id,capacity)` is the table/seat-capacity authority.
- `wem_table_reservations(guest_id,table_id,event_term_id,status,active_slot)` is the single authoritative occupancy ledger. A reservation counts only when `status='active' AND active_slot=1`. `UNIQUE(guest_id,active_slot)` disallows two active rows per guest. Composite FK `(table_id,event_term_id)` forbids cross-event reference. Neither index by itself enforces capacity.
- `wem_seating_participation` is explicit cancellation state, not the sole proof of eligibility; the canonical guest's actual `evento` membership must be validated under transaction.
- `wem_seating_admin_audit` is append-only history once the authorized domain writer exists. No partial or duplicate audit rows.

## One global lock protocol for I2
On one InnoDB connection: START TRANSACTION; acquire canonical guest identity (persistent WordPress post row) lock using SELECT ... FOR UPDATE in ascending guest ID; for each affected table acquire `wem_event_tables` row locks using SELECT ... FOR UPDATE in ascending numeric table ID; then read current guest active reservation, canonical `evento` term identity, participation state, effective table capacity, and active occupancy inside that same transaction, under the held locks. Administrative operations involving multiple guests acquire guest locks first in ascending ID, followed by all relevant table locks in ascending ID. After acquiring locks, revalidate all identities and capacities; do not trust a pre-transaction snapshot or a client table count. For modifications where an affected table is only discoverable after reading the guest reservation, obtain a tentative table ID before locking (without authorizing mutation), then lock and re-read; if the set changes, roll back and retry from the beginning under the same order. Never acquire a new lower-ranked lock out of order.

The locking table rows serialize all cooperating writers targeting the same capacity and all cooperating writers for one guest. Direct writes outside the domain service are prohibited. A previous active reservation implies initial reservation DENY; only server-authorized administrator workflows may cancel/reassign. The transaction owns the complete mutation, including any append-only audit entry.

## Errors, commits and retries
SQL errors, lock acquisition failure, deadlock (1213 / SQLSTATE 40001), timeout (1205), uniqueness/FK failure, post-insert callback exception or failed audit INSERT lead to immediate ROLLBACK on the same connection. COMMIT occurs only once, after every write and invariant succeeds. An error cannot be turned into success because rollback itself fails; mark the connection/operation unavailable and fail closed. Never issue COMMIT on the exception path.

Retry only deadlock/lock-timeout errors, with a hard maximum of **two additional attempts** after the initial attempt, each using a fresh transaction and rechecking authorization, eligibility and capacity. No retry after a successful COMMIT, no retry on unknown commit outcome, ordinary SQL validation failure, or a deterministic injected exception. Return a controlled busy/unavailable result after the limit; caller may initiate a new request. Ensure neither duplicate active reservation nor duplicate audit row can be produced by retries.

## T1 RED acceptance
Run `tests/Integration/SeatingReservationTransactionTest.php` in a dedicated disposable real InnoDB database/WordPress environment, never a shared contaminated PHPUnit database. The test must verify: a successfully executed reservation INSERT inside an opened transaction; a controlled induced failure after that INSERT and before COMMIT; reservation no longer exists after rollback; administrative audit has no partial row; active occupancy and derived free capacity equal before snapshot; COMMIT never occurs after failure. A test that rejects before the INSERT is invalid. Verify the test harness does not wrap this real COMMIT/ROLLBACK in the normal `WP_UnitTestCase` outer transaction; an environment error is ERROR rather than behavioral RED.

## Scope boundary
T1 establishes mechanics. T2 verifies eligibility/initial seat semantics; T3 stresses independent-connection races; T4–T6 add authorized administrative domain writers, reusing this protocol. WEM-76 owns the public bearer adapter and projections; WEM-77 visual UI; WEM-78 administrative screens/CSV; WEM-72 general-purpose PHPUnit isolation.
