<?php
declare(strict_types=1);
require_once __DIR__.'/PhoneNumber.php';
final class ManagerNotifications {
    // Managers notified of every booking, whatever the destination.
    public static function emails(): array { return ['harithjaved@gmail.com', 'apoorvchit@gmail.com']; }
    public static function phones(): array { return [PhoneNumber::normalize('+254792488382'), PhoneNumber::normalize('+254115933896')]; }
}
