<?php
/**
 * Plugin Name: Pickup Mtaani
 * Description: WooCommerce Pickup Mtaani Integration
 * Version: 1.0.0
 * Author: Eric Muthemba
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('PM_PATH', plugin_dir_path(__FILE__));
define('PM_URL', plugin_dir_url(__FILE__));

// Load core classes
require_once PM_PATH . 'includes/class-plugin.php';
require_once PM_PATH . 'includes/class-cron.php';

// Initialize plugin
add_action('plugins_loaded', function () {
    new PM_Plugin();
});

// Register activation/deactivation hooks
register_activation_hook(__FILE__, ['PM_Cron', 'activate']);
register_deactivation_hook(__FILE__, ['PM_Cron', 'deactivate']);