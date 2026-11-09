<?php

namespace App\Support;

class Mobile
{
    /** Normalise to +<country><number>. 10-digit Indian numbers get +91. */
    public static function normalize(?string $mobile): ?string
    {
        if ($mobile === null || trim($mobile) === '') return null;
        $digits = preg_replace('/\D+/', '', $mobile);
        if (strlen($digits) === 10) return '+91' . $digits;
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) return '+91' . substr($digits, 1);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) return '+' . $digits;
        return '+' . $digits;
    }

    public static function isValid(?string $normalized): bool
    {
        return (bool) preg_match('/^\+\d{10,15}$/', (string) $normalized)
            && (! str_starts_with($normalized, '+91') || preg_match('/^\+91[6-9]\d{9}$/', $normalized));
    }

    /** Mask for limited-visibility contexts: +91******3210 */
    public static function mask(?string $m): ?string
    {
        return $m ? substr($m, 0, 3) . str_repeat('*', max(0, strlen($m) - 7)) . substr($m, -4) : null;
    }
}
