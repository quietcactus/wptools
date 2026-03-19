<?php
/**
 * Plugin Name: WPTools
 * Plugin URI:  #
 * Description: A suite of standalone WordPress admin tools.
 * Version:     2.0.0
 * Author:      Custom
 * License:     GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
  exit;
}

define('WPTOOLS_VERSION', '2.0.0');
define('WPTOOLS_DIR', plugin_dir_path(__FILE__));
define('WPTOOLS_URL', plugin_dir_url(__FILE__));

require_once WPTOOLS_DIR . 'includes/loader.php';
