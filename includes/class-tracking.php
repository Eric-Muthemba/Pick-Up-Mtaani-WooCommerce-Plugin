<?php
if (!defined('ABSPATH')) {
    exit;
}

class PM_Tracking
{
    public function __construct()
    {
        // Show tracking in admin order page
        add_action('add_meta_boxes', [$this, 'add_tracking_metabox']);

        // Show tracking to customer
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_customer_tracking']);

        // Save tracking when shipment is created
        add_action('pm_shipment_created', [$this, 'store_tracking'], 10, 2);
    }

    /**
     * Store tracking number in order meta
     */
    public function store_tracking($order_id, $shipment)
    {
        if (empty($shipment['tracking_number'])) {
            return;
        }

        update_post_meta($order_id, '_pm_tracking_number', sanitize_text_field($shipment['tracking_number']));
        update_post_meta($order_id, '_pm_last_status', 'created');
    }

    /**
     * Add admin metabox
     */
    public function add_tracking_metabox()
    {
        add_meta_box(
            'pm_tracking_box',
            __('Pickup Mtaani Tracking', 'pickup-mtaani'),
            [$this, 'render_tracking_metabox'],
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Render admin tracking UI
     */
    public function render_tracking_metabox($post)
    {
        $order_id = $post->ID;
        $tracking = get_post_meta($order_id, '_pm_tracking_number', true);
        $status   = get_post_meta($order_id, '_pm_last_status', true);

        if (!$tracking) {
            echo '<p>No shipment created yet.</p>';
            return;
        }

        echo '<p><strong>Tracking Number:</strong><br>' . esc_html($tracking) . '</p>';
        echo '<p><strong>Status:</strong><br>' . esc_html(ucfirst($status)) . '</p>';

        echo '<a class="button" href="' . esc_url(add_query_arg([
            'pm_refresh_tracking' => $order_id
        ])) . '">Refresh Status</a>';
    }

    /**
     * Show tracking to customer (My Account page)
     */
    public function display_customer_tracking($order)
    {
        $tracking = get_post_meta($order->get_id(), '_pm_tracking_number', true);
        $status   = get_post_meta($order->get_id(), '_pm_last_status', true);

        if (!$tracking) {
            return;
        }

        echo '<section class="pm-tracking">';
        echo '<h2>Delivery Tracking</h2>';
        echo '<p><strong>Tracking Number:</strong> ' . esc_html($tracking) . '</p>';
        echo '<p><strong>Status:</strong> ' . esc_html(ucfirst($status)) . '</p>';
        echo '</section>';
    }

    /**
     * Called by WP-Cron to sync shipment statuses hourly
     */
    public function sync_all_shipments()
    {
        $orders = get_posts([
            'post_type'  => 'shop_order',
            'meta_query' => [
                [
                    'key'     => '_pm_tracking_number',
                    'compare' => 'EXISTS'
                ]
            ],
            'posts_per_page' => -1
        ]);

        if (!$orders) {
            return;
        }

        $client = new PM_API_Client();

        foreach ($orders as $order_post) {
            $this->sync_single_order($order_post->ID, $client);
        }
    }

    /**
     * Sync one shipment
     */
    public function sync_single_order($order_id, $client = null)
    {
        $tracking = get_post_meta($order_id, '_pm_tracking_number', true);
        if (!$tracking) {
            return;
        }

        if (!$client) {
            $client = new PM_API_Client();
        }

        $response = $client->get_tracking_status($tracking);

        if (empty($response['status'])) {
            return;
        }

        $new_status = sanitize_text_field($response['status']);
        $old_status = get_post_meta($order_id, '_pm_last_status', true);

        if ($new_status === $old_status) {
            return;
        }

        update_post_meta($order_id, '_pm_last_status', $new_status);

        $order = wc_get_order($order_id);

        // Map Pickup Mtaani status → WooCommerce status
        switch (strtolower($new_status)) {

            case 'in_transit':
            case 'collected':
                $order->update_status('processing', 'Shipment is in transit.');
                break;

            case 'delivered':
                $order->update_status('completed', 'Delivered by Pickup Mtaani.');
                break;

            case 'failed':
                $order->update_status('on-hold', 'Delivery failed.');
                break;
        }

        $order->add_order_note('Pickup Mtaani status updated to: ' . $new_status);
    }
}