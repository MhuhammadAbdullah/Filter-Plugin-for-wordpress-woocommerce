=== MAB Commerce Filters ===
Contributors: mabcommerce
Tags: woocommerce, product filter, ajax filter, elementor, filter
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.2
WC requires at least: 7.0
WC tested up to: 9.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium WooCommerce AJAX product filters — category, price, color, size, brand, tag, rating, stock and custom attribute filters, with a drag & drop builder and native Elementor widgets.

== Description ==

MAB Commerce Filters is a full AJAX product filtering system for
WooCommerce: a drag & drop filter builder, custom database tables (no
custom post types, no bloated `wp_options`), an instant filtering engine
with URL sync, pagination and infinite scroll, and native Elementor
widgets.

= Features =

* Unlimited filter sets, assignable to the shop page, category/tag/brand
  archives, specific pages, Elementor templates or a shortcode.
* Category (tree/list), price (dual slider), color swatches, image
  swatches, size pills, brand (name/image/logo), tags, rating, stock
  status, sale/featured/new/best-selling/top-rated/most-viewed/recently
  viewed, search, any WooCommerce attribute, and generic custom
  field/meta filters.
* Instant AJAX filtering with no page reload, optional Apply button,
  browser URL sync with back/forward support, pagination and infinite
  scroll.
* Native Elementor widgets: Advanced Filter Sidebar, Products Grid,
  Active Filters, Reset Button, Filter Toggle Button, and one widget per
  filter type.
* Professional admin panel: Dashboard, Filter Sets, Locations,
  Appearance, Performance, Import/Export, Settings, System Status, Logs,
  License.
* Built for scale: cached facet counts, indexed custom tables, assets
  loaded only where a filter can render.

== Installation ==

1. Upload the plugin to `wp-content/plugins/` or install via **Plugins →
   Add New → Upload Plugin**.
2. Activate. WooCommerce must already be installed and active.
3. A "Default Shop Filters" set is created automatically. Customise it
   under **MAB Commerce Filters → Filter Builder**.

See `docs/INSTALLATION.md` for details.

== Frequently Asked Questions ==

= Does this use custom post types or `wp_options`? =

No. Filter sets, filters, locations, settings and logs all live in
dedicated custom database tables, installed via `dbDelta()` and
versioned migrations.

= Does it work without Elementor? =

Yes. Use the `[mabcf_filters]` / `[mabcf_products]` shortcodes or the
classic "MAB Commerce Filters" widget in any widget area.

= Is it translation ready? =

Yes, text domain `mab-commerce-filters`, `.pot`-ready strings throughout.

== Changelog ==

= 1.0.0 =
* Initial release.
