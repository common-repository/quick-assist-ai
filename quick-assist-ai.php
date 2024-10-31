<?php
/*
Plugin Name: Quick Assist AI
Description: AI-powered assistant for troubleshooting issues, SEO recommendations, and ideas to enhance your website's performance and user experience
Version: 1.0
Author: vipulag, gagarwal
Plugin URI:        http://quickassistai.com/
Requires at least: 6.0
Requires PHP:      7.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:       quick-assist-ai
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('WPCO_VERSION', '1.0');
define('WPCO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPCO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPCO_PLUGIN_UPLOAD_DIR', plugin_dir_path(__FILE__) . 'uploads/');


// Include necessary files
require_once WPCO_PLUGIN_DIR . 'includes/class-wpco-init.php';
require_once WPCO_PLUGIN_DIR . 'includes/class-wpco-chat.php';

if (!function_exists('wp_filesystem')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

// Initialize the plugin
add_action('plugins_loaded', ['WPCO_Init', 'init']);
?>
