<?php
if (!defined('ABSPATH')) {
    exit;
}

class PM_Cron
{
    const HOOK = 'pickupmtaani_hourly_sync';

    public function __construct()
    {
        add_action(self::HOOK, [$this, 'run']);
    }

    public static function activate()
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK);
        }
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public function run()
    {
        $tracking = new PM_Tracking();
        $tracking->sync_all_shipments();
    }
}
