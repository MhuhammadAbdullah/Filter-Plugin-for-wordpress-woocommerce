# Upgrade Guide

## How schema upgrades work

`MABCF_DB_VERSION` (in `mab-commerce-filters.php`) is compared against
the `mabcf_db_version` option on every `plugins_loaded`. When they
differ, `Database\Migrator::migrate()` runs every migration class in
`includes/Database/Migrations/` that has not yet been recorded in the
`mabcf_migrations` table, in version order, then updates the stored
option. Every migration runs through `dbDelta()`, which is idempotent —
safe to re-run and safe on multisite.

## Adding a new migration

1. Bump `MABCF_DB_VERSION` in `mab-commerce-filters.php`.
2. Add `includes/Database/Migrations/Migration_X_Y_Z.php` with a
   `run(): void` method and a `public const VERSION = 'X.Y.Z';`.
3. Register it in `Database\Migrator::MIGRATIONS`.
4. If you're changing an existing table, update
   `Database\Schema::definitions()` — `dbDelta()` diffs the full `CREATE
   TABLE` statement against what exists, so always provide the
   **complete** target schema, not just the delta.

## Plugin version upgrades

Because filter sets, filters and locations live in dedicated tables
(not post meta or serialized options), there is nothing to migrate when
WooCommerce or WordPress core update their own schemas — the plugin
only depends on the `wp_posts`/`wp_postmeta`/`wp_term_relationships`
tables through standard `WP_Query`/`get_terms()` APIs.

Before upgrading in production:

1. Back up your database.
2. Check `MAB Commerce Filters → System Status` after upgrading to
   confirm every table reports "OK" and the DB version matches the
   plugin version.
3. If you customised any filter type via `mabcf_register_filter_types`,
   re-check `Interfaces\FilterTypeInterface` for signature changes noted
   in the changelog before upgrading.
