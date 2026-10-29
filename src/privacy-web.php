<?php
declare(strict_types=1);
header('Cache-Control: public, max-age=3600');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'none'");
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
require __DIR__.'/views/privacy.php';
