<?php
declare(strict_types=1);
$descriptions = [];
$names = ['galana' => 'Galana Farm'];
foreach (json_decode(file_get_contents(__DIR__.'/photo-manifest.json'), true, 512, JSON_THROW_ON_ERROR) as $photo) {
    $descriptions[$photo['destination']][$photo['asset']] = ucfirst(str_replace('-', ' ', $photo['description'])).' — '.$names[$photo['destination']];
}
return $descriptions;
