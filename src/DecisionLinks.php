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
}
