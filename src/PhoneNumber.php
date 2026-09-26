<?php
declare(strict_types=1);
final class PhoneNumber {
    // Shared booking policy: normalization does not imply SMS routability.
    public static function normalize(string $phone): ?string {
        $phone = trim($phone);
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($phone) > 40 || !preg_match('/^\+?[0-9][0-9 ()-]*$/D', $phone) || strlen($digits) < 7 || strlen($digits) > 15) return null;
        if (preg_match('/^0[17]\d{8}$/D', $digits)) return '+254'.substr($digits, 1);
        if (str_starts_with($phone, '+') || preg_match('/^254\d{9}$/D', $digits)) return '+'.$digits;
        return $digits;
    }
    public static function smsRoutable(string $phone): bool {
        return preg_match('/^\+[1-9][0-9]{6,14}$/D', $phone) === 1;
    }
}
