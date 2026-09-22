<?php
declare(strict_types=1);
$uiVersion = $argv[1] ?? '';
if (!in_array($uiVersion, ['v1','v2','v3'], true)) exit(1);
$overnight = ($argv[2] ?? '') === 'overnight';
$preview = true; $unavailable = false; $input = []; $errors = []; $csrf = '';
$assets = '../../assets/';
$invitation = ['destination'=>$overnight ? 'galana' : 'feeding', 'visit_date'=>'2099-05-01','overnight'=>$overnight,'arrival_date'=>'2099-05-01','departure_date'=>'2099-05-03','submitted_at'=>null];
require __DIR__.'/../src/views/feedback.php';
