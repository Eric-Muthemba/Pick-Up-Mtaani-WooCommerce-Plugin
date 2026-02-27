<?php
if (!defined('ABSPATH')) {
    exit;
}

class PM_Plugin
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    /**
     * Initialize plugin after WooCommerce loads
     */
    public function init()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        $this->includes();
        $this->init_hooks();
    }

    /**
     * Load required classes
     */
    private function includes()
    {
        require_once PM_PLUGIN_PATH . 'includes/class-api-client.php';
        require_once PM_PLUGIN_PATH . 'includes/class-shipping-method.php';
        require_once PM_PLUGIN_PATH . 'includes/class-tracking.php';
        require_once PM_PLUGIN_PATH . 'includes/class-cron.php';
        require_once PM_PLUGIN_PATH . 'includes/class-admin-dashboard.php';
    }

    /**
     * Register hooks
     */
    private function init_hooks()
    {
        // Register WooCommerce Shipping Method
        add_action('woocommerce_shipping_init', [$this, 'load_shipping_method']);
        add_filter('woocommerce_shipping_methods', [$this, 'register_shipping_method']);

        // Init tracking + dashboard + cron
        new PM_Tracking();
        new PM_Admin_Dashboard();
        new PM_Cron();

        // Create shipment after order is paid
        add_action('woocommerce_order_status_processing', [$this, 'create_pickup_shipment']);
    }

    /**
     * Load shipping class
     */
    public function load_shipping_method()
    {
        require_once PM_PLUGIN_PATH . 'includes/class-shipping-method.php';
    }

    /**
     * Register shipping method with WooCommerce
     */
    public function register_shipping_method($methods)
    {
        $methods['pickup_mtaani'] = 'PM_Shipping_Method';
        return $methods;
    }

    /**
     * Create shipment in Pickup Mtaani once order is confirmed
     */
    public function create_pickup_shipment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Ensure customer selected Pickup Mtaani
        $shipping_methods = $order->get_shipping_methods();
        $selected = false;

        foreach ($shipping_methods as $method) {
            if ($method->get_method_id() === 'pickup_mtaani') {
                $selected = true;
                break;
            }
        }

        if (!$selected) {
            return;
        }

        // Prevent duplicate creation
        if (get_post_meta($order_id, '_pm_tracking_number', true)) {
            return;
        }

        $client = new PM_API_Client();

        $payload = [
            'order_id' => $order->get_id(),
            'customer' => [
                'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'phone' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
            ],
            'destination' => [
                'city'    => $order->get_shipping_city(),
                'address' => $order->get_shipping_address_1(),
            ],
            'items' => [],
        ];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $payload['items'][] = [
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price'    => $product ? $product->get_price() : 0,
            ];
        }

        $response = $client->create_shipment($payload);

        if (!empty($response['tracking_number'])) {

            /**
             * Trigger tracking storage hook
             */
            do_action('pm_shipment_created', $order_id, $response);

            $order->add_order_note(
                'Pickup Mtaani shipment created. Tracking: ' . $response['tracking_number']
            );
        } else {
            $order->add_order_note('Pickup Mtaani shipment creation failed.');
        }
    }

    /**
     * Admin notice if WooCommerce missing
     */
    public function woocommerce_missing_notice()
    {
        echo '<div class="error"><p><strong>Pickup Mtaani Shipping</strong> requires WooCommerce to be installed and active.</p></div>';
    }
}