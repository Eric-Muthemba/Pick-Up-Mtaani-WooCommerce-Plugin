<?php
if (!defined('ABSPATH')) {
    exit;
}

class PM_Shipping_Method extends WC_Shipping_Method
{
    private $logger;

    public function __construct($instance_id = 0)
    {
        $this->id                 = 'pickup_mtaani';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Pickup Mtaani', 'pickup-mtaani');
        $this->method_description = __('Ship orders to a Pickup Mtaani agent location.', 'pickup-mtaani');

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );

        $this->logger = wc_get_logger();

        $this->init();
    }

    public function init()
    {
        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->enabled     = $this->get_option('enabled');
        $this->api_key     = trim($this->get_option('api_key'));
        $this->environment = sanitize_text_field($this->get_option('environment'));

        add_action(
            'woocommerce_update_options_shipping_' . $this->id,
            array($this, 'process_admin_options')
        );
    }

    public function init_form_fields()
    {
        $this->instance_form_fields = array(

            'enabled' => array(
                'title'   => __('Enable', 'pickup-mtaani'),
                'type'    => 'checkbox',
                'label'   => __('Enable Pickup Mtaani Shipping', 'pickup-mtaani'),
                'default' => 'yes',
            ),

            'title' => array(
                'title'   => __('Method Title', 'pickup-mtaani'),
                'type'    => 'text',
                'default' => __('Pickup Mtaani', 'pickup-mtaani'),
            ),

            'api_key' => array(
                'title' => __('API Key', 'pickup-mtaani'),
                'type'  => 'password',
            ),

            'environment' => array(
                'title'   => __('Environment', 'pickup-mtaani'),
                'type'    => 'select',
                'default' => 'production',
                'options' => array(
                    'production' => __('Production', 'pickup-mtaani'),
                    'sandbox'    => __('Sandbox', 'pickup-mtaani'),
                ),
            ),
        );
    }

    /**
     * Main shipping calculation
     */
    public function calculate_shipping($package = array())
    {
        if ($this->enabled !== 'yes' || empty($this->api_key)) {
            return;
        }

        $destination = $package['destination'];
        if (empty($destination['city']) || empty($destination['country'])) {
            return;
        }

        $cache_key = 'pm_rate_' . md5(json_encode($destination));

        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->add_rate($cached);
            return;
        }

        try {
            $client = new PM_API_Client($this->api_key, $this->environment);

            // Check serviceability
            if (!$client->check_serviceability($destination)) {
                return; // hide method silently
            }

            $rate_response = $client->get_shipping_rate(array(
                'destination_city' => $destination['city'],
                'items' => $this->get_items_for_rate($package),
            ));

            if (empty($rate_response['amount'])) {
                return;
            }

            $rate = array(
                'id'    => $this->id,
                'label' => $this->title,
                'cost'  => floatval($rate_response['amount']),
                'meta_data' => array('pickup_mtaani' => true),
            );

            // Cache for 10 minutes
            set_transient($cache_key, $rate, 10 * MINUTE_IN_SECONDS);

            $this->add_rate($rate);

        } catch (Exception $e) {

            $this->logger->error(
                'Pickup Mtaani error: ' . $e->getMessage(),
                array('source' => 'pickup-mtaani')
            );

            return;
        }
    }

    private function get_items_for_rate($package)
    {
        $items = array();

        foreach ($package['contents'] as $item) {

            $product = $item['data'];

            $items[] = array(
                'name'     => $product->get_name(),
                'quantity' => (int) $item['quantity'],
                'price'    => (float) $product->get_price(),
                'weight'   => (float) ($product->get_weight() ?: 1),
            );
        }

        return $items;
    }
}