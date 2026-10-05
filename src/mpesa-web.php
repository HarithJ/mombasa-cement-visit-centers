<?php
declare(strict_types=1);
require_once __DIR__.'/CommunityStore.php';
require_once __DIR__.'/MpesaConfig.php';
require_once __DIR__.'/DonationLedger.php';
header('Content-Type: application/json');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo '{"error":"Method not allowed"}';return;}
 $config=MpesaConfig::load();if(!$config){http_response_code(503);echo '{"error":"Unavailable"}';return;}
 if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new InvalidArgumentException('JSON required');
 $raw=file_get_contents('php://input',false,null,0,32769);if(strlen($raw)>32768){http_response_code(413);echo '{"error":"Too large"}';return;}
 $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($body))throw new InvalidArgumentException('Invalid notification');
 $db=CommunityStore::open();
 if(!CommunityStore::throttle($db,'mpesa-callback',$_SERVER['REMOTE_ADDR']??'',2000)){http_response_code(429);echo '{"error":"Retry later"}';return;}
 if(preg_match('#^/mpesa/(result|timeout)/([a-f0-9]{64})$#D',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),$match)){
  require_once __DIR__.'/MpesaReconciler.php';(new MpesaReconciler($db,$config))->acceptResult($match[2],$body,MpesaConfig::trustedCallback(),$match[1]==='timeout');
 }else (new DonationLedger($db,$config))->receive($body,MpesaConfig::trustedCallback());
 echo '{"ResultCode":0,"ResultDesc":"Accepted"}';
}catch(InvalidArgumentException|JsonException $error){http_response_code(400);echo '{"error":"Invalid notification"}';}
catch(Throwable $error){http_response_code(503);echo '{"error":"Please retry"}';}
