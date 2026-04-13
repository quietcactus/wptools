# WPTools

A suite of standalone WordPress admin tools, all housed under a single plugin.

---

## Current Tools

WPTools currently includes the following admin tools:

| Tool              | Summary                                                                                                                               |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `image-converter` | Lists WordPress media library images and processes supported files into WebP variants for conversion/compression workflows.           |
| `phone-checker`   | Scans public post types for phone numbers that appear in content without a linked `tel:` anchor and reports the matches in the admin. |

Each tool lives in its own folder under `tools/` and is auto-discovered by the plugin loader.

---

## WIP

### `image-converter`

- [ ] Add search bar to search for specific images.
- [ ] Add a small thumbnail preview of the images.
- [ ] Add filters for date, image type, name ordering.

---

## Adding a New Tool

1. **Create a folder** under `tools/` using your tool's slug:

   ```
   tools/your-tool-slug/
   ```

2. **Create `tool.php`** inside that folder. This is the only required file. Call `wptools_register_tool()` to register it:

   ```php
   <?php
   if (!defined('ABSPATH')) { exit; }

   define('WPTOOLS_MYTOOL_DIR', plugin_dir_path(__FILE__));
   define('WPTOOLS_MYTOOL_URL', plugin_dir_url(__FILE__));
   define('WPTOOLS_MYTOOL_NONCE', 'wptools_mytool_nonce');

   require_once WPTOOLS_MYTOOL_DIR . 'my-tool.php';

   wptools_register_tool([
     'id'         => 'my-tool',           // Unique slug — used in menu URL
     'label'      => 'My Tool',           // Sidebar menu label
     'page_title' => 'WPTools — My Tool', // <title> for the admin page
     'render'     => 'wptools_mytool_render_page',    // Required: page render callback
     'enqueue'    => 'wptools_mytool_enqueue_assets', // Optional: asset enqueue callback
   ]);

   // Register any AJAX actions your tool needs
   add_action('wp_ajax_wptools_mytool_do_thing', 'wptools_mytool_ajax_do_thing');
   ```

3. **Write your tool logic** in `my-tool.php` (or split into multiple files — your call). Prefix all function names with `wptools_mytool_` to avoid collisions.

4. **Done.** No changes to `wptools.php` or `loader.php` needed. The loader auto-discovers any `tools/*/tool.php` file on activation.

---

## Naming Conventions

| What               | Convention                      | Example                    |
| ------------------ | ------------------------------- | -------------------------- |
| Tool folder        | `tools/your-slug/`              | `tools/broken-links/`      |
| PHP functions      | `wptools_{slug}_{function}()`   | `wptools_links_run_scan()` |
| PHP constants      | `WPTOOLS_{SLUG}_{CONSTANT}`     | `WPTOOLS_LINKS_NONCE`      |
| AJAX actions       | `wptools_{slug}_{action}`       | `wptools_links_run_scan`   |
| JS localize object | `wptools{Slug}Data`             | `wptoolsLinksData`         |
| CSS classes        | `wptools-{slug}-{element}`      | `wptools-links-table`      |
| Asset handles      | `wptools-{slug}-styles/scripts` | `wptools-links-styles`     |
| Transients         | `wptools_{slug}_{key}`          | `wptools_links_scan`       |

Keeping consistent prefixes per tool ensures nothing bleeds between tools.

---

## Enqueue Hook

The `enqueue` callback receives the current `$hook` string. Check against your tool's expected hook to load assets only on your tool's page:

```php
function wptools_mytool_enqueue_assets($hook) {
  if ($hook !== 'wptools_page_wptools-my-tool') {
    return;
  }
  // enqueue styles and scripts here
}
```

The hook pattern is always: `wptools_page_wptools-{tool-id}`

