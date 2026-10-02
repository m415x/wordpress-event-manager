# WEM-9 — Version 2.1.1 to 2.2.1 source reconciliation

Status: **source comparison reconciled; independent follow-ups approved and recorded** (2026-10-01). This document establishes no functional activation GREEN.
Parent: WEM-2; issue: [WEM-9](https://trail-running-workout-planning.atlassian.net/browse/WEM-9).

## Evidence and method

- Earlier baseline: the user-provided **v2.1.1 monolithic code snapshot** in `mapeo_proyecto(1).txt` (1,270 text lines, containing a PHP plugin, a mapping utility and a historical readme). It is **not** the current Git repository baseline; its snapshot provenance and exact historical identifiers belong in Jira evidence, not modern maintained source.
- Later baseline: repository `main@74116d06ccf40f3316cee7a33b034df45f8efa7b`, historically identified as **v2.2.1**. Current comparison target: the modular PHP files on `feat/WEM-2-foundation` after WEM-8's approved neutral naming migration. Consequently, differences in identifiers reflect the intentional **new-installation-only breaking change**, not evidence of an original v2.2.1 regression.
- Verification method: static inspection of both source sets, their registration/call sites, and the actual WordPress integration-test scope. **None of the row-level functional behaviors below has been demonstrated end-to-end** by an activated plugin; classification is based on code, not runtime parity.
- Definitions: **preserved** means an equivalent source path/data intent exists (not that it executes); **changed** means source behavior or contract differs; **added** means not found in the earlier snapshot; **removed** means no counterpart was found; **documentation-only** means an assertion from documentation is not the effective code contract.

## Source comparison matrix

| Area | Classification | Evidence-based finding | Implementation location / consequence |
| --- | --- | --- | --- |
| Guest content type and event taxonomy | Preserved in source | Both define `invitado` and `evento`, with the guest admin screen and event association. | v2.1.1 PHP registration near source lines 27–100; current `includes/class-cpt-manager.php` and `class-taxonomy-manager.php`. Registration in current runtime is not certified. |
| Guest metadata and metabox | Preserved intent; changed identity | Guest name, organization, table, observations, event assignment and ticket metadata remain conceptually represented. After WEM-8, storage keys and form identities use `wem_*`; there is deliberately no migration of old data. | `includes/helpers.php`, `class-metabox-manager.php`. Fresh installations only; no backward compatibility. |
| Admin columns and filters | Preserved in source | Guest/ticket, event, organization, table, current check-in indicators and filters appear in both implementations. | `includes/class-admin-columns.php`; its hook-registration method is not called by the current entrypoint. |
| Shortcodes for guest check-in and searchable list | Preserved intent; changed identity | Both have guest lookup and listing markup; WEM-8 deliberately uses `wem_checkin` and `wem_list`. Historical embedded content is not accepted as an upgrade path. | `includes/class-shortcode-manager.php`; new shortcode registration is not currently wired to WordPress. |
| Check-in, checkout and re-entry | Preserved behavior shape; preserved debt | The same three operations manipulate current-state metadata, times and operator fields; checkout/re-entry delete or replace portions of the prior state. No append-only movement history is present in either source. | Earlier AJAX handler in snapshot near lines 546–611; current `includes/class-ajax-handler.php`. Not a verified audit trail. |
| AJAX list and guest search | Preserved source logic; changed identity | Both search by ticket/name/organization, filter by table/event and render an interactive table; each contains hooks for both authenticated and unauthenticated requests. | Snapshot near lines 765–864; current `class-ajax-handler.php`. Public registration and authorization are separate security concerns. |
| CSV header `titulo` vs README `ticket` | Documentation-only inconsistency in 2.1.1 | The earlier source import form and parser require `titulo` and its exporter emits `titulo`, although one section of its own readme says `ticket`. The modular parser/exporter also use `titulo`. | Earlier snapshot near lines 888, 931–942, 1041–1058 and readme near 1201; current `class-import-export.php`. **Do not mislabel this as a new regression.** |
| Import CSV separators, BOM, guest lookup by title+event, export | Preserved in source | Comma, semicolon, pipe and tab, UTF-8 BOM treatment, finding existing guests by title/event, and seven exported columns are present in both. | Snapshot near lines 888–1060; current `class-import-export.php`. No imported fixture walkthrough yet. |
| Import result counters | **Changed — apparent regression** | The earlier importer increments `updated` on the existing-post replacement branch. The modular `process_single_row()` always returns `created` when it receives a truthy ID, even if `find_or_create_invitado()` returned an existing ID; therefore its `updated` count cannot reflect that case. | Current `includes/class-import-export.php`, `import_csv_data()`, `process_single_row()`, `find_or_create_invitado()`. Keep this as a bounded future fix with an automated fixture; do not silently alter in WEM-9. |
| Pre-import character-encoding conversion | **Added** | The modular importer attempts to normalize input as UTF-8 using encoding detection and conversions. Earlier code handled BOM but had no comparable raw-file encoding-conversion pipeline. | Current `class-import-export.php`, `process_csv_import()`, `wem_force_utf8()`. Its effectiveness and edge cases have not been tested. |
| QR image/table shortcodes | **Added** | The monolithic snapshot only describes using external QR generation for ticket URLs; no image/table shortcode implementation was observed there. The modular code adds QR image/table shortcodes using an external Google Charts image URL. | Current `includes/class-qr-generator.php`. QR generation is **not native/offline**; the external dependency has not been availability-tested. |
| File/architecture organization | **Changed** | Earlier code registers hooks directly at top level of one PHP plugin file. Modular code separates eight classes, helpers and assets, and instantiates classes on `plugins_loaded`. | Current `wordpress-event-manager.php` plus `includes/` and `assets/`. This source rewrite introduces activation/wiring risks. |
| Plugin activation / autoload | **Changed — apparent regression / blocker** | The modular autoloader lowercases class suffixes but leaves underscores in filenames, whereas the tracked class modules have hyphenated filenames. Loading those classes can therefore fail before their methods run. The earlier monolith had no such class-file autoload dependency. | Current `wordpress-event-manager.php` autoloader; historical local WordPress bootstrap failure recorded in WEM-3. Not fixed here. |
| Module hook initialization | **Changed — apparent regression / blocker** | Only the CPT and taxonomy class constructors attach hooks. The other instantiated modules expose `register_*` / `setup_columns` methods which the entrypoint does not call. Thus admin columns, metaboxes, AJAX actions, shortcodes and import/export menu registration cannot be inferred to be active. | Current entrypoint and respective `includes/class-*.php`. The earlier monolith registered these at file scope. |
| Plugin identity, metadata, labels and entrypoint | **Changed intentionally** | WEM-8 established the neutral WEM naming contract with no upgrade aliases. This is approved for **new installations only**. | `docs/architecture/naming-contract.md`, WEM-8 Jira evidence and global source identity test. |
| Full movement history, native QR, complete security | **Documentation-only / unsupported** | The historical readmes made strong product claims which neither snapshot proves. Checkout overwrites state, QR rendering requires an external URL in the modular implementation, and nonce verification is not a capability check. | `readme.txt` was rewritten in WEM-8 to describe verified source boundaries rather than market complete functionality. |
| Legacy monolithic entrypoint and identifiers | Removed intentionally from current surface | Original entrypoint, old hooks/shortcodes/meta key names and original branding are absent under WEM-8's approved breaking-compatibility policy. | This is not a candidate for reintroduction through compatibility aliases. |

## Reusable implementation candidates

- WordPress CPT/taxonomy and metabox presentation concepts; keep **data model identity distinct from authorization**.
- CSV parsing/export structures, field mapping, delimiter selection, BOM handling and title+event duplicate matching; introduce fixture-based tests before reuse.
- Guest-list query and table rendering, with explicit server-side scoping and pagination considered separately.
- Check-in, checkout and re-entry as **workflow concepts only**. Do not reuse anonymous mutations or destructive event-history semantics as authoritative domain invariants.
- QR ticket URL construction can inform future design; the QR image provider and ticket security require fresh decisions.

## Documented debt and future-work boundaries

1. **Activation and registration (blocking functional baseline):** class-to-filename autoload mismatch, absent module hook wiring, and missing actual WordPress activation + registration integration tests. Verify each module's hooks explicitly rather than relying on a metadata-only integration test.
2. **Security (blocking public exposure):** anonymous check-in/list AJAX and nonce-only access, predictable ticket identifiers, missing per-event authorization and permission checks on sensitive reads and writes; CPT capability mapping deserves a separate WordPress permissions audit.
3. **Data model:** one current status with overwritten timestamps/operator fields, no immutable check-in/check-out event ledger, no atomic transition invariants, and no trustworthy complete history; no schema redesign in WEM-9.
4. **CSV (bounded probable regression):** import created/updated counter classification; test existing/new/skipped/duplicate-by-event cases before correcting. Also test encoding conversions with non-UTF-8 fixtures.
5. **QR/provider:** image generated via an external service; validate reliability, privacy, sanitization and the use of unpredictable, purpose-scoped invitation credentials before production use.
6. **Release/CI:** no proof of real plugin operation despite WEM-8 full static, unit, and isolated WordPress *metadata* integration GREEN; Node/action and Ubuntu runner warnings are nonblocking maintenance issues.

## Jira follow-up reconciliation — approved

The user explicitly approved preserving these findings as independent Jira work, not modifying runtime while closing WEM-9.

| Issue | Ownership | Relationship to foundation |
| --- | --- | --- |
| [WEM-11](https://trail-running-workout-planning.atlassian.net/browse/WEM-11) | Bug: fix autoload resolution, modular hooks, prove isolated WordPress plugin activation/registration | **Blocks WEM-10 closure** because WEM-2 requires a walkthrough and source-backed functional continuity. WEM-2 cannot be represented as a verified usable baseline while this remains unresolved. |
| [WEM-12](https://trail-running-workout-planning.atlassian.net/browse/WEM-12) | Bug: test and correct CSV existing-row creation/update counters | Independent probable behavior regression. Tracked as related to WEM-9/WEM-2; do not silently add its fix to WEM-9. Final WEM-10 AC review must explicitly decide whether an open regression can be accepted as a documented limitation. |
| [WEM-13](https://trail-running-workout-planning.atlassian.net/browse/WEM-13) | Story: event-scoped permission model and authorization of guest reads/mutations | Separately scoped security work, related to WEM-11/WEM-2. **Public exposure remains disallowed without authorization assurance**; WordPress activation itself does not establish secure operation. |
| [WEM-14](https://trail-running-workout-planning.atlassian.net/browse/WEM-14) | Story: append-only guest movement event model and current-status projections | Long-term domain model follow-up related to WEM-13; not a precondition for documenting the foundation source baseline. |

### Closure decision

WEM-9 is limited to historical delta classification and publication, and its acceptance findings have explicit issue owners. Its analysis can be completed without editing the plugin.

WEM-10 and parent WEM-2 **must remain open** pending the WEM-11 activation/registration evidence and a distinct acceptance determination on any remaining regression/security limitations. The earlier GitHub Actions WordPress integration job only checked plugin metadata; it did not activate the plugin. No automatic merge to `dev`.

## Non-goals and acceptance check

WEM-9 is **classification and documentation**, not implementation. Do not fix the importer, autoloader, hook wiring or security vulnerabilities here without separate approved Jira work. Do not restore historical branding or migration compatibility. Source-backed findings above should be reconciled into explicit future work and reviewed before WEM-9 moves to Listo. WEM-10 owns final foundation closure and merge, not this task.
