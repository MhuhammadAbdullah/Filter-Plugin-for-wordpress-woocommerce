# MAB Commerce Filters

**Premium WooCommerce AJAX Product Filters for Elementor.**

MAB Commerce Filters is a commercial-grade WooCommerce filtering plugin: a
drag & drop filter builder, custom database tables (no CPTs, no bloated
`wp_options`), an instant AJAX filtering engine with URL sync and infinite
scroll, and native Elementor widgets.

## Requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.2 |
| WordPress | 6.0 |
| WooCommerce | 7.0 |
| Elementor (optional) | Latest recommended |

## Features

- **Filter Builder** — unlimited filter sets, drag & drop filter ordering,
  assignable to the shop page, category/tag/brand archives, specific
  pages, Elementor templates or a shortcode.
- **Filter types** — category (tree/list), price (dual slider), color
  swatches, image swatches, size pills, brand (name/image/logo), tags,
  rating, stock status (in/out/backorder/low), sale/featured/new
  arrivals/best selling/top rated/most viewed/recently viewed, search,
  any WooCommerce attribute, and generic custom field/meta filters
  (text/number/boolean/date).
- **AJAX engine** — instant filtering with no page reload, optional Apply
  button, browser URL sync, back/forward support, pagination and infinite
  scroll, WooCommerce ordering.
- **Elementor** — Advanced Filter Sidebar, per-type filter widgets
  (Category/Price/Color/Size/Brand/Tag/Rating/Stock/Search), Products
  Grid, Active Filters, Reset Button and Filter Toggle Button widgets.
- **Admin panel** — Dashboard, Filter Sets, Locations, Appearance,
  Performance, Import/Export, Settings, System Status, Logs, License.
- **Performance** — cached facet counts (object cache or transients),
  lazy-loaded assets only where a filter can render, indexed custom
  tables built for large catalogs.
- **Security** — prepared SQL throughout, nonces on every AJAX/REST
  mutation, `manage_woocommerce` capability checks, output escaping.
- **Accessibility** — keyboard-operable price slider and accordions, ARIA
  attributes, visible focus states.

## Quick Start

1. Activate the plugin (WooCommerce must already be active). A "Default
   Shop Filters" set (Categories, Price, Color, Size, Tags) is created
   automatically and assigned to your shop page.
2. Go to **MAB Commerce Filters → Filter Builder** to customise it, or
   create a new filter set.
3. Assign filter sets to locations under **MAB Commerce Filters →
   Locations**.
4. Drop `[mabcf_filters]` / `[mabcf_products]` shortcodes, the classic
   "MAB Commerce Filters" widget, or the Elementor **Advanced Filter
   Sidebar** / **Products Grid (Filtered)** widgets anywhere else.

See `INSTALLATION.md`, `DEVELOPER-GUIDE.md`, `HOOKS.md` and
`UPGRADE.md` in this folder for more detail.
