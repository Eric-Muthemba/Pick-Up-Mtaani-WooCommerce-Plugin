<?php
if (!defined('ABSPATH')) {
    exit;
}

class PM_Plugin
{
    private $map_ui_rendered = false;

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
        require_once PM_PATH . 'includes/class-api-client.php';
        require_once PM_PATH . 'includes/class-shipping-method.php';
        require_once PM_PATH . 'includes/class-tracking.php';
        require_once PM_PATH . 'includes/class-cron.php';
        require_once PM_PATH . 'includes/class-admin-dashboard.php';
    }

    /**
     * Register hooks
     */
    private function init_hooks()
    {
        add_action('woocommerce_shipping_init', [$this, 'load_shipping_method']);
        add_filter('woocommerce_shipping_methods', [$this, 'register_shipping_method']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_assets']);
        add_action('woocommerce_after_order_notes', [$this, 'render_pickup_map_ui']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'render_pickup_map_ui']);
        add_action('woocommerce_review_order_after_shipping', [$this, 'render_pickup_map_ui']);
        add_action('woocommerce_checkout_process', [$this, 'validate_pickup_agent_selection']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_pickup_agent_selection'], 10, 2);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        new PM_Tracking();
        new PM_Admin_Dashboard();
        new PM_Cron();

        add_action('woocommerce_order_status_processing', [$this, 'create_pickup_shipment']);
    }

    /**
     * Load shipping class
     */
    public function load_shipping_method()
    {
        require_once PM_PATH . 'includes/class-shipping-method.php';
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

        if (get_post_meta($order_id, '_pm_tracking_number', true)) {
            return;
        }

        $client = new PM_API_Client();

        $delivery_option = (string) $order->get_meta('_pm_delivery_option', true);
        if ($delivery_option !== 'doorstep_dropoff') {
            $delivery_option = 'pickup_agent';
        }

        $payload = [
            'order_id' => $order->get_id(),
            'delivery_option' => $delivery_option,
            'customer' => [
                'name'  => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'phone' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
            ],
            'destination' => [
                'city'    => $order->get_shipping_city(),
                'address' => $order->get_shipping_address_1(),
            ],
            'items' => [],
        ];

        if ($delivery_option === 'pickup_agent') {
            $pickup_agent_id = (string) $order->get_meta('_pm_pickup_agent_id', true);
            if ($pickup_agent_id !== '') {
                $payload['pickup_agent_id'] = $pickup_agent_id;
            }
        } else {
            $payload['delivery_address'] = [
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'country' => $order->get_shipping_country(),
                'postcode' => $order->get_shipping_postcode(),
            ];
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $payload['items'][] = [
                'name'     => $item->get_name(),
                'quantity' => (int) $item->get_quantity(),
                'price'    => $product ? (float) $product->get_price() : 0,
            ];
        }

        if ($delivery_option === 'doorstep_dropoff') {
            $response = $client->create_doorstep_shipment($payload);
        } else {
            $response = $client->create_shipment($payload);
        }

        if (!empty($response['tracking_number'])) {
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

    public function enqueue_checkout_assets()
    {
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
            return;
        }

        $google_maps_api_key = $this->get_active_pickup_setting('google_maps_api_key');
        if (empty($google_maps_api_key)) {
            return;
        }

        wp_enqueue_script(
            'pickup-mtaani-google-maps',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($google_maps_api_key),
            [],
            null,
            true
        );

        wp_enqueue_script(
            'pickup-mtaani-map',
            PM_URL . 'Assests/map.js',
            ['jquery', 'pickup-mtaani-google-maps'],
            '1.0.0',
            true
        );

        wp_localize_script('pickup-mtaani-map', 'pickupMtaaniMapConfig', [
            'restUrl' => esc_url_raw(rest_url('pickupmtaani/v1/agents')),
            'defaultCenter' => [
                'lat' => -1.286389,
                'lng' => 36.817223,
            ],
        ]);
    }

    public function render_pickup_map_ui($checkout = null)
    {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }

        if ($this->map_ui_rendered) {
            return;
        }

        if (!class_exists('WC_Checkout') || !($checkout instanceof WC_Checkout)) {
            $checkout = WC()->checkout();
        }

        if (!$checkout) {
            return;
        }

        $this->map_ui_rendered = true;

        echo '<div id="pickupmtaani_checkout_map_wrap" style="margin-top:20px;">';
        echo '<h3>' . esc_html__('Pickup Mtaani Delivery Option', 'pickup-mtaani') . '</h3>';
        echo '<p>' . esc_html__('Choose how you want to receive your order.', 'pickup-mtaani') . '</p>';

        $delivery_option_value = $checkout->get_value('pickupmtaani_delivery_option');
        if ($delivery_option_value !== 'doorstep_dropoff') {
            $delivery_option_value = 'pickup_agent';
        }

        woocommerce_form_field('pickupmtaani_delivery_option', [
            'type'    => 'radio',
            'label'   => __('Delivery Option', 'pickup-mtaani'),
            'options' => [
                'pickup_agent' => __('Pickup from Agent', 'pickup-mtaani'),
                'doorstep_dropoff' => __('Doorstep Dropoff', 'pickup-mtaani'),
            ],
            'required' => true,
            'class' => ['form-row-wide'],
        ], $delivery_option_value);

        echo '<div id="pickupmtaani_agent_picker_wrap">';
        echo '<p>' . esc_html__('Choose your preferred pickup point on the map.', 'pickup-mtaani') . '</p>';
        echo '<div id="pickupmtaani_map" style="height:300px; border:1px solid #dcdcde; border-radius:4px;"></div>';

        woocommerce_form_field('pickupmtaani_agent', [
            'type'     => 'hidden',
            'required' => false,
        ], $checkout->get_value('pickupmtaani_agent'));

        woocommerce_form_field('pickupmtaani_display', [
            'type'        => 'text',
            'label'       => __('Selected Pickup Agent', 'pickup-mtaani'),
            'required'    => false,
            'custom_attributes' => ['readonly' => 'readonly'],
        ], $checkout->get_value('pickupmtaani_display'));
        echo '</div>';

        echo '</div>';
    }

    public function validate_pickup_agent_selection()
    {
        if (!$this->is_pickup_mtaani_selected_at_checkout()) {
            return;
        }

        $delivery_option = isset($_POST['pickupmtaani_delivery_option'])
            ? sanitize_text_field(wp_unslash($_POST['pickupmtaani_delivery_option']))
            : 'pickup_agent';

        if ($delivery_option === 'pickup_agent' && empty($_POST['pickupmtaani_agent'])) {
            wc_add_notice(__('Please select a pickup agent on the map.', 'pickup-mtaani'), 'error');
        }
    }

    public function save_pickup_agent_selection($order, $data)
    {
        $delivery_option = !empty($_POST['pickupmtaani_delivery_option'])
            ? sanitize_text_field(wp_unslash($_POST['pickupmtaani_delivery_option']))
            : 'pickup_agent';

        if ($delivery_option !== 'doorstep_dropoff') {
            $delivery_option = 'pickup_agent';
        }

        $order->update_meta_data('_pm_delivery_option', $delivery_option);

        if (!empty($_POST['pickupmtaani_agent'])) {
            $order->update_meta_data('_pm_pickup_agent_id', sanitize_text_field(wp_unslash($_POST['pickupmtaani_agent'])));
        } else {
            $order->delete_meta_data('_pm_pickup_agent_id');
        }

        if (!empty($_POST['pickupmtaani_display'])) {
            $order->update_meta_data('_pm_pickup_agent_name', sanitize_text_field(wp_unslash($_POST['pickupmtaani_display'])));
        } else {
            $order->delete_meta_data('_pm_pickup_agent_name');
        }
    }

    public function register_rest_routes()
    {
        register_rest_route('pickupmtaani/v1', '/agents', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_get_agents'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_get_agents($request)
    {
        $client = new PM_API_Client();
        $agents = $client->get_agents();

        if (empty($agents) || !is_array($agents)) {
            return rest_ensure_response([]);
        }

        $normalized = [];

        foreach ($agents as $agent) {
            if (!is_array($agent)) {
                continue;
            }

            $id = isset($agent['id']) ? $agent['id'] : (isset($agent['agentID']) ? $agent['agentID'] : '');
            $name = isset($agent['name']) ? $agent['name'] : (isset($agent['agentName']) ? $agent['agentName'] : '');
            $lat = isset($agent['lat']) ? $agent['lat'] : (isset($agent['latitude']) ? $agent['latitude'] : '');
            $lng = isset($agent['lng']) ? $agent['lng'] : (isset($agent['longitude']) ? $agent['longitude'] : '');

            if ($id === '' || $name === '' || $lat === '' || $lng === '') {
                continue;
            }

            $normalized[] = [
                'id' => sanitize_text_field((string) $id),
                'name' => sanitize_text_field((string) $name),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }

        return rest_ensure_response($normalized);
    }

    private function is_pickup_mtaani_selected_at_checkout()
    {
        if (!empty($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {
            $selected = (string) reset($_POST['shipping_method']);
            return strpos($selected, 'pickup_mtaani') === 0;
        }

        if (!function_exists('WC') || !WC()->session) {
            return false;
        }

        $chosen = WC()->session->get('chosen_shipping_methods');
        if (empty($chosen) || !is_array($chosen)) {
            return false;
        }

        $selected = (string) reset($chosen);
        return strpos($selected, 'pickup_mtaani') === 0;
    }

    private function get_active_pickup_setting($setting_key)
    {
        if (!function_exists('WC') || !WC()->session) {
            return '';
        }

        $chosen = WC()->session->get('chosen_shipping_methods');
        if (empty($chosen) || !is_array($chosen)) {
            return '';
        }

        $selected = (string) reset($chosen);
        if (strpos($selected, 'pickup_mtaani') !== 0) {
            return '';
        }

        $instance_id = 0;
        if (strpos($selected, ':') !== false) {
            $parts = explode(':', $selected);
            $instance_id = isset($parts[1]) ? absint($parts[1]) : 0;
        }

        if ($instance_id > 0) {
            $settings = get_option('woocommerce_pickup_mtaani_' . $instance_id . '_settings', []);
            if (is_array($settings) && !empty($settings[$setting_key])) {
                return sanitize_text_field($settings[$setting_key]);
            }
        }

        return '';
    }
}
