<?php
/**
 * WPTools Loader
 *
 * Registers the top-level WPTools admin menu, enqueues shared assets,
 * and auto-loads every tool found in the /tools/ directory.
 *
 * To add a new tool:
 *   1. Create a folder: /tools/your-tool-slug/
 *   2. Add a file:      /tools/your-tool-slug/tool.php
 *   3. In tool.php, call wptools_register_tool() with your tool definition.
 *
 * That's it — no changes to this file or wptools.php needed.
 */

if (!defined('ABSPATH')) {
  exit;
}

// Registry of all loaded tools.
$wptools_registry = [];

/**
 * Register a tool with WPTools.
 *
 * Each tool calls this once from its tool.php file.
 *
 * @param array $args {
 *   @type string   $id           Unique tool ID (e.g. 'phone-checker'). Required.
 *   @type string   $label        Menu label shown in the sidebar. Required.
 *   @type string   $page_title   <title> tag for the tool's admin page. Required.
 *   @type callable $render       Callback that renders the tool's page HTML. Required.
 *   @type callable $enqueue      Optional. Callback to enqueue tool-specific assets.
 *                                Receives the current $hook string as its only argument.
 * }
 */
function wptools_register_tool($args) {
  global $wptools_registry;

  $defaults = [
    'id'          => '',
    'label'       => '',
    'page_title'  => '',
    'render'      => null,
    'enqueue'     => null,
  ];

  $tool = array_merge($defaults, $args);

  if (empty($tool['id']) || !is_callable($tool['render'])) {
    return;
  }

  $wptools_registry[$tool['id']] = $tool;
}

/**
 * Auto-load all tools by requiring each tool.php found under /tools/.
 */
function wptools_load_tools() {
  $tool_dirs = glob(WPTOOLS_DIR . 'tools/*/tool.php');

  if (empty($tool_dirs)) {
    return;
  }

  foreach ($tool_dirs as $tool_file) {
    require_once $tool_file;
  }
}

/**
 * Register the WPTools admin menu and one submenu page per tool.
 */
function wptools_register_menu() {
  global $wptools_registry;

  if (empty($wptools_registry)) {
    return;
  }

  $first_tool = reset($wptools_registry);

  // Top-level menu item points to the first registered tool.
  add_menu_page(
    'WPTools',
    'WPTools',
    'manage_options',
    'wptools',
    '__return_false',
    'dashicons-admin-tools',
    80
  );

  foreach ($wptools_registry as $id => $tool) {
    $hook_suffix = add_submenu_page(
      'wptools',
      $tool['page_title'],
      $tool['label'],
      'manage_options',
      'wptools-' . $id,
      $tool['render']
    );

    // Store hook suffix on the tool so enqueue callbacks can check it.
    $wptools_registry[$id]['hook_suffix'] = $hook_suffix;
  }

  // Remove the auto-generated duplicate top-level submenu entry WordPress adds.
  remove_submenu_page('wptools', 'wptools');
}

/**
 * Dispatch asset enqueuing to each tool that registered an enqueue callback.
 */
function wptools_enqueue_assets($hook) {
  global $wptools_registry;

  foreach ($wptools_registry as $tool) {
    if (!empty($tool['enqueue']) && is_callable($tool['enqueue'])) {
      call_user_func($tool['enqueue'], $hook);
    }
  }
}

// Boot sequence.
wptools_load_tools();
add_action('admin_menu', 'wptools_register_menu');
add_action('admin_enqueue_scripts', 'wptools_enqueue_assets');
