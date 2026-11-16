<?php
declare(strict_types=1);
require_once __DIR__.'/EmailTemplate.php';
// Emails about a staff account, sent through the account_emails queue.
final class AccountEmail {
    public static function signInLink(array $user, string $link): array {
        return self::build($user, 'Your sign-in link for Visit Nyumba admin', 'Your sign-in link',
            "here's your link to sign in. It works once and expires in 15 minutes.",
            ['label' => 'Sign in', 'url' => $link], "If you didn't ask for this, you can ignore this email.");
    }
    public static function reset(array $user, string $link): array {
        return self::build($user, 'Reset your Visit Nyumba admin password', 'Reset your password',
            'use this link to set a new password. It works once and expires in 1 hour.',
            ['label' => 'Set a new password', 'url' => $link], "If you didn't ask for this, you can ignore this email. Your password won't change.");
    }
    // $adminName is whoever added the user.
    public static function welcome(array $user, string $link, string $adminName): array {
        $intro = $adminName.' has given you access to the Visit Nyumba admin panel, where you can see bookings and accept or reject visits to Galana Farm.';
        $content = [
            'eyebrow' => 'Visit Nyumba admin', 'title' => 'Welcome to Visit Nyumba admin', 'preheader' => $intro,
            'paragraphs' => ['Hi '.EmailTemplate::firstName($user['name']).', '.$intro, "From now on you'll get an email with Accept and Reject buttons whenever someone books a visit."],
            'details' => [], 'notes' => ['After that, sign in with "Email me a sign-in link", or set a password with "Forgot password?".'],
            'action' => ['label' => 'Sign in', 'url' => $link], 'footer' => '',
        ];
        return ['to' => [$user['email']], 'subject' => "You've been added to Visit Nyumba admin", 'text' => EmailTemplate::text($content), 'html' => EmailTemplate::html($content)];
    }
    // $kind is login, reset or welcome; $key makes the send idempotent.
    public static function queue(PDO $db, array $user, string $kind, array $payload, string $key): void {
        $db->prepare('INSERT INTO account_emails(user_id, kind, payload, idempotency_key) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $kind, json_encode($payload, JSON_THROW_ON_ERROR), $key]);
    }
    private static function build(array $user, string $subject, string $title, string $body, array $action, string $footer): array {
        $content = [
            'eyebrow' => 'Visit Nyumba admin', 'title' => $title, 'preheader' => ucfirst($body),
            'paragraphs' => ['Hi '.EmailTemplate::firstName($user['name']).', '.$body], 'details' => [], 'notes' => [],
            'action' => $action, 'footer' => $footer,
        ];
        return ['to' => [$user['email']], 'subject' => $subject, 'text' => EmailTemplate::text($content), 'html' => EmailTemplate::html($content)];
    }
}
