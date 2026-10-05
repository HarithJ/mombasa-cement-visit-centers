<?php
declare(strict_types=1);
require_once __DIR__.'/BookingStore.php';
final class CommunityStore {
    public static function open(?string $path = null): PDO {
        $path ??= getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
        new BookingStore($path);
        $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }
    public static function throttle(PDO $db, string $scope, string $address, int $limit = 10, ?int $now = null): bool {
        $now ??= time();
        $db->exec('BEGIN IMMEDIATE');
        try {
            $db->prepare('DELETE FROM community_throttle WHERE started_at <= ?')->execute([$now-900]);
            $bucket = hash('sha256', $scope.'|'.$address);
            $db->prepare('INSERT INTO community_throttle VALUES (?, ?, 1) ON CONFLICT(bucket) DO UPDATE SET attempts=attempts+1')->execute([$bucket,$now]);
            $q=$db->prepare('SELECT attempts FROM community_throttle WHERE bucket=?'); $q->execute([$bucket]);
            $allowed=(int)$q->fetchColumn()<=$limit;
            $db->exec('COMMIT'); return $allowed;
        } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    }
}
