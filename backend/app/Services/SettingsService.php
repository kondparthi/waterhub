<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    /** Defaults used by the seeder and as fallbacks. */
    public const DEFAULTS = [
        'business_name' => ['Water Tanker Services', 'string', 'Business name', true],
        'business_phone' => ['', 'string', 'Business contact number', true],
        'currency' => ['INR', 'string', 'Currency', true],
        'timezone' => ['Asia/Kolkata', 'string', 'Business timezone', true],
        'tax_percent' => ['0', 'decimal', 'Tax % applied on quotations', true],
        'require_customer_quote_acceptance' => ['true', 'bool', 'Customer must accept quotation before confirmation', true],
        'customer_cancellable_statuses' => ['["new","under_review","quoted","confirmed","assigned"]', 'json', 'Statuses in which customer can cancel', true],
        'cancellation_fee_after_confirmation' => ['0', 'decimal', 'Cancellation fee (₹) once booking is confirmed', true],
        'driver_response_timeout_minutes' => ['15', 'int', 'Minutes a driver has to accept an assignment', false],
        'gps_update_interval_seconds' => ['12', 'int', 'Driver GPS push interval (s)', true],
        'map_refresh_seconds' => ['8', 'int', 'Tracking map refresh interval (s)', true],
        'gps_stale_after_seconds' => ['60', 'int', 'Mark GPS stale after (s)', true],
        'gps_max_speed_kmph' => ['120', 'int', 'Reject GPS jumps faster than (km/h)', false],
        'gps_retention_days' => ['90', 'int', 'Keep GPS history for (days)', false],
        'default_service_radius_km' => ['25', 'int', 'Default tanker service radius (km)', false],
        'otp_verification_enabled' => ['false', 'bool', 'Require OTP mobile verification at registration', true],
        'time_slots' => ['["06:00-09:00","09:00-12:00","12:00-15:00","15:00-18:00","18:00-21:00"]', 'json', 'Delivery time windows', true],
    ];

    public static function get(string $key)
    {
        $default = self::DEFAULTS[$key] ?? null;
        $fallback = $default ? (new Setting(['value' => $default[0], 'type' => $default[1]]))->castValue() : null;
        return Setting::get($key, $fallback);
    }
}
