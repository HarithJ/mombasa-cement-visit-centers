<?php
declare(strict_types=1);
require_once __DIR__.'/SiteUrl.php';
// Personal Accept/Reject links in the new-booking team email: {BOOKING_SITE_URL}/decide?token={64 hex}.
// The token travels in the query string because nginx's access log records only the path.
// Only sha256(token) is stored, with the user's credential_version at issue time, so a link
// stops working once that user is deactivated or changes email.
final class DecisionLinks {
    public static function hash(string $token): string { return hash('sha256', $token); }

    // Creates this user's two links for the booking and returns their URLs, keyed accept and reject.
    public static function issue(PDO $db, array $booking, array $user): array {
        $site = SiteUrl::canonical();
        $query = $db->prepare('INSERT INTO decision_links(booking_id, user_id, action, token_hash, credential_version, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $urls = [];
        foreach (['accept', 'reject'] as $action) {
            $token = bin2hex(random_bytes(32));
            $query->execute([$booking['id'], $user['id'], $action, self::hash($token), $user['credential_version'], gmdate('Y-m-d\TH:i:s\Z')]);
            $urls[$action] = $site.'/decide?token='.$token;
        }
        return $urls;
    }

    // The link's action, user and booking, or null when the token is malformed, unknown, or its user
    // has been deactivated or changed email since it was issued.
    public static function find(PDO $db, string $token): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $query = $db->prepare('SELECT l.action, l.user_id, l.booking_id FROM decision_links l JOIN users u ON u.id = l.user_id WHERE l.token_hash = ? AND u.active = 1 AND u.credential_version = l.credential_version');
        $query->execute([self::hash($token)]);
        $link = $query->fetch(PDO::FETCH_ASSOC);
        if (!$link) return null;
        $user = $db->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$link['user_id']]);
        $booking = $db->prepare('SELECT * FROM bookings WHERE id = ?');
        $booking->execute([$link['booking_id']]);
        $booking = $booking->fetch(PDO::FETCH_ASSOC);
        return $booking ? ['action' => $link['action'], 'user' => $user->fetch(PDO::FETCH_ASSOC), 'booking' => $booking] : null;
    }
}
