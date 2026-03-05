<?php
if (!defined('ABSPATH')) {
    exit;
}

class PM_Admin_Dashboard
{
    public function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
    }

    public function register_widget()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        wp_add_dashboard_widget(
            'pickupmtaani_dashboard_widget',
            __('Pickup Mtaani - Shipments In Transit', 'pickup-mtaani'),
            [$this, 'render_widget']
        );
    }

    public function render_widget()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $orders = wc_get_orders([
            'limit'        => 15,
            'meta_key'     => '_pm_tracking_number',
            'meta_compare' => 'EXISTS',
            'status'       => ['processing', 'completed', 'on-hold'],
            'orderby'      => 'date',
            'order'        => 'DESC',
        ]);

        if (empty($orders)) {
            echo '<p>' . esc_html__('No active Pickup Mtaani shipments.', 'pickup-mtaani') . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead>
                <tr>
                    <th>' . esc_html__('Order', 'pickup-mtaani') . '</th>
                    <th>' . esc_html__('Customer', 'pickup-mtaani') . '</th>
                    <th>' . esc_html__('Tracking Number', 'pickup-mtaani') . '</th>
                    <th>' . esc_html__('Status', 'pickup-mtaani') . '</th>
                </tr>
              </thead><tbody>';

        foreach ($orders as $order) {
            $track = $order->get_meta('_pm_tracking_number');
            $status = $order->get_meta('_pm_last_status');

            if (strtolower((string) $status) === 'delivered') {
                continue;
            }

            $status_label = $status ? ucfirst((string) $status) : __('Pending Sync', 'pickup-mtaani');

            echo '<tr>';
            echo '<td>
                    <a href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">
                        #' . esc_html($order->get_id()) . '
                    </a>
                  </td>';
            echo '<td>' . esc_html($order->get_formatted_billing_full_name()) . '</td>';
            echo '<td>' . esc_html($track) . '</td>';
            echo '<td><strong>' . esc_html($status_label) . '</strong></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
