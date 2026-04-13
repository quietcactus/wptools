<?php
/**
 * WPTools — Image Converter
 *
 * Self-contained tool. All logic, AJAX handlers, assets, and rendering
 * live in this directory. Nothing outside this folder needs to change
 * when this tool is added, removed, or updated.
 */

if (!defined('ABSPATH')) {
  exit;
}

define('WPTOOLS_IMAGECONV_DIR', plugin_dir_path(__FILE__));
define('WPTOOLS_IMAGECONV_URL', plugin_dir_url(__FILE__));
define('WPTOOLS_IMAGECONV_NONCE', 'wptools_imageconv_nonce');

require_once WPTOOLS_IMAGECONV_DIR . 'image-converter.php';

wptools_register_tool([
  'id'         => 'image-converter',
  'label'      => 'Image Converter',
  'page_title' => 'WPTools — Image Converter',
  'render'     => 'wptools_imageconv_render_page',
  'enqueue'    => 'wptools_imageconv_enqueue_assets',
]);

add_action('wp_ajax_wptools_imageconv_convert',    'wptools_imageconv_ajax_convert');
add_action('wp_ajax_wptools_imageconv_compress',   'wptools_imageconv_ajax_compress');
add_action('wp_ajax_wptools_imageconv_get_images', 'wptools_imageconv_ajax_get_images');
add_action('wp_ajax_wptools_imageconv_process',    'wptools_imageconv_ajax_process');
