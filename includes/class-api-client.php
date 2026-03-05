<?php
if (!defined('ABSPATH')) {
    exit;
}

class PM_API_Client
{
    private $api_key;
    private $base_url;
    private $timeout = 30;

    public function __construct($api_key = '')
    {
        $settings = get_option('woocommerce_pickup_mtaani_settings', []);

        if (empty($settings)) {
            $settings = get_option('woocommerce_pickupmtaani_settings', []);
        }

        if (empty($settings)) {
            $settings = $this->get_first_shipping_instance_settings();
        }

        $saved_api_key = isset($settings['api_key']) ? sanitize_text_field($settings['api_key']) : '';
        $saved_base_url = isset($settings['base_url']) ? esc_url_raw($settings['base_url']) : '';

        $this->api_key = sanitize_text_field($api_key ?: $saved_api_key);

        if (!empty($saved_base_url)) {
            $this->base_url = rtrim($saved_base_url, '/');
        } else {
            $this->base_url = 'https://api.pickupmtaani.com';
        }
    }

    private function get_first_shipping_instance_settings()
    {
        global $wpdb;

        $option_name = $wpdb->get_var(
            "SELECT option_name
             FROM {$wpdb->options}
             WHERE option_name LIKE 'woocommerce_pickup_mtaani_%_settings'
             ORDER BY option_id ASC
             LIMIT 1"
        );

        if (empty($option_name)) {
            return [];
        }

        $settings = get_option($option_name, []);
        return is_array($settings) ? $settings : [];
    }

    private function is_configured()
    {
        return !empty($this->api_key) && !empty($this->base_url);
    }

    private function get($endpoint, $query = [])
    {
        if (!$this->is_configured()) {
            $this->log('API not configured for GET ' . $endpoint);
            return [];
        }

        $url = add_query_arg($this->sanitize_array($query), $this->base_url . $endpoint);

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/json',
                'apiKey' => $this->api_key,
            ],
            'timeout' => $this->timeout,
        ]);

        return $this->handle_response($response, 'GET ' . $endpoint);
    }

    private function post($endpoint, $payload = [], $query = [])
    {
        if (!$this->is_configured()) {
            $this->log('API not configured for POST ' . $endpoint);
            return [];
        }

        $url = add_query_arg($this->sanitize_array($query), $this->base_url . $endpoint);

        $response = wp_remote_post($url, [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'apiKey'       => $this->api_key,
            ],
            'body'    => wp_json_encode($this->sanitize_array($payload)),
            'timeout' => $this->timeout,
        ]);

        return $this->handle_response($response, 'POST ' . $endpoint);
    }

    private function handle_response($response, $context = '')
    {
        if (is_wp_error($response)) {
            $this->log($context . ' WP_Error: ' . $response->get_error_message());
            return [];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            $this->log($context . ' HTTP ' . $status_code . ' Response: ' . $body);
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->log($context . ' Invalid JSON returned.');
            return [];
        }

        return $decoded;
    }

    private function extract_error_reason($response)
    {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            if (!empty($decoded['message']) && is_string($decoded['message'])) {
                return $decoded['message'];
            }

            if (!empty($decoded['error']) && is_string($decoded['error'])) {
                return $decoded['error'];
            }

            if (!empty($decoded['detail']) && is_string($decoded['detail'])) {
                return $decoded['detail'];
            }

            if (!empty($decoded['data']['message']) && is_string($decoded['data']['message'])) {
                return $decoded['data']['message'];
            }

            if (!empty($decoded['errors'][0]['message']) && is_string($decoded['errors'][0]['message'])) {
                return $decoded['errors'][0]['message'];
            }
        }

        $response_message = wp_remote_retrieve_response_message($response);
        if (!empty($response_message)) {
            return $response_message;
        }

        return __('Unknown API error.', 'pickup-mtaani');
    }

    private function sanitize_array($data)
    {
        if (!is_array($data)) {
            return [];
        }

        $clean = [];

        foreach ($data as $key => $value) {
            $key = sanitize_key($key);

            if (is_array($value)) {
                $clean[$key] = $this->sanitize_array($value);
            } elseif (is_bool($value) || is_int($value) || is_float($value)) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }

    private function log($message)
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($message, ['source' => 'pickup-mtaani']);
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PickupMtaani] ' . $message);
        }
    }

    public function check_serviceability($destination)
    {
        $response = $this->get('/locations/doorstep-destinations', [
            'city' => isset($destination['city']) ? $destination['city'] : '',
            'country' => isset($destination['country']) ? $destination['country'] : '',
        ]);

        if (!empty($response['serviceable'])) {
            return (bool) $response['serviceable'];
        }

        if (!empty($response['data']) && is_array($response['data'])) {
            return true;
        }

        return false;
    }

    public function get_shipping_rate($payload)
    {
        $response = $this->post('/delivery-charge/doorstep-package', $payload);

        if (isset($response['amount'])) {
            return $response;
        }

        if (isset($response['data']['amount'])) {
            return ['amount' => $response['data']['amount']];
        }

        return [];
    }

    public function create_shipment($payload)
    {
        $response = $this->post('/packages/agent-agent', $payload);

        if (!empty($response['tracking_number'])) {
            return $response;
        }

        if (!empty($response['data']['tracking_number'])) {
            return [
                'tracking_number' => $response['data']['tracking_number'],
                'raw' => $response,
            ];
        }

        if (!empty($response['data']['track_id'])) {
            return [
                'tracking_number' => $response['data']['track_id'],
                'raw' => $response,
            ];
        }

        return [];
    }

    public function create_doorstep_shipment($payload)
    {
        $endpoint = apply_filters('pm_doorstep_shipment_endpoint', '/packages/doorstep-package');
        $response = $this->post((string) $endpoint, $payload);

        if (!empty($response['tracking_number'])) {
            return $response;
        }

        if (!empty($response['data']['tracking_number'])) {
            return [
                'tracking_number' => $response['data']['tracking_number'],
                'raw' => $response,
            ];
        }

        if (!empty($response['data']['track_id'])) {
            return [
                'tracking_number' => $response['data']['track_id'],
                'raw' => $response,
            ];
        }

        return [];
    }

    public function get_tracking_status($tracking_number)
    {
        $response = $this->get('/packages/track/' . rawurlencode((string) $tracking_number));

        if (!empty($response['status'])) {
            return $response;
        }

        if (!empty($response['data']['status'])) {
            return ['status' => $response['data']['status'], 'raw' => $response];
        }

        return [];
    }

    public function get_agents()
    {
        $response = $this->get('/locations/agents');

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        if (is_array($response)) {
            return $response;
        }

        return [];
    }

    public function validate_credentials()
    {
        if (!$this->is_configured()) {
            return [
                'valid' => false,
                'reason' => __('API key is required.', 'pickup-mtaani'),
            ];
        }

        $url = $this->base_url . '/locations/agents';
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/json',
                'apiKey' => $this->api_key,
            ],
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'reason' => $this->extract_error_reason($response),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            return [
                'valid' => true,
                'reason' => '',
            ];
        }

        return [
            'valid' => false,
            'reason' => $this->extract_error_reason($response),
        ];
    }
}
