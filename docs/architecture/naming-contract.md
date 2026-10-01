# WEM-8 — Clean-break naming contract

Status: **approved product compatibility decision; test-first implementation pending** (2026-10-01).
Authority: Jira WEM-8. This document is a forward contract, not a claim that existing plugin code already complies.

## Scope and compatibility
- Target: **new WordPress installations only**. No legacy data import, backfill, dual-read metadata, upgrade script, backward-compatible entrypoint, shortcodes, hooks, AJAX aliases or deprecation adapters.
- Existing installations must not be silently presented as upgrade-compatible. Do not run this change against live WordPress sites expecting preserved guest records.
- Rename the plugin entrypoint to `wordpress-event-manager.php` while retaining the repository/plugin directory `wordpress-event-manager`. This changes the WordPress plugin basename intentionally.
- Preserve the unbranded existing WordPress content type `invitado` and taxonomy `evento` during WEM-8. This avoids broadening naming work into a domain redesign. This preservation is **not** a pledge to migrate existing guest data.
- No alterations to invitation security, check-in authorization, historical event storage or QR service design merely as a side effect of this rename. Security issues remain separately tracked.

## Canonical identity and identifiers
| Surface | Canonical form |
| --- | --- |
| Display name | WordPress Event Manager |
| Directory and translation text domain | `wordpress-event-manager` |
| Main PHP plugin file | `wordpress-event-manager.php` |
| Display author | `WordPress Event Manager Contributors` |
| Plugin URI | `https://github.com/m415x/wordpress-event-manager` |
| New PHP class namespace | `WEM\\` |
| Transitional names for existing procedural classes | `WEM_CPT_Manager`, `WEM_Taxonomy_Manager`, `WEM_Metabox_Manager`, `WEM_Admin_Columns`, `WEM_Ajax_Handler`, `WEM_Shortcode_Manager`, `WEM_Import_Export`, `WEM_QR_Generator` |
| PHP constants | `WEM_PATH`, `WEM_URL` |
| PHP helper functions | `wem_*` |
| Post metadata | `wem_*` |
| AJAX action names / nonce actions / admin form fields | `wem_*` |
| Shortcodes | `wem_checkin`, `wem_list`, `wem_qr_table`, `wem_qr_single` |
| CSS classes and HTML selectors | `wem-*` |
| JavaScript globals and localized configuration | `WEM`, `wem_ajax` |

## Data and behavior boundaries
- For a new installation, read/write only the canonical `wem_*` metadata; update every caller together: admin forms, columns and filters, AJAX handlers, CSV, templates, helpers and QR output.
- Rename class declarations, autoloader resolution and call sites together. Ensure filename conversion handles underscores into the existing hyphenated class filenames. Avoid creating an autoload alias to the previous class prefix.
- Keep current CSV **column headers** (`titulo,nombre,organizacion,mesa,evento,observaciones,checkin`) because they are ordinary field names, not brand identifiers. Internally they map only to the new keys.
- New shortcodes intentionally replace old embed codes; do not register historical tags. Pages from older installations are out of scope.
- Preserve the existing semantic content fields and actions to the extent possible, but **do not claim activation/feature parity** until independent WordPress integration tests explicitly verify hooks and rendering.
- Do not silently fix the already-audited missing module registration and unsafe anonymous AJAX routes during a mechanical rename. Where activation is necessarily fixed, require a separate explicit test and record the behavior change; do not treat a passing source scan as evidence of security.

## Regression and release gates
1. Add and run an **expected RED** on the original source: asserted canonical entrypoint and a source scan excluding dependencies, Git metadata and generated artifacts. Failure must be a PHPUnit assertion for the naming contract, not missing Composer, WordPress or PHP fatal.
2. Implement small, coherent rename changes; do not retain prohibited historical identifiers in comments, test fixtures, compatibility code, docs or user-visible strings. Historical exact identifiers stay in WEM-3/WEM-8 Jira audit only.
3. GREEN: focused naming contract, all unit/integration tests, PHP syntax, PHPCS, PHPStan and GitHub Actions.
4. WordPress activation and new shortcode/AJAX registration require separate integration evidence before considering the plugin operational; security authorization review is distinct.
5. Update the handoff and Jira with exact executed RED/GREEN evidence. Do not close WEM-8 while source scan or CI is red.

## Approved exclusions
- WordPress core/plugin dependencies and `.git` history are not maintained product source.
- Generated Composer lockfile package metadata and hashes are excluded from the human-authored text scan. The dependency graph remains validated by Composer and CI.
- Scanning current maintained PHP/JS/CSS/docs/config files is mandatory. No suppression list for historical identifiers is permitted.
