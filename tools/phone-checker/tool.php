<?php
/**
 * WPTools — Unlinked Phone Checker
 *
 * Self-contained tool. All logic, AJAX handlers, assets, and rendering
 * live in this directory. Nothing outside this folder needs to change
 * when this tool is added, removed, or updated.
 */

if (!defined('ABSPATH')) {
  exit;
}

define('WPTOOLS_PHONE_DIR', plugin_dir_path(__FILE__));
define('WPTOOLS_PHONE_URL', plugin_dir_url(__FILE__));
define('WPTOOLS_PHONE_TRANSIENT', 'wptools_phone_scan');
define('WPTOOLS_PHONE_TRANSIENT_TTL', HOUR_IN_SECONDS * 6);
define('WPTOOLS_PHONE_NONCE', 'wptools_phone_nonce');

require_once WPTOOLS_PHONE_DIR . 'phone-checker.php';

wptools_register_tool([
  'id'         => 'phone-checker',
  'label'      => 'Unlinked Phones',
  'page_title' => 'WPTools — Unlinked Phone Checker',
  'render'     => 'wptools_phone_render_page',
  'enqueue'    => 'wptools_phone_enqueue_assets',
]);

add_action('wp_ajax_wptools_phone_run_scan',    'wptools_phone_ajax_run_scan');
add_action('wp_ajax_wptools_phone_clear_cache', 'wptools_phone_ajax_clear_cache');
