<?php
namespace PickupMtaani;

if (!defined('ABSPATH')) exit;

class Cron {

    const HOOK        = 'pickupmtaani_hourly_sync';
    const LOCK_KEY    = 'pickupmtaani_cron_lock';
    const LOCK_TTL    = 50 * MINUTE_IN_SECONDS; // prevent overlap

    /**
     * Init hooks (called from main plugin bootstrap)
     */
    public static function init() {
        add_action(self::HOOK, [self::class, 'run']);
    }

    /**
     * These MUST be called from main plugin file
     */
    public static function activate() {

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 60, 'hourly', self::HOOK);
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::HOOK);
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Main runner with locking
     */
    public static function run() {

        if (self::is_locked()) {
            self::log('Skipped — already running.');
            return;
        }

        self::lock();
        self::log('Cron started.');

        try {
            self::refresh_destinations();
            self::sync_tracking();
        } catch (\Throwable $e) {
            self::log('Fatal: ' . $e->getMessage());
        }

        self::unlock();
        self::log('Cron finished.');
    }

    /**
     * Prevent concurrent execution
     */
    private static function is_locked() {
        return get_transient(self::LOCK_KEY);
    }

    private static function lock() {
        set_transient(self::LOCK_KEY, 1, self::LOCK_TTL);
    }

    private static function unlock() {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Refresh supported destinations cache
     */
    private static function refresh_destinations() {

        delete_transient('pickupmtaani_destinations');

        $api  = new \PickupMtaani\API_Client();
        $data = $api->get_destinations();

        if (empty($data['data'])) {
            self::log('No destinations returned.');
            return;
        }

        $destinations = [];

        foreach ($data['data'] as $d) {
            if (empty($d['name']) || empty($d['id'])) continue;

            $destinations[strtolower(sanitize_text_field($d['name']))] = (int) $d['id'];
        }

        set_transient('pickupmtaani_destinations', $destinations, 6 * HOUR_IN_SECONDS);

        self::log('Destinations refreshed: ' . count($destinations));
    }

    /**
     * Sync tracking in batches (prevents memory exhaustion)
     */
    private static function sync_tracking() {

        if (!class_exists('\PickupMtaani\Tracking')) {
            self::log('Tracking class missing.');
            return;
        }

        $page  = 1;
        $limit = 25;

        do {
            $processed = Tracking::sync_batch($page, $limit);
            $page++;
        } while ($processed === $limit);

        self::log('Tracking sync completed.');
    }

    /**
     * Logger (uses WooCommerce logger if available)
     */
    private static function log($message) {

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info($message, ['source' => 'pickup-mtaani']);
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PickupMtaani] ' . $message);
        }
    }
}