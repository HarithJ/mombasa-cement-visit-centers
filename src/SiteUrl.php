<?php
declare(strict_types=1);
// The canonical public origin for links in emails. Links never use the incoming Host header.
final class SiteUrl {
    public static function canonical(): string {
        $site = rtrim(getenv('BOOKING_SITE_URL') ?: '', '/');
        $url = parse_url($site);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])) throw new RuntimeException('Configure a canonical HTTPS BOOKING_SITE_URL');
        return $site;
    }
}
