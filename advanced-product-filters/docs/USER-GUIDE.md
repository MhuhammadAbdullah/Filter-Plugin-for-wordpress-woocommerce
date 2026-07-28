# Advanced Product Filters — User Guide

## 1. Creating your first Filter Set

1. Go to **Product Filters → Filter Sets** in wp-admin.
2. Click **Add New**.
3. Give it a name (e.g. "Shop Filters").
4. Under **Assign To**, choose where it should appear:
   - **Shop Page** — the main WooCommerce shop archive.
   - **All Product Archives** — every category/tag/brand/attribute archive.
   - **Categories / Tags / Brands / Custom Taxonomies** — pick specific terms.
   - **Specific Pages** — place the `[advanced_product_filters]` shortcode or the "Advanced Filter Sidebar" Elementor widget on that page.
   - **Specific Elementor Templates** — for Theme Builder archive/single templates built in Elementor.
5. Set a **Priority**. When two Filter Sets both match the same page, the higher priority one wins.
6. Under **Sections**, drag to reorder, toggle sections on/off, and click the gear icon on a section to configure its label, display style (checkbox/buttons/swatches/dropdown/tree), product counts and search box.
7. Click **Add Section** to add Categories, Price, Brand, Tags, Ratings, Stock, Sale, Featured, New Arrivals, an Attribute (e.g. Color, Size) or a Custom Taxonomy.
8. Watch the **Live Preview** panel on the right — it reloads the real shop page with this Filter Set forced on, even before it's enabled for shoppers.
9. Click **Save Filter Set**.

## 2. Color & Image Swatches

Color and Image swatch sections read their colour/image from the attribute's **term**, not the plugin. To set them:

1. Go to **Products → Attributes**, click "Configure terms" next to the attribute (e.g. Color).
2. Edit (or add) a term. You'll see two new fields: **Swatch Color** (a colour picker) and **Swatch Image** (a media picker).
3. Save the term. It will now render as a coloured circle / image thumbnail wherever that attribute is used in a Color/Image Swatch section.

## 3. Global Settings

**Product Filters → Settings** controls the defaults every new Filter Set inherits (Filter Sets can override each of these individually under "Behaviour" in the builder):

- **Instant AJAX Filtering** — update results the moment a filter is clicked.
- **Apply / Clear buttons** — show explicit "Apply Filters" / "Reset" controls.
- **Sync Filters To URL** — reflect active filters in the address bar (bookmarkable, Back-button friendly).
- **Sticky Sidebar**, **Mobile Offcanvas Breakpoint**, **Offcanvas Position** (left/right).
- **Pagination Mode** — classic numbered pagination, or infinite scroll.
- **Primary/Accent Color** — the sidebar's brand colours.
- **Caching** — enable/disable and set the lifetime for cached facet counts (recommended ON for large catalogs).

## 4. Import / Export

**Product Filters → Import/Export** lets you download every Filter Set + global setting as a JSON file, and re-import it on another site (e.g. moving from staging to production). Importing never deletes or overwrites existing Filter Sets — it always adds new ones.

## 5. Elementor Widgets

Search for "Advanced Product Filters" in the Elementor widget panel to find:

- **Advanced Filter Sidebar** — the full sidebar for a chosen (or auto-detected) Filter Set.
- **Product Grid (Filterable)** — a standalone product grid that reacts to any filter widgets on the same page.
- **Filter Toggle Button**, **Active Filters**, **Price Slider**, **Color Swatches**, **Category Filter**, **Tag Filter**, **Brand Filter** — single-purpose widgets you can lay out however you like; they automatically stay in sync with each other and with the Product Grid widget via AJAX.

## 6. Troubleshooting

- **No sidebar appears on the shop page.** Make sure a Filter Set is both *enabled* and *assigned* to the Shop Page (or the current archive).
- **Color/Image swatches show a blank circle.** Set the term's Swatch Color/Image under Products → Attributes (see section 2).
- **Filters don't update the grid.** Check that Instant AJAX is on, or click Apply if you've turned it off. If the issue persists, check your browser console for a blocked REST API request (some security plugins restrict `/wp-json/`).
