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

function wptools_imageconv_convert($file_path) {
  $editor = wp_get_image_editor($file_path);
  if (is_wp_error($editor)) {
    return $editor;
  }
  if (!$editor->supports_mime_type('image/webp')) {
    return new WP_Error('webp_unsupported', 'Server GD/Imagick does not support WebP.');
  }
  $info     = pathinfo($file_path);
  $out_path = $info['dirname'] . '/' . $info['filename'] . '.webp';
  if (file_exists($out_path)) {
    return new WP_Error('file_exists', 'Output file already exists: ' . basename($out_path));
  }
  $editor->set_quality(82);
  return $editor->save($out_path, 'image/webp');
}

function wptools_imageconv_compress($file_path) {
  $editor = wp_get_image_editor($file_path);
  if (is_wp_error($editor)) {
    return $editor;
  }
  if (!$editor->supports_mime_type('image/webp')) {
    return new WP_Error('webp_unsupported', 'Server GD/Imagick does not support WebP.');
  }
  $info     = pathinfo($file_path);
  $out_path = $info['dirname'] . '/' . $info['filename'] . '-min.webp';
  if (file_exists($out_path)) {
    return new WP_Error('file_exists', 'Output file already exists: ' . basename($out_path));
  }
  $editor->set_quality(75);
  return $editor->save($out_path, 'image/webp');
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function wptools_imageconv_format_bytes($bytes) {
  if ($bytes >= 1048576) {
    return round($bytes / 1048576, 1) . ' MB';
  }
  if ($bytes >= 1024) {
    return round($bytes / 1024, 1) . ' KB';
  }
  return $bytes . ' B';
}

// ---------------------------------------------------------------------------
// AJAX handlers
// ---------------------------------------------------------------------------

function wptools_imageconv_ajax_get_images() {
  check_ajax_referer(WPTOOLS_IMAGECONV_NONCE, 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions.');
  }

  // --- Sanitize and read params ---
  $search   = isset($_POST['search'])   ? sanitize_text_field(wp_unslash($_POST['search']))   : '';
  $type     = isset($_POST['type'])     ? sanitize_text_field(wp_unslash($_POST['type']))     : '';
  $year     = isset($_POST['year'])     ? absint($_POST['year'])     : 0;
  $month    = isset($_POST['month'])    ? absint($_POST['month'])    : 0;
  $orderby  = isset($_POST['orderby'])  ? sanitize_text_field(wp_unslash($_POST['orderby']))  : 'date';
  $order    = isset($_POST['order'])    ? strtoupper(sanitize_text_field(wp_unslash($_POST['order']))) : 'DESC';
  $page     = isset($_POST['page'])     ? max(1, absint($_POST['page']))     : 1;
  $per_page = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 50;

  // --- Validate enum params ---
  $allowed_types   = ['jpg', 'png', 'webp', ''];
  $allowed_orderby = ['date', 'filesize', 'title'];
  $allowed_order   = ['ASC', 'DESC'];

  if (!in_array($type, $allowed_types, true)) {
    $type = '';
  }
  if (!in_array($orderby, $allowed_orderby, true)) {
    $orderby = 'date';
  }
  if (!in_array($order, $allowed_order, true)) {
    $order = 'DESC';
  }

  // --- Build mime type filter ---
  $mime_map = [
    'jpg'  => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
  ];
  $post_mime_type = $type !== '' && isset($mime_map[$type])
    ? [$mime_map[$type]]
    : ['image/jpeg', 'image/png', 'image/webp'];

  // --- Build WP_Query orderby ---
  $wp_orderby = 'date';
  if ($orderby === 'title') {
    $wp_orderby = 'title';
  } elseif ($orderby === 'filesize') {
    // filesize is metadata; sort by meta_value_num after joining _wp_attached_file
    // WP_Query does not sort by filesize natively — sort by date, re-sort in PHP
    $wp_orderby = 'date';
  }

  // --- Query args ---
  $query_args = [
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => $post_mime_type,
    'posts_per_page' => $per_page,
    'paged'          => $page,
    'orderby'        => $wp_orderby,
    'order'          => $order,
    'no_found_rows'  => false,
  ];

  if ($search !== '') {
    $query_args['s'] = $search;
  }
  if ($year > 0) {
    $query_args['date_query'][] = ['year' => $year];
  }
  if ($month > 0) {
    if (!isset($query_args['date_query'])) {
      $query_args['date_query'] = [];
    }
    $query_args['date_query'][] = ['month' => $month];
    if (count($query_args['date_query']) > 1) {
      $query_args['date_query']['relation'] = 'AND';
    }
  }

  $query = new WP_Query($query_args);
  $total = (int) $query->found_posts;
  $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

  $images = [];
  foreach ($query->posts as $attachment) {
    $file_path = get_attached_file($attachment->ID);
    $file_size = ($file_path && file_exists($file_path)) ? filesize($file_path) : 0;

    $images[] = [
      'attachment_id'   => $attachment->ID,
      'filename'        => basename(get_attached_file($attachment->ID)),
      'mime_type'       => $attachment->post_mime_type,
      'file_size_bytes' => $file_size,
      'file_size_label' => wptools_imageconv_format_bytes($file_size),
      'thumbnail_html'  => wp_get_attachment_image($attachment->ID, 'thumbnail'),
    ];
  }

  // --- Re-sort by filesize in PHP when orderby=filesize ---
  if ($orderby === 'filesize') {
    usort($images, function ($a, $b) use ($order) {
      if ($order === 'ASC') {
        return $a['file_size_bytes'] - $b['file_size_bytes'];
      }
      return $b['file_size_bytes'] - $a['file_size_bytes'];
    });
  }

  wp_send_json_success([
    'images'      => $images,
    'total'       => $total,
    'page'        => $page,
    'total_pages' => $total_pages,
  ]);
}

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

function wptools_imageconv_ajax_process() {
  check_ajax_referer(WPTOOLS_IMAGECONV_NONCE, 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions.');
  }

  $attachment_id   = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
  $delete_original = isset($_POST['delete_original']) ? (bool) absint($_POST['delete_original']) : false;

  if ($attachment_id < 1) {
    wp_send_json_error('Invalid attachment ID.');
  }

  $allowed   = ['image/jpeg', 'image/png', 'image/webp'];
  $mime_type = get_post_mime_type($attachment_id);

  if (!in_array($mime_type, $allowed, true)) {
    wp_send_json_error('Attachment is not a supported image type.');
  }

  $file_path = get_attached_file($attachment_id);

  if (!$file_path || !file_exists($file_path)) {
    wp_send_json_error('File not found on disk.');
  }

  $orig_size = filesize($file_path);

  if ($mime_type === 'image/webp') {
    $result = wptools_imageconv_compress($file_path);
  } else {
    $result = wptools_imageconv_convert($file_path);
  }

  if (is_wp_error($result)) {
    wp_send_json_error([
      'attachment_id' => $attachment_id,
      'error'         => $result->get_error_message(),
    ]);
  }

  require_once ABSPATH . 'wp-admin/includes/image.php';

  $out_path   = $result['path'];
  $upload_dir = wp_upload_dir();
  $filetype   = wp_check_filetype(basename($out_path), null);

  $attachment_args = [
    'guid'           => $upload_dir['baseurl'] . '/' . _wp_relative_upload_path($out_path),
    'post_mime_type' => $filetype['type'],
    'post_title'     => preg_replace('/\.[^.]+$/', '', basename($out_path)),
    'post_content'   => '',
    'post_status'    => 'inherit',
  ];

  $new_id = wp_insert_attachment($attachment_args, $out_path, 0);

  if (is_wp_error($new_id) || $new_id < 1) {
    wp_send_json_error(['attachment_id' => $attachment_id, 'error' => 'Failed to register attachment.']);
  }

  $metadata = wp_generate_attachment_metadata($new_id, $out_path);
  wp_update_attachment_metadata($new_id, $metadata);

  $new_size      = filesize($out_path);
  $savings_bytes = $orig_size - $new_size;
  $savings_pct   = $orig_size > 0 ? round(($savings_bytes / $orig_size) * 100, 1) : 0;

  if ($delete_original) {
    wp_delete_attachment($attachment_id, true);
  }

  wp_send_json_success([
    'original_id'   => $attachment_id,
    'new_id'        => $new_id,
    'original_name' => basename($file_path),
    'output_name'   => basename($out_path),
    'original_size' => $orig_size,
    'output_size'   => $new_size,
    'savings_bytes' => $savings_bytes,
    'savings_pct'   => $savings_pct,
    'output_url'    => wp_get_attachment_url($new_id),
    'original_url'  => $delete_original ? null : wp_get_attachment_url($attachment_id),
  ]);
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

function wptools_imageconv_render_page() {
  ?>
  <div class="wrap wptools-imageconv-wrap">
    <h1><?php echo esc_html__('Image Converter', 'wptools'); ?></h1>
    <p class="wptools-imageconv-description">
      <?php echo esc_html__('Convert JPG/PNG images to WebP or compress existing WebP images.', 'wptools'); ?>
    </p>

    <div id="wptools-imageconv-app" class="wptools-imageconv-app">

      <div id="wptools-imageconv-filter-panel" class="wptools-imageconv-filter-panel">
        <div class="wptools-imageconv-filter-row">
          <div class="wptools-imageconv-filter-group">
            <label for="wptools-imageconv-search"><?php echo esc_html__('Search', 'wptools'); ?></label>
            <input
              type="text"
              id="wptools-imageconv-search"
              class="wptools-imageconv-filter-input"
              placeholder="<?php echo esc_attr__('Filename or title', 'wptools'); ?>"
            />
          </div>
          <div class="wptools-imageconv-filter-group">
            <label for="wptools-imageconv-type"><?php echo esc_html__('Format', 'wptools'); ?></label>
            <select id="wptools-imageconv-type" class="wptools-imageconv-filter-select">
              <option value=""><?php echo esc_html__('All Formats', 'wptools'); ?></option>
              <option value="jpg"><?php echo esc_html__('JPG', 'wptools'); ?></option>
              <option value="png"><?php echo esc_html__('PNG', 'wptools'); ?></option>
              <option value="webp"><?php echo esc_html__('WebP', 'wptools'); ?></option>
            </select>
          </div>
          <div class="wptools-imageconv-filter-group">
            <label for="wptools-imageconv-year"><?php echo esc_html__('Year', 'wptools'); ?></label>
            <select id="wptools-imageconv-year" class="wptools-imageconv-filter-select">
              <option value=""><?php echo esc_html__('All Years', 'wptools'); ?></option>
              <?php
              global $wpdb;
              $years = $wpdb->get_col(
                "SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                   AND post_status = 'inherit'
                   AND post_mime_type IN ('image/jpeg','image/png','image/webp')
                 ORDER BY post_date DESC"
              );
              foreach ($years as $y) {
                echo '<option value="' . esc_attr($y) . '">' . esc_html($y) . '</option>';
              }
              ?>
            </select>
          </div>
          <div class="wptools-imageconv-filter-group">
            <label for="wptools-imageconv-month"><?php echo esc_html__('Month', 'wptools'); ?></label>
            <select id="wptools-imageconv-month" class="wptools-imageconv-filter-select">
              <option value=""><?php echo esc_html__('All Months', 'wptools'); ?></option>
              <?php
              $month_names = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
              ];
              foreach ($month_names as $num => $name) {
                echo '<option value="' . esc_attr($num) . '">' . esc_html($name) . '</option>';
              }
              ?>
            </select>
          </div>
        </div>
      </div>

      <div class="wptools-imageconv-toolbar">
        <label class="wptools-imageconv-select-all-label">
          <input type="checkbox" id="wptools-imageconv-select-all" />
          <?php echo esc_html__('Select all / Deselect all', 'wptools'); ?>
        </label>
        <span id="wptools-imageconv-selected-count" class="wptools-imageconv-selected-count"></span>
        <button id="wptools-imageconv-filter-toggle" class="button wptools-imageconv-filter-toggle" type="button">
          <?php echo esc_html__('Filters', 'wptools'); ?>
        </button>
      </div>

      <div id="wptools-imageconv-list-wrap">
        <p id="wptools-imageconv-loading" class="wptools-imageconv-loading">
          <?php echo esc_html__('Loading images\xe2\x80\xa6', 'wptools'); ?>
        </p>
        <p id="wptools-imageconv-empty" class="wptools-imageconv-empty" style="display:none;">
          <?php echo esc_html__('No JPG, PNG, or WebP images found in the Media Library.', 'wptools'); ?>
        </p>
        <table id="wptools-imageconv-table" class="wp-list-table widefat fixed striped wptools-imageconv-table" style="display:none;">
          <thead>
            <tr>
              <th class="wptools-imageconv-col-cb check-column"><span class="screen-reader-text"><?php echo esc_html__('Select', 'wptools'); ?></span></th>
              <th class="wptools-imageconv-col-thumb"><?php echo esc_html__('Preview', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-name"><?php echo esc_html__('File Name', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-type"><?php echo esc_html__('Format', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-size"><?php echo esc_html__('Size', 'wptools'); ?></th>
            </tr>
          </thead>
          <tbody id="wptools-imageconv-tbody">
          </tbody>
        </table>
      </div>

      <div class="wptools-imageconv-actions" style="display:none;" id="wptools-imageconv-actions">
        <button id="wptools-imageconv-process-btn" class="button button-primary" disabled>
          <?php echo esc_html__('Convert / Compress', 'wptools'); ?>
        </button>
      </div>

      <div id="wptools-imageconv-results" style="display:none;">
        <h3><?php echo esc_html__('Results', 'wptools'); ?></h3>
        <table class="wp-list-table widefat fixed striped wptools-imageconv-results-table">
          <thead>
            <tr>
              <th class="wptools-imageconv-col-original-name"><?php echo esc_html__('Original File', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-output-name"><?php echo esc_html__('Output File', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-orig-size"><?php echo esc_html__('Before', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-out-size"><?php echo esc_html__('After', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-savings"><?php echo esc_html__('Savings', 'wptools'); ?></th>
              <th class="wptools-imageconv-col-status"><?php echo esc_html__('Status', 'wptools'); ?></th>
            </tr>
          </thead>
          <tbody id="wptools-imageconv-results-tbody">
          </tbody>
        </table>
      </div>

      <div id="wptools-imageconv-preview" class="wptools-imageconv-preview" style="display:none;">
        <h3><?php echo esc_html__('Before / After Preview', 'wptools'); ?></h3>
        <div class="wptools-imageconv-preview-pair">
          <div class="wptools-imageconv-preview-item">
            <p class="wptools-imageconv-preview-label"><?php echo esc_html__('Before', 'wptools'); ?></p>
            <img id="wptools-imageconv-preview-before" src="" alt="Before" width="0" height="0" />
          </div>
          <div class="wptools-imageconv-preview-item">
            <p class="wptools-imageconv-preview-label"><?php echo esc_html__('After', 'wptools'); ?></p>
            <img id="wptools-imageconv-preview-after" src="" alt="After" width="0" height="0" />
          </div>
        </div>
      </div>

      <div id="wptools-imageconv-modal" class="wptools-imageconv-modal-overlay" style="display:none;">
        <div class="wptools-imageconv-modal-dialog">
          <h2><?php echo esc_html__('Confirm Conversion', 'wptools'); ?></h2>
          <p id="wptools-imageconv-modal-summary"></p>
          <ul id="wptools-imageconv-modal-list"></ul>
          <label class="wptools-imageconv-modal-delete-label">
            <input type="checkbox" id="wptools-imageconv-delete-original" />
            <?php echo esc_html__('Delete original after conversion', 'wptools'); ?>
          </label>
          <div class="wptools-imageconv-modal-actions">
            <button id="wptools-imageconv-modal-confirm" class="button button-primary">
              <?php echo esc_html__('Proceed', 'wptools'); ?>
            </button>
            <button id="wptools-imageconv-modal-cancel" class="button">
              <?php echo esc_html__('Cancel', 'wptools'); ?>
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
  <?php
}
