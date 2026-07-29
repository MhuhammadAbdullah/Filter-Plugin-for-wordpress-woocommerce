# Installation Guide

## Requirements

- PHP 8.2+
- WordPress 6.0+
- WooCommerce 7.0+ (required — the plugin will not boot without it)
- Elementor (optional — the Elementor widgets only register when
  Elementor is active)

## Installing

1. Upload the `mab-commerce-filters` folder to `wp-content/plugins/`, or
   upload the plugin ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **MAB Commerce Filters** from the Plugins screen.
3. On activation the plugin:
   - Creates its database tables (`{prefix}mabcf_filter_sets`,
     `filters`, `locations`, `settings`, `logs`, `migrations`).
   - Seeds sensible default settings.
   - Creates a **Default Shop Filters** filter set (Categories, Price,
     Color, Size, Tags) and assigns it to your Shop page, so filtering
     works immediately with zero configuration.
4. Visit **MAB Commerce Filters** in the WordPress admin menu to review
   or customise the default set, or create additional filter sets.

## Verifying the install

Go to **MAB Commerce Filters → System Status**. You should see every
database table listed as "OK" and your PHP/WordPress/WooCommerce
versions meeting the minimums above.

## Uninstalling

By default, uninstalling the plugin (delete from the Plugins screen)
**keeps all your data** so a reinstall picks up right where you left
off. To fully remove all filter sets, filters, locations, settings and
logs on uninstall, enable **MAB Commerce Filters → Settings → "Remove
all data on uninstall"** before deleting the plugin.
