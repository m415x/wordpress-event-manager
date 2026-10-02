=== WordPress Event Manager ===
Contributors: m415x
Tags: events, event management, guests, check-in, invitations, csv
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 2.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress plugin under modernization for event guests, check-in and invitation workflows. This Foundation branch is not production ready.

== Description ==

WordPress Event Manager (WEM) is being developed for **new installations only**. Its current PHP source contains modules for an `invitado` custom post type, an `evento` taxonomy, CSV import/export, check-in interfaces and QR-related shortcodes.

**Current status:** The Foundation build has PHPUnit unit and isolated WordPress integration tooling, but has not passed plugin activation, end-to-end check-in, permission or security acceptance tests. Do not deploy it to production or expose its guest-management endpoints on a publicly accessible site.

== Compatibility ==

This is a deliberate breaking change to the earlier plugin identity. No existing guest records, post metadata, pages containing old shortcodes, activation state or configuration are migrated. Do not install it as an upgrade over a previous release.

The canonical WordPress plugin file is `wordpress-event-manager.php` inside the `wordpress-event-manager` directory. The plugin text domain is `wordpress-event-manager`.

== Current source interfaces ==

The following shortcode names are registered by methods in the codebase, but their execution has **not yet been verified end to end in WordPress**:

- `[wem_checkin event="event-slug"]` — proposed guest check-in screen.
- `[wem_list event="event-slug"]` — proposed guest list.
- `[wem_qr_table event="event-slug" start="1" end="100" columns="4" size="150"]` — proposed QR table.
- `[wem_qr_single event="event-slug" ticket="50" size="200"]` — proposed individual QR image.

The current QR implementation constructs images through an external Google Charts URL; it is **not** an offline or locally generated QR implementation. Its availability is not guaranteed.

The existing CSV code expects these column headings:

`titulo,nombre,organizacion,mesa,evento,observaciones,checkin`

Those headings are ordinary data field names. Internally the canonical post metadata names use the `wem_` prefix.

== Installation and testing ==

This branch is for development and testing only. Use an isolated disposable WordPress installation, not a site with real guest data.

Development instructions and runnable checks are documented in `docs/testing.md` and `AGENTS.md`. The dedicated environment uses `.wp-env.test.json`; the local TDD runner can be invoked with:

`pnpm tdd tests/Unit/PluginEntrypointTest.php`

Unit tests that inspect source files and WordPress tests that inspect plugin metadata do not establish that activating the plugin or operating its endpoints is safe.

== Security and known limitations ==

- Module autoloading and hook initialization have known historical gaps pending independent verification and remediation.
- Existing guest-list and check-in AJAX paths have unresolved authorization issues. WordPress nonces must not be treated as user authorization.
- Ticket references in the current source are predictable, and check-in state updates are destructive rather than append-only audit events.
- Access control, event-scoped permissions, QR security and guest movement history require dedicated implementation and tests.

Track current acceptance criteria and remediation through the WEM Jira project.

== Changelog ==

= Foundation (in progress) =
- Adopted the neutral WordPress Event Manager identity and new-installation-only contract.
- Introduced PHPUnit, scoped static checks and a reproducible CI baseline.
- Source-level naming changes are under verification; no production release is claimed.

== Support and source ==

Repository: https://github.com/m415x/wordpress-event-manager

Report development issues using the repository's issue tracker. No production support commitment is made for this Foundation branch.
