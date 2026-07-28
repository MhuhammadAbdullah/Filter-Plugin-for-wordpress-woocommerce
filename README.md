# Advanced Product Filters

A premium, dependency-free AJAX product filter plugin for WordPress + WooCommerce, built to match the quality bar of Shopify/WoodMart/Novaworks-style storefront filtering — unlimited Filter Sets, drag-and-drop section ordering, color/image/label swatches, a dual-handle price slider, a mobile offcanvas sidebar, and 9 Elementor widgets, all wired together over AJAX.

The plugin lives in [`advanced-product-filters/`](advanced-product-filters).

## Getting started

1. Copy (or symlink) the `advanced-product-filters` folder into a WordPress install's `wp-content/plugins/` directory.
2. Activate **Advanced Product Filters** from the Plugins screen (requires WooCommerce active, PHP 8.0+).
3. Go to **Product Filters → Filter Sets** and edit the auto-created "Default Shop Filters" set.

Full documentation:

- [`advanced-product-filters/docs/USER-GUIDE.md`](advanced-product-filters/docs/USER-GUIDE.md) — building Filter Sets, swatches, settings, import/export, Elementor widgets.
- [`advanced-product-filters/docs/DEVELOPER-GUIDE.md`](advanced-product-filters/docs/DEVELOPER-GUIDE.md) — architecture, data model, hooks/filters, REST API, template overrides, performance design.
- [`advanced-product-filters/readme.txt`](advanced-product-filters/readme.txt) — WordPress.org-style plugin readme.

## Highlights

- **Unlimited Filter Sets**, assignable to the shop page, category/tag/brand/custom-taxonomy archives, specific pages or specific Elementor templates, with priority-based conflict resolution and a live preview.
- **Every requested section**: Active Filters, Categories (tree view), Price (dual-handle slider), Color/Image/Label swatches, Buttons, Brand, Tags, Ratings, Stock, Sale, Featured, New Arrivals and Custom Taxonomies — each independently enable-able, reorderable and configurable.
- **Instant AJAX filtering** with optional Apply/Clear buttons, URL sync with full browser Back/Forward support, classic pagination or infinite scroll, and WooCommerce sort-order interception — all without a page reload.
- **Elementor + Gutenberg compatible**: 9 dedicated Elementor widgets that auto-sync over AJAX, plus a `[advanced_product_filters]` shortcode usable from any block/builder.
- **Built for scale**: facet counts and price ranges are computed via a single indexed SQL join (never loading matched product IDs into PHP), cached through the persistent object cache or transients, and invalidated automatically when products change.
- **No jQuery on the front end** — vanilla ES2017, CSS custom properties, Flexbox/Grid, no Bootstrap.
- **REST API** (`apf/v1`) alongside an `admin-ajax.php` fallback, translation-ready strings, and a full template-override system (`yourtheme/advanced-product-filters/`).
