<?php
/**
 * Plugin Name: Pickup Mtaani for WooCommerce
 * Description: Pickup Mtaani integration with map selection, validation, package creation and tracking.
 * Version: 1.0.0
 * Developer: Eric Muthemba Kiarie
 * Email: emkiarie0@gmail.com
 */

use PickupMtaani\Cron;


if (!defined('ABSPATH')) exit;

define('PM_VERSION', '1.0.0');
define('PM_PATH', plugin_dir_path(__FILE__));
define('PM_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, ['PickupMtaani\Cron', 'activate']);
register_deactivation_hook(__FILE__, ['PickupMtaani\Cron', 'deactivate']);

/**
 * Ensure WooCommerce exists
 */
add_action('plugins_loaded', function () {

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>Pickup Mtaani requires WooCommerce.</p></div>';
        });
        return;
    }

    require_once PM_PATH . 'includes/class-plugin.php';

    PickupMtaani\Plugin::init();
});


add_action('plugins_loaded', function () {
    Cron::init();
});