<?php
declare(strict_types=1);

// Keep routing explicit: request paths must never become filesystem paths.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$routes = ['/' => 'v1', '/index.php' => 'v1'];
foreach (['v1', 'v2', 'v3'] as $version) {
    $routes['/'.$version] = $version;
    $routes['/'.$version.'/'] = $version;
    $routes['/'.$version.'/index.php'] = $version;
}
if (!is_string($path) || !isset($routes[$path])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Page not found.';
    exit;
}
$uiVersion = $routes[$path];
$bookingPath = in_array($path, ['/', '/index.php'], true) ? '/' : '/'.$uiVersion;
require __DIR__.'/../src/web.php';
require __DIR__.'/../src/views/'.$uiVersion.'.php';
