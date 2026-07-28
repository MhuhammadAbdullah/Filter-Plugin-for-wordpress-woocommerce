# Advanced Product Filters — Developer Guide

## Architecture overview

```
plugin.php                          Bootstrap: constants, autoloader, activation hooks
includes/classes/                   Domain logic shared by admin + frontend + AJAX + Elementor
  class-apf-plugin.php               Singleton that wires every subsystem together
  class-apf-post-types.php           Registers the private `apf_filter_set` storage CPT
  class-apf-filter-set.php           Value object for one Filter Set
  class-apf-filter-sets.php          Repository (CRUD, context matching, sanitisation)
  class-apf-section-types.php        Registry of section types (category, price, attribute, ...)
  class-apf-filter-input-types.php   Registry of input types (checkbox, color, image, ...)
  class-apf-taxonomies.php           Brand/attribute/custom taxonomy discovery
  class-apf-context.php              Resolves "where am I" and scores it against location rules
  class-apf-query-builder.php        Turns request params into tax_query/meta_query/date_query
  class-apf-facet-counts.php         Live term counts + price range via a single SQL subquery join
  class-apf-cache.php                Object-cache/transient wrapper
  class-apf-settings.php             Global settings (get/update/reset/sanitise)
  class-apf-import-export.php        JSON export/import
  class-apf-rest-controller.php      REST routes (apf/v1)
  class-apf-term-swatches.php        Adds Color/Image fields to attribute & brand terms
includes/admin/                     wp-admin: menu, list/editor pages, AJAX handlers
includes/frontend/                  Storefront: sidebar injection, renderer, templates, shortcode
includes/ajax/                      Shared `admin-ajax.php` + REST filtering logic
includes/elementor/                 Elementor category + 9 widgets
templates/                          Overridable front-end templates
assets/                             CSS (CSS variables, no framework) + vanilla JS (no jQuery)
```

## Data model

A **Filter Set** is an `apf_filter_set` post:

- `post_title` → name
- `post_status` → `publish` (enabled) / `draft` (disabled)
- `menu_order` → assignment priority
- `_apf_locations` (postmeta, array) → where it applies, e.g. `[{"type":"shop"}]` or `[{"type":"product_cat","ids":[12,15]}]`
- `_apf_sections` (postmeta, array) → ordered section configs, e.g. `{"id":"color","type":"attribute","taxonomy":"pa_color","input_type":"color","enabled":true,"show_count":true}`
- `_apf_settings` (postmeta, array) → per-Filter-Set behaviour overrides, falling back to the global `apf_settings` option

## Key hooks & filters

| Hook | Type | Purpose |
|---|---|---|
| `apf_loaded` | action | Fires once `APF_Plugin` has booted every subsystem. |
| `apf_section_types` | filter | Register a custom section type. |
| `apf_filter_input_types` | filter | Register a custom input type. |
| `apf_brand_taxonomies` | filter | Add your own brand taxonomy slug to the auto-detection list. |
| `apf_custom_taxonomies` | filter | Adjust which custom taxonomies are offered in the builder. |
| `apf_template_candidates` | filter | Adjust template override resolution order. |
| `apf_render_custom_section` | filter | Render markup for a section type not built into the plugin. |

## Adding a custom section type

```php
add_filter( 'apf_section_types', function ( array $types ): array {
	$types['warranty'] = array(
		'label'        => __( 'Warranty', 'my-textdomain' ),
		'taxonomy'      => null,
		'input_types'  => array( 'toggle' ),
		'collapsible'  => true,
		'multi_select' => false,
	);
	return $types;
} );

add_filter( 'apf_render_custom_section', function ( string $html, array $section, array $applied_filters ): string {
	if ( 'warranty' !== $section['type'] ) {
		return $html;
	}
	// Render your own markup here, reading/writing whatever query param you choose,
	// and hook `APF_Query_Builder`'s build via `apf_loaded` if it needs to affect the product query.
	return '<div class="apf-section">…</div>';
}, 10, 3 );
```

## Template overrides

Copy any file from `templates/` to `yourtheme/advanced-product-filters/` (same filename) and it will be used automatically — resolution order is child theme → parent theme → plugin, exactly like WooCommerce's own template override system.

## REST API (`apf/v1`)

- `GET /apf/v1/filter?filter_set_id=&apf_cat=&apf_price_min=&...` — public. Returns `{ grid_html, sidebar_html, found, max_num_pages, page, active_count, result_count_text }`.
- `GET /apf/v1/terms?taxonomy=pa_color&search=` — public. Returns `[{ id, name, slug, count }]`.
- `GET /apf/v1/pages?search=` — requires `manage_woocommerce`. Powers the location picker.
- `GET /apf/v1/elementor-templates` — requires `manage_woocommerce`.

`admin-ajax.php?action=apf_filter_products` mirrors `/apf/v1/filter` exactly (both call `APF_Ajax::build_response()`), for setups where the REST API is disabled.

## Performance notes

`APF_Facet_Counts` never materialises a matched-product ID array in PHP. It asks `WP_Query` to compile its SQL (via the `posts_pre_query` short-circuit filter), then reuses that compiled `SELECT ... FROM wp_posts WHERE ...` as a subquery inside one `COUNT() ... GROUP BY term_id` statement. This keeps facet counting a single indexed join even on very large catalogs. Results are cached (persistent object cache when available, transients otherwise) and invalidated automatically on `save_post_product`, term changes, and stock/price updates. For extremely large catalogs (500k+ SKUs), pairing this with a persistent object cache (Redis/Memcached) is strongly recommended.

## Front-end JS architecture

`assets/js/advanced-product-filters.js` groups every filter control and product grid on the page into **scopes** (`data-apf-scope`). The auto-injected sidebar and its matching grid share the implicit `"context"` scope; each standalone Elementor widget can share that same scope or its own. A scope's `refresh()` serialises every input inside its containers, calls `/apf/v1/filter`, and replaces the sidebar + grid(s) HTML in one round trip — so dropping a Color Swatches widget and a Product Grid widget anywhere on an Elementor page keeps them in sync automatically, with zero extra configuration.

## Coding standards

PHP follows WordPress Coding Standards (tabs, Yoda-optional but consistent, full PHPDoc blocks, `esc_*`/`sanitize_*` at every boundary, prepared SQL). JS is dependency-free ES2017, no build step. CSS uses only custom properties, Flexbox and Grid — no preprocessor, no Bootstrap.
