<?php
namespace PickupMtaani;

if (!defined('ABSPATH')) exit;

class Admin_Dashboard {

    public static function init() {
        add_action('wp_dashboard_setup', [self::class, 'register_widget']);
    }

    /**
     * Register Dashboard Widget
     */
    public static function register_widget() {

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        wp_add_dashboard_widget(
            'pickupmtaani_dashboard_widget',
            __('Pickup Mtaani – Shipments In Transit', 'pickup-mtaani'),
            [self::class, 'render_widget']
        );
    }

    /**
     * Render Widget UI
     */
    public static function render_widget() {

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        self::handle_manual_refresh();

        $orders = wc_get_orders([
            'limit'        => 15,
            'meta_key'     => '_pickupmtaani_track_id',
            'meta_compare' => 'EXISTS',
            'status'       => ['processing','completed'],
            'orderby'      => 'date',
            'order'        => 'DESC',
        ]);

        if (empty($orders)) {
            echo '<p>'.esc_html__('No active Pickup Mtaani shipments.', 'pickup-mtaani').'</p>';
            self::render_refresh_button();
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead>
                <tr>
                    <th>'.esc_html__('Order','pickup-mtaani').'</th>
                    <th>'.esc_html__('Customer','pickup-mtaani').'</th>
                    <th>'.esc_html__('Tracking ID','pickup-mtaani').'</th>
                    <th>'.esc_html__('Status','pickup-mtaani').'</th>
                </tr>
              </thead><tbody>';

        foreach ($orders as $order) {

            $track   = $order->get_meta('_pickupmtaani_track_id');
            $status  = $order->get_meta('_pickupmtaani_last_status');
            $stalled = $order->get_meta('_pickupmtaani_stalled');

            if (strtolower($status) === 'delivered') {
                continue;
            }

            $status_label = $status ? ucfirst($status) : __('Pending Sync','pickup-mtaani');

            $color = ($stalled === 'yes') ? '#d63638' : '#2271b1';

            echo '<tr>';
            echo '<td>
                    <a href="'.esc_url(admin_url('post.php?post='.$order->get_id().'&action=edit')).'">
                        #'.esc_html($order->get_id()).'
                    </a>
                  </td>';

            echo '<td>'.esc_html($order->get_formatted_billing_full_name()).'</td>';
            echo '<td>'.esc_html($track).'</td>';
            echo '<td><strong style="color:'.$color.'">'.esc_html($status_label).'</strong></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        self::render_refresh_button();
    }

    /**
     * Manual Refresh Handler
     */
    private static function handle_manual_refresh() {

        if (!isset($_POST['pickupmtaani_refresh'])) {
            return;
        }

        if (!isset($_POST['_wpnonce']) ||
            !wp_verify_nonce($_POST['_wpnonce'], 'pickupmtaani_refresh_nonce')) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (class_exists('\PickupMtaani\Tracking')) {
            \PickupMtaani\Tracking::sync();
        }

        echo '<div class="updated"><p>'.
             esc_html__('Shipment data refreshed.', 'pickup-mtaani').
             '</p></div>';
    }

    /**
     * Refresh Button
     */
    private static function render_refresh_button() {
        echo '<form method="post" style="margin-top:12px;">';
        wp_nonce_field('pickupmtaani_refresh_nonce');
        echo '<input type="submit"
                     name="pickupmtaani_refresh"
                     class="button button-primary"
                     value="'.esc_attr__('Refresh Now','pickup-mtaani').'" />';
        echo '</form>';
    }
}