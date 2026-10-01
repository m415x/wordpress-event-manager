# WEM-11 — Safe bootstrap and fail-closed registration contract

Status: **approved and frozen before the first integration RED** (2026-10-01).
Jira: [WEM-11](https://trail-running-workout-planning.atlassian.net/browse/WEM-11). Dependency: WEM-11 blocks WEM-10; [WEM-13](https://trail-running-workout-planning.atlassian.net/browse/WEM-13) owns event-scoped authorization.

## Approved product/security boundary

**All sensitive guest-facing interfaces remain fail-closed until WEM-13 is implemented and verified.** Correct plugin activation, class loading and hook registration do **not** imply permission to expose guest data or mutate guest state. The policy applies to authenticated and unauthenticated visitors alike, including site administrators: no interim privileged bypass.

- Registered guest AJAX actions `wem_checkin_ajax` and `wem_list_ajax` must reject before nonce checks, guest ID resolution, queries, rendering or writes. Use the standard WordPress JSON error response with HTTP **403**, without leaking guest-specific fields or identifiers. Both `wp_ajax_*` and `wp_ajax_nopriv_*` handlers are subject to this guard.
- Registered shortcode callbacks `wem_checkin` and `wem_list` must return fixed safe unavailable output **before** reading request ticket parameters or post metadata; no guest-specific output or query is allowed. No interim content determined by the user's ticket/event.
- QR shortcodes `wem_qr_table` and `wem_qr_single` must also remain unavailable until their ticket/credential exposure is reviewed; do not render predictable invitation links or call external QR services in this interim state.
- These guards are temporary *deny-all* safety boundaries. They are not a substitute for WEM-13's future identity, roles, event scoping and authorization model. Any future relaxation requires separately approved WEM-13 tests and a revised security contract.
- Administration hooks may be wired for isolated activation and admin UI checks, but do not use real guests, credentials or production databases. Avoid enabling sensitive public routes through side effects of registration.
- No old-brand identifiers, upgrade compatibility aliases, database migrations, unrelated CSV behavior changes or movement-history redesign.

## Bounded RED/GREEN sequence

1. **Bootstrap-class RED:** in the dedicated WordPress test environment, load the actual neutral plugin PHP entrypoint while WordPress is initialized, and assert that every expected WEM module class resolves with the plugin's own autoloader. WordPress itself must load successfully first; the RED must be an ordinary PHPUnit assertion on unresolved classes, not a PHP fatal or environmental failure. **Only after observed RED**, fix underscore-to-hyphen filename mapping.
2. **Lifecycle registration RED:** separately verify startup and hooks: CPT `invitado`, taxonomy `evento`, administrative metaboxes/columns/import menu, the four shortcodes and both logged-in and logged-out AJAX registrations exactly once. Test activation through WordPress APIs in an isolated disposable environment; avoid counting repeated callbacks from multiple test fixtures as actual plugin behavior.
3. **Fail-closed RED:** explicitly assert unauthenticated and authenticated AJAX actions reject with HTTP 403 and do not read/write guest metadata; confirm all four shortcode callbacks do not query, expose guest data or render QR links. Implement guard before any public handler registration can be considered complete. Ensure security tests execute in the isolated WP environment.
4. **Closure:** confirm real activation and relevant registrations in a disposable WordPress walkthrough, focused tests, unit/WordPress integration gates, syntax, PHPCS, PHPStan and CI. Do not claim functional or public secure check-in while WEM-13 is pending. WEM-10 stays blocked until WEM-11 evidence is complete.

## Source-of-truth caveats

The existing integration bootstrap loads WordPress's test framework and deliberately avoids automatically loading the plugin. Its earlier metadata test and CI GREEN do **not** establish plugin activation. WEM-11's new integration tests should explicitly load the entrypoint **only in the test needing activation**, preserving independent metadata coverage and a deterministic error classification. WEM-8's new-installations-only breaking identity remains binding.
