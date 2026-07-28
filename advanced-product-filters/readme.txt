=== Advanced Product Filters ===
Contributors: advancedproductfilters
Tags: woocommerce, product filter, ajax filter, layered navigation, elementor
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium AJAX product filtering for WooCommerce — categories, price, swatches, attributes, brands, tags, ratings and more.

== Description ==

Advanced Product Filters adds a Shopify/WoodMart-style AJAX filter sidebar to any WooCommerce store. Build unlimited Filter Sets from wp-admin, assign each one to the shop page, a category/tag/brand archive, a custom taxonomy, specific pages or a specific Elementor template, and let shoppers narrow down your catalog instantly — no page reloads required.

= Highlights =

* Unlimited Filter Sets with drag-and-drop section ordering
* Active Filters, Categories (tree view), Price (dual-handle slider), Color/Image/Label swatches, Size/attribute buttons, Brand, Tags, Ratings, Stock, Sale, Featured and New Arrivals sections
* Checkbox, radio, buttons, color swatch, image swatch, label pill, dropdown, range slider and AJAX search input types
* Instant AJAX filtering with optional Apply/Clear buttons, URL sync and full browser Back/Forward support
* Mobile offcanvas sidebar with overlay, swipe-to-close and smooth animations
* Works with simple, variable, grouped and external products, attributes, categories, tags, brands, custom taxonomies, price, stock, sale and featured status
* 9 Elementor widgets: Advanced Filter Sidebar, Product Grid, Filter Toggle Button, Active Filters, Price Slider, Color Swatches, Category Filter, Tag Filter and Brand Filter — all AJAX-connected automatically
* REST API (`apf/v1`) and a `[advanced_product_filters]` shortcode for headless/Gutenberg use
* Import/Export Filter Sets as JSON, plus a one-click Reset
* Object-cache aware facet counting built for large catalogs
* Translation ready, hookable, template-overridable, and built with zero jQuery dependency on the front end

See `docs/USER-GUIDE.md` and `docs/DEVELOPER-GUIDE.md` in the plugin folder for full documentation.

== Installation ==

1. Upload the `advanced-product-filters` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **Product Filters → Filter Sets** and edit the auto-created "Default Shop Filters" set, or create a new one.
4. Visit your shop page to see the sidebar.

== Frequently Asked Questions ==

= Does this work without Elementor? =

Yes. The plugin automatically injects the sidebar into any WooCommerce archive using standard WooCommerce template hooks, independently of any page builder. Elementor is only needed if you want to use the bundled widgets.

= Can I override the templates from my theme? =

Yes. Copy any file from the plugin's `templates/` folder to `yourtheme/advanced-product-filters/` and edit it there.

= Does it support 100,000+ products? =

Facet counts and price ranges are computed with a single indexed SQL join (never loading matched product IDs into PHP) and cached via the persistent object cache when available, or transients otherwise. For very large catalogs we recommend a persistent object cache (Redis/Memcached).

== Changelog ==

= 1.0.0 =
* Initial release.
