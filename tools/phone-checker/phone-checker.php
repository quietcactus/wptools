<?php
/**
 * Unlinked Phone Checker — Core
 *
 * Contains all logic for this tool. Loaded only by tool.php.
 * Functions are prefixed wptools_phone_ to avoid collisions.
 */

if (!defined('ABSPATH')) {
  exit;
}

// ---------------------------------------------------------------------------
// Assets
// ---------------------------------------------------------------------------

function wptools_phone_enqueue_assets($hook) {
  $expected = 'wptools_page_wptools-phone-checker';

  if ($hook !== $expected) {
    return;
  }

  wp_enqueue_style(
    'wptools-phone-styles',
    WPTOOLS_PHONE_URL . 'assets/phone-checker.css',
    [],
    WPTOOLS_VERSION
  );

  wp_enqueue_script(
    'wptools-phone-scripts',
    WPTOOLS_PHONE_URL . 'assets/phone-checker.js',
    ['jquery'],
    WPTOOLS_VERSION,
    true
  );

  wp_localize_script('wptools-phone-scripts', 'wptoolsPhoneData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce(WPTOOLS_PHONE_NONCE),
  ]);
}

// ---------------------------------------------------------------------------
// Phone patterns
// ---------------------------------------------------------------------------

function wptools_phone_get_patterns() {
  return [
    [
      'label'   => 'US/CA with country code: +1 (800) 555-1234',
      'pattern' => '/\+1[\s.\-]?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/',
    ],
    [
      'label'   => 'US/CA parentheses: (800) 555-1234',
      'pattern' => '/\(\d{3}\)[\s.\-]?\d{3}[\s.\-]?\d{4}/',
    ],
    [
      'label'   => 'US/CA dashes: 800-555-1234',
      'pattern' => '/\b\d{3}[\-\.]\d{3}[\-\.]\d{4}\b/',
    ],
    [
      'label'   => 'US/CA dots: 800.555.1234',
      'pattern' => '/\b\d{3}\.\d{3}\.\d{4}\b/',
    ],
    [
      'label'   => 'US/CA spaces: 800 555 1234',
      'pattern' => '/\b\d{3}\s\d{3}\s\d{4}\b/',
    ],
    [
      'label'   => 'Plain 10-digit: 8005551234',
      'pattern' => '/\b[2-9]\d{2}[2-9]\d{6}\b/',
    ],
    [
      'label'   => 'International E.164: +44 20 7946 0958',
      'pattern' => '/\+\d{1,3}[\s.\-]?\(?\d{1,4}\)?[\s.\-]?\d{1,4}[\s.\-]?\d{1,9}/',
    ],
  ];
}

// ---------------------------------------------------------------------------
// Scanning
// ---------------------------------------------------------------------------

function wptools_phone_get_post_types() {
  $types = get_post_types(['public' => true], 'objects');
  unset($types['attachment']);

  $result = [];
  foreach ($types as $slug => $obj) {
    $result[$slug] = $obj->labels->singular_name;
  }

  return $result;
}

function wptools_phone_find_in_content($html) {
  // Strip already-linked tel: numbers first.
  $stripped = preg_replace('/<a[^>]+href=["\']tel:[^"\']*["\'][^>]*>.*?<\/a>/is', '', $html);

  $text = wp_strip_all_tags($stripped);
  $text = preg_replace('/\s+/', ' ', $text);

  $matches = [];
  $seen    = [];

  foreach (wptools_phone_get_patterns() as $def) {
    if (!preg_match_all($def['pattern'], $text, $found)) {
      continue;
    }

    foreach ($found[0] as $match) {
      $match = trim($match);
      if ($match === '' || in_array($match, $seen, true)) {
        continue;
      }

      $seen[]    = $match;
      $matches[] = [
        'match'   => $match,
        'pattern' => $def['label'],
      ];
    }
  }

  return $matches;
}

function wptools_phone_run_scan($selected_types = []) {
  $all_types = array_keys(wptools_phone_get_post_types());

  $post_types = !empty($selected_types)
    ? array_values(array_intersect($selected_types, $all_types))
    : $all_types;

  if (empty($post_types)) {
    return [];
  }

  $results = [];
  $paged   = 1;

  do {
    $query = new WP_Query([
      'post_type'      => $post_types,
      'post_status'    => 'publish',
      'posts_per_page' => 100,
      'paged'          => $paged,
      'no_found_rows'  => false,
    ]);

    if (!$query->have_posts()) {
      break;
    }

    foreach ($query->posts as $post) {
      if (empty(trim($post->post_content))) {
        continue;
      }

      $phones = wptools_phone_find_in_content($post->post_content);
      if (empty($phones)) {
        continue;
      }

      $type_obj   = get_post_type_object($post->post_type);
      $type_label = $type_obj ? $type_obj->labels->singular_name : $post->post_type;

      foreach ($phones as $phone) {
        $results[] = [
          'post_id'   => $post->ID,
          'title'     => get_the_title($post->ID),
          'url'       => get_permalink($post->ID),
          'edit_url'  => get_edit_post_link($post->ID),
          'post_type' => $type_label,
          'match'     => $phone['match'],
          'pattern'   => $phone['pattern'],
        ];
      }
    }

    $max_pages = $query->max_num_pages;
    $paged++;
    wp_reset_postdata();

  } while ($paged <= $max_pages);

  return $results;
}

// ---------------------------------------------------------------------------
// AJAX handlers
// ---------------------------------------------------------------------------

function wptools_phone_ajax_run_scan() {
  check_ajax_referer(WPTOOLS_PHONE_NONCE, 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions.');
  }

  $selected = [];
  if (!empty($_POST['post_types']) && is_array($_POST['post_types'])) {
    $selected = array_map('sanitize_key', $_POST['post_types']);
  }

  delete_transient(WPTOOLS_PHONE_TRANSIENT);
  $results = wptools_phone_run_scan($selected);
  set_transient(WPTOOLS_PHONE_TRANSIENT, $results, WPTOOLS_PHONE_TRANSIENT_TTL);

  wp_send_json_success([
    'results' => $results,
    'count'   => count($results),
  ]);
}

function wptools_phone_ajax_clear_cache() {
  check_ajax_referer(WPTOOLS_PHONE_NONCE, 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions.');
  }

  delete_transient(WPTOOLS_PHONE_TRANSIENT);
  wp_send_json_success(['cleared' => true]);
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

function wptools_phone_render_page() {
  $cached     = get_transient(WPTOOLS_PHONE_TRANSIENT);
  $results    = is_array($cached) ? $cached : [];
  $post_types = wptools_phone_get_post_types();
  ?>
  <div class="wrap wptools-phone-wrap">
    <h1>Unlinked Phone Checker</h1>
    <p class="wptools-phone-description">
      Scans published post content for phone numbers not wrapped in a
      <code>&lt;a href="tel:"&gt;</code> link. Select post types to narrow the scan,
      or leave all unchecked to scan everything.
    </p>

    <div class="wptools-phone-options">
      <h3>Post Types to Scan</h3>
      <div class="wptools-phone-type-list">
        <?php foreach ($post_types as $slug => $label) { ?>
          <label class="wptools-phone-type-label">
            <input type="checkbox" class="wptools-phone-type-cb" value="<?php echo esc_attr($slug); ?>" />
            <?php echo esc_html($label); ?>
            <span class="wptools-phone-type-slug">(<?php echo esc_html($slug); ?>)</span>
          </label>
        <?php } ?>
      </div>
      <p class="wptools-phone-type-hint">Leave all unchecked to scan all post types.</p>
    </div>

    <div class="wptools-phone-toolbar">
      <button id="wptools-phone-scan" class="button button-primary">
        <?php echo $cached !== false ? 'Re-scan Now' : 'Run Scan'; ?>
      </button>
      <?php if ($cached !== false) { ?>
        <button id="wptools-phone-clear" class="button button-secondary">Clear Cache &amp; Reset</button>
        <span class="wptools-phone-cache-note">Results cached for 6 hours.</span>
      <?php } ?>
      <span id="wptools-phone-status" class="wptools-phone-status"></span>
    </div>

    <div id="wptools-phone-results">
      <?php if ($cached !== false) { ?>
        <?php wptools_phone_render_table($results); ?>
      <?php } else { ?>
        <div class="wptools-phone-empty">
          <p>No scan has been run yet. Click <strong>Run Scan</strong> to begin.</p>
        </div>
      <?php } ?>
    </div>
  </div>
  <?php
}

function wptools_phone_render_table($results) {
  if (empty($results)) { ?>
    <div class="notice notice-success inline wptools-phone-notice">
      <p>&#10003; No unlinked phone numbers found.</p>
    </div>
    <?php return;
  }

  $count          = count($results);
  $posts_affected = count(array_unique(array_column($results, 'post_id')));
  ?>
  <div class="notice notice-warning inline wptools-phone-notice">
    <p>
      Found <strong><?php echo esc_html($count); ?> unlinked phone number<?php echo $count !== 1 ? 's' : ''; ?></strong>
      across <strong><?php echo esc_html($posts_affected); ?> post<?php echo $posts_affected !== 1 ? 's' : ''; ?></strong>.
    </p>
  </div>

  <div class="wptools-phone-table-controls">
    <input type="text" id="wptools-phone-filter" class="regular-text" placeholder="Filter by title, phone, or post type&hellip;" />
    <button id="wptools-phone-export" class="button">Export CSV</button>
  </div>

  <table class="wp-list-table widefat fixed striped wptools-phone-table" id="wptools-phone-table">
    <thead>
      <tr>
        <th class="col-title" data-col="0">Page / Post Title</th>
        <th class="col-type"  data-col="1">Post Type</th>
        <th class="col-phone" data-col="2">Unlinked Phone</th>
        <th class="col-fmt"   data-col="3">Format Matched</th>
        <th class="col-actions">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($results as $row) { ?>
        <tr>
          <td class="col-title">
            <strong><?php echo esc_html($row['title']); ?></strong>
            <div class="row-actions">
              <span><a href="<?php echo esc_url($row['url']); ?>" target="_blank">View</a></span>
            </div>
          </td>
          <td class="col-type">
            <span class="wptools-phone-badge"><?php echo esc_html($row['post_type']); ?></span>
          </td>
          <td class="col-phone">
            <code><?php echo esc_html($row['match']); ?></code>
          </td>
          <td class="col-fmt">
            <span class="wptools-phone-fmt"><?php echo esc_html($row['pattern']); ?></span>
          </td>
          <td class="col-actions">
            <?php if ($row['edit_url']) { ?>
              <a href="<?php echo esc_url($row['edit_url']); ?>" class="button button-small" target="_blank">Edit Post</a>
            <?php } ?>
          </td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
  <?php
}
