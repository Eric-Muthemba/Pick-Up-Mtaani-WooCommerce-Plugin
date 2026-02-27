<?php
namespace PickupMtaani;

if (!defined('ABSPATH')) exit;

class API_Client {

    private $api_key;
    private $base_url;
    private $timeout = 20;

    public function __construct() {

        $settings = get_option('woocommerce_pickupmtaani_settings', []);

        $this->api_key  = isset($settings['api_key']) ? sanitize_text_field($settings['api_key']) : '';
        $this->base_url = isset($settings['base_url'])
            ? rtrim(esc_url_raw($settings['base_url']), '/')
            : '';

    }

    /**
     * Ensure configuration exists
     */
    private function is_configured() {
        return !empty($this->api_key) && !empty($this->base_url);
    }

    /**
     * Perform GET request
     */
    public function get($endpoint, $query = []) {

        if (!$this->is_configured()) {
            $this->log('API not configured.');
            return null;
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

    /**
     * Perform POST request
     */
    public function post($endpoint, $payload = [], $query = []) {

        if (!$this->is_configured()) {
            $this->log('API not configured.');
            return null;
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

    /**
     * Handle API response safely
     */
    private function handle_response($response, $context = '') {

        if (is_wp_error($response)) {
            $this->log($context . ' WP_Error: ' . $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            $this->log($context . ' HTTP ' . $status_code . ' Response: ' . $body);
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log($context . ' Invalid JSON returned.');
            return null;
        }

        return $decoded;
    }

    /**
     * Sanitize nested arrays before sending to API
     */
    private function sanitize_array($data) {

        if (!is_array($data)) return [];

        $clean = [];

        foreach ($data as $key => $value) {

            $key = sanitize_key($key);

            if (is_array($value)) {
                $clean[$key] = $this->sanitize_array($value);
            } else {
                $clean[$key] = sanitize_text_field((string)$value);
            }
        }

        return $clean;
    }

    /**
     * Lightweight logger (can later be swapped for Monolog)
     */
    private function log($message) {

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PickupMtaani] ' . $message);
        }
    }

    /**
     * Convenience helper: Get Agents
     */
    public function get_agents() {
        return $this->get('/locations/agents');
    }

    /**
     * Convenience helper: Get Supported Destinations
     */
    public function get_destinations() {
        return $this->get('/locations/doorstep-destinations');
    }

    /**
     * Convenience helper: Get Delivery Price
     */
    public function get_delivery_price($sender_id, $destination_id) {

        return $this->get('/delivery-charge/doorstep-package', [
            'senderAgentID' => $sender_id,
            'doorstepDestinationID' => $destination_id,
        ]);
    }

    /**
     * Convenience helper: Create Package
     */
    public function create_package($business_id, $payload) {

        return $this->post('/packages/agent-agent', $payload, [
            'b_id' => $business_id
        ]);
    }

    /**
     * Convenience helper: Track Package
     */
    public function track_package($track_id) {
        return $this->get('/packages/track/' . urlencode($track_id));
    }
}