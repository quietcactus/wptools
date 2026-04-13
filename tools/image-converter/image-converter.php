<?php
/**
 * Image Converter — Core
 *
 * Contains all logic for this tool. Loaded only by tool.php.
 * Functions are prefixed wptools_imageconv_ to avoid collisions.
 */

if (!defined('ABSPATH')) {
  exit;
}

// ---------------------------------------------------------------------------
// Assets
// ---------------------------------------------------------------------------

function wptools_imageconv_enqueue_assets($hook) {
  $expected = 'wptools_page_wptools-image-converter';

  if ($hook !== $expected) {
    return;
  }

  wp_enqueue_style(
    'wptools-imageconv-styles',
    WPTOOLS_IMAGECONV_URL . 'assets/image-converter.css',
    [],
    WPTOOLS_VERSION
  );

  wp_enqueue_script(
    'wptools-imageconv-scripts',
    WPTOOLS_IMAGECONV_URL . 'assets/image-converter.js',
    ['jquery'],
    WPTOOLS_VERSION,
    true
  );

  wp_localize_script('wptools-imageconv-scripts', 'wptoolsImageconvData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce(WPTOOLS_IMAGECONV_NONCE),
  ]);
}

// ---------------------------------------------------------------------------
// API Engine
// ---------------------------------------------------------------------------

/**
 * Convert a JPG/PNG file to WebP via compress-or-die.com API.
 *
 * @param string $file_path Absolute server path to the source file.
 * @return array|WP_Error Response array on success, WP_Error on failure.
 */
function wptools_imageconv_convert($file_path) {
  // TODO Phase 3: POST to https://compress-or-die.com/api-v2 using wp_safe_remote_post()
  return new WP_Error('not_implemented', 'Convert not yet implemented.');
}

/**
 * Compress a WebP file via compress-or-die.com API.
 *
 * @param string $file_path Absolute server path to the source WebP file.
 * @return array|WP_Error Response array on success, WP_Error on failure.
 */
function wptools_imageconv_compress($file_path) {
  // TODO Phase 3: POST to https://compress-or-die.com/api-v2 using wp_safe_remote_post()
  return new WP_Error('not_implemented', 'Compress not yet implemented.');
}

// ---------------------------------------------------------------------------
// AJAX handlers
// ---------------------------------------------------------------------------

function wptools_imageconv_ajax_convert() {
  check_ajax_referer(WPTOOLS_IMAGECONV_NONCE, 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions.');
  }

  $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

  if ($attachment_id < 1) {
    wp_send_json_error('Invalid attachment ID.');
  }

  wp_send_json_success(['stub' => true, 'action' => 'convert', 'attachment_id' => $attachment_id]);
}

function wptools_imageconv_ajax_compress() {
  check_ajax_referer(WPTOOLS_IMAGECONV_NONCE, 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions.');
  }

  $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

  if ($attachment_id < 1) {
    wp_send_json_error('Invalid attachment ID.');
  }

  wp_send_json_success(['stub' => true, 'action' => 'compress', 'attachment_id' => $attachment_id]);
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

function wptools_imageconv_render_page() {
  ?>
  <div class="wrap wptools-imageconv-wrap">
    <h1><?php echo esc_html__('Image Converter', 'wptools'); ?></h1>
    <p class="wptools-imageconv-description">
      <?php echo esc_html__('Convert JPG/PNG images to WebP or compress existing WebP images via the compress-or-die.com API.', 'wptools'); ?>
    </p>
    <div id="wptools-imageconv-app" class="wptools-imageconv-app">
      <p><?php echo esc_html__('Image selection UI coming in Phase 2.', 'wptools'); ?></p>
    </div>
  </div>
  <?php
}
