# Developer Guide

## Architecture

```
mab-commerce-filters.php        Bootstrap: constants, autoloader, activation hooks
includes/
  Autoloader.php                PSR-4 autoloader (MABCommerceFilters\ → includes/)
  Plugin.php                    Singleton that wires every service together
  Activator.php / Deactivator.php
  Database/
    Schema.php                  dbDelta table definitions
    Migrator.php                Applies pending migrations, tracked in mabcf_migrations
    Migrations/                 One class per schema version
  Repositories/                 Repository pattern over the custom tables
  Interfaces/                   RepositoryInterface, FilterTypeInterface
  Filters/                      One class per filter type (Strategy pattern)
  Services/                     FilterQueryService, CacheService, LocationResolver,
                                 UrlSyncService, FilterRequestHandler, ViewTracker
  Admin/                        Admin menu, pages (one class per screen), AJAX controller
  Frontend/                     Renderer, ProductGridRenderer, AssetLoader,
                                 ShortcodeHandler, MainQueryIntegration, FilterWidget
  Ajax/                         Public AJAX controller (thin adapter over FilterRequestHandler)
  Rest/                         REST API controller (same adapter)
  Elementor/                    Elementor integration + widgets
  Helpers/                      Stateless helper functions
```

### Data model (custom tables, no CPTs, no `wp_options` blobs)

- `mabcf_filter_sets` — a named group of filters with layout/behaviour
  settings (JSON) and a status.
- `mabcf_filters` — one row per filter within a set: `type`, `label`,
  `source_key` (taxonomy/attribute/meta key), `display_style`,
  `settings` (JSON), `sort_order`.
- `mabcf_locations` — where a filter set is shown: `location_type`
  (`shop|category|tag|brand|page|template|global`) + `location_value`.
- `mabcf_settings` — key/value store for plugin-wide settings.
- `mabcf_logs` — structured debug/AJAX/query log entries.
- `mabcf_migrations` — applied schema versions.

### Filter type engine

Every filter type implements `Interfaces\FilterTypeInterface`:

```php
interface FilterTypeInterface {
    public function get_type(): string;
    public function get_options( array $filter, array $query_context ): array;
    public function render( array $filter, array $query_context, array $selected ): string;
    public function apply_query( array $filter, array &$args, $selected ): void;
}
```

`Filters\FilterTypeRegistry` holds every registered type. Built-ins:
`category`, `taxonomy` (+ aliases `tag`, `brand`, `custom_taxonomy`),
`attribute`, `price`, `rating`, `stock`, `sale`, `search`, `meta` (+
aliases `custom_field`, `boolean`, `date`, `number`, `text`).

### Request lifecycle (AJAX)

1. Front-end JS (`assets/js/frontend.js`) serialises the `.mabcf-form`
   with `FormData` and POSTs to `admin-ajax.php?action=mabcf_filter_products`.
2. `Ajax\FilterAjaxController` verifies the nonce and delegates to
   `Services\FilterRequestHandler`.
3. `FilterRequestHandler` resolves the filter set, rebuilds the base
   WP_Query args for the page's archive context
   (`Services\LocationResolver::base_args_for_context()`), and calls
   `FilterQueryService::build_query_args()` which asks each active
   filter's `apply_query()` to add its `tax_query` / `meta_query` /
   `post__in` clauses.
4. The resulting `WP_Query` runs, and the handler renders fresh facet
   HTML (`FilterQueryService::render_filters()` — counts are computed
   against every *other* filter's current selection, standard faceted
   search behaviour), the product grid (`ProductGridRenderer`, reusing
   your theme's `content-product.php` template part) and the active
   filters bar.
5. JS swaps the three fragments in place and, if enabled, updates the
   URL via `history.pushState()` using clean query vars
   (`Services\UrlSyncService`: `product_cat`, `product_tag`,
   `filter_{attribute}`, `min_price`/`max_price`, or a generic
   `mabcf_filter_{id}` for everything else).

The same `FilterRequestHandler` backs `Rest\RestController`
(`POST /wp-json/mabcf/v1/filter-sets/{id}/query`), so a headless front
end gets identical behaviour.

### Extending

- **Add a filter type**: implement `FilterTypeInterface` (extending
  `Filters\AbstractFilterType` for the shared counting/markup helpers)
  and register it on the `mabcf_register_filter_types` action.
- **Custom taxonomies/meta**: no code needed — use the `taxonomy` or
  `meta` filter types from the builder with your taxonomy/meta key as
  the source.
- See `HOOKS.md` for every action/filter.
