=== MAB Commerce Filters ===
Contributors: mabcommerce
Tags: woocommerce, product filter, ajax filter, elementor, filter
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.2
WC requires at least: 7.0
WC tested up to: 10.9
Requires Plugins: woocommerce
Stable tag: 1.0.3
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
* Fully compatible with High-Performance Order Storage (HPOS) and the
  Cart & Checkout blocks — the plugin only ever filters products and
  never touches order data or classic cart/checkout markup.

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

= Is it compatible with High-Performance Order Storage (HPOS)? =

Yes. The plugin declares compatibility with `custom_order_tables` via
`FeaturesUtil::declare_compatibility()` on `before_woocommerce_init`,
and never reads or writes order data in the first place, so it behaves
identically whether HPOS or legacy post-based order storage is active.

= Is it compatible with the Cart & Checkout blocks? =

Yes, declared via the same mechanism. The plugin filters the product
catalog only; it does not render or hook into cart/checkout markup, so
there is nothing for it to conflict with on block-based Cart/Checkout
pages.

== Changelog ==

= 1.0.3 =
* Fix filtering not working: the selection data passed to each filter
  type's query builder and its facet re-render used two different
  shapes depending on whether the request came from a checkbox click
  (AJAX POST) or a query-string/back-forward reload, and neither side
  understood the other's shape. This broke category, attribute
  (color/size), brand/tag, stock, rating and sale filtering on
  GET-driven loads (shared/bookmarked URLs, the Elementor Products Grid
  widget's own render, and reload-after-back-navigation), and broke
  checked-state persistence — and therefore multi-filter combinations —
  after every AJAX response.
* Fix pagination: page links generated without a stable base/format so
  clicking page 2+ during AJAX filtering always reloaded page 1.
* Fix Reset not clearing an active price selection.
* Fix a price filter's own "Apply" button never actually submitting
  when enabled.
* Fix removing a price pill collapsing the range to $0–$0 instead of
  clearing it.
* Front-end AJAX errors (bad nonce, non-JSON response, HTTP failure)
  are now logged to the console instead of failing silently.

= 1.0.2 =
* Fix a fatal "Typed property must not be accessed before initialization"
  error on every front-end page load when Elementor was active, caused
  by SingleFilterWidget assigning its properties after calling
  parent::__construct() (Elementor's base widget constructor reads them
  back via get_name()/get_title()/get_icon() before returning).

= 1.0.1 =
* Declare WooCommerce feature compatibility (HPOS / custom order tables,
  Cart & Checkout blocks) via `FeaturesUtil::declare_compatibility()`.
* Add a WooCommerce Feature Compatibility panel to System Status.
* Bump tested-up-to versions (WordPress 7.0, WooCommerce 10.9).

= 1.0.0 =
* Initial release.
