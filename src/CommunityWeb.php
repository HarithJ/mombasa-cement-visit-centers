<?php
declare(strict_types=1);
final class CommunityWeb {
    public static function start(): void {
        header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer'); header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        ini_set('session.use_strict_mode','1');
        if ($path=getenv('BOOKING_SESSION_PATH')) session_save_path($path);
        $secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
        $trusted=array_filter(array_map('trim',explode(',',getenv('ADMIN_TRUSTED_PROXIES')?:'')));
        if (in_array($_SERVER['REMOTE_ADDR']??'', $trusted,true)) $secure=($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';
        session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']); session_start();
        $_SESSION['csrf']??=bin2hex(random_bytes(32));
        if (!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)) {http_response_code(405); header('Allow: GET, POST'); exit;}
    }
    public static function token(string $scope): string { return $_SESSION['community_tokens'][$scope]??=bin2hex(random_bytes(32)); }
    public static function valid(string $scope): bool {
        return is_string($_POST['csrf']??null) && hash_equals($_SESSION['csrf'],$_POST['csrf']) && is_string($_POST['submissionToken']??null) && hash_equals(self::token($scope),$_POST['submissionToken']);
    }
    public static function escape(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function header(string $title): void {
        $title=self::escape($title);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.' · Nyumba</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/community.css"></head><body class="community-page"><header><a href="/">NYUMBA GROUP <span> / Visit Nyumba</span></a></header><main>';
    }
}
