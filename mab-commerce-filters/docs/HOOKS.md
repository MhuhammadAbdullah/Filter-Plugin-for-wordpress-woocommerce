# Hooks Reference

## Actions

### `mabcf_loaded`

Fires once, at the end of `Plugin::boot()`, after every controller and
service has been registered.

```php
add_action( 'mabcf_loaded', function () {
    // Safe point to interact with any MAB Commerce Filters service.
} );
```

### `mabcf_register_filter_types`

Fires after every built-in filter type is registered. Use it to add a
custom filter type.

```php
add_action( 'mabcf_register_filter_types', function ( \MABCommerceFilters\Filters\FilterTypeRegistry $registry ) {
    $registry->register( new My_Custom_Filter_Type() );
} );
```

## Filters

### `mabcf_should_load_assets`

`bool`. Return `true` to force-enqueue `mabcf-frontend` CSS/JS on the
current request (the plugin already auto-detects the shop page, product
taxonomy archives, and any post containing `[mabcf_filters]`).

```php
add_filter( 'mabcf_should_load_assets', function ( $should_load ) {
    return $should_load || is_page( 'custom-catalog' );
} );
```

### `mabcf_ajax_query_args`

`array`. Fires immediately before the final `WP_Query` runs for an
AJAX/REST filter request. Receives and must return the WP_Query args
array; also receives the active filter set row and the parsed
selection for context.

```php
add_filter( 'mabcf_ajax_query_args', function ( array $args, array $filter_set, array $selection ) {
    // e.g. only show products the current user's role can purchase.
    $args['meta_query'][] = array( 'key' => '_visible_to_role', 'value' => wp_get_current_user()->roles, 'compare' => 'IN' );
    return $args;
}, 10, 3 );
```

## Filter type contract

Every filter type implements
`MABCommerceFilters\Interfaces\FilterTypeInterface`. Extend
`MABCommerceFilters\Filters\AbstractFilterType` for the shared
`count_products()`, `wrap()` and `to_array()` helpers used by every
built-in type.

## REST API

- `GET /wp-json/mabcf/v1/filter-sets` — list active filter sets.
- `GET /wp-json/mabcf/v1/filter-sets/{id}` — describe a set's filters.
- `POST /wp-json/mabcf/v1/filter-sets/{id}/query` — run a filtered
  product query (same payload shape as the AJAX endpoint: `mabcf_filter`,
  `context`, `paged`, `orderby`); returns the same `filters_html` /
  `grid_html` / `active_html` / `query_args` shape used by the front end.

## Shortcodes

- `[mabcf_filters set="slug-or-id"]` — renders a filter widget.
  Omit `set` to auto-resolve from the current page's location
  assignment.
- `[mabcf_products set="slug-or-id" columns="4"]` — renders the
  filtered product grid standalone.
