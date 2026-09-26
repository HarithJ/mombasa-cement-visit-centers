<?php
declare(strict_types=1);
require_once __DIR__.'/PhoneNumber.php';
final class ManagerNotifications {
    // Temporary notification routing; public location contacts remain unchanged.
    public static function overridden(): bool { return getenv('BOOKING_MANAGER_OVERRIDE_ENABLED') !== '0'; }
    public static function email(): string { return 'harithjaved@gmail.com'; }
    public static function phone(): string { return PhoneNumber::normalize('0792488382'); }
}
