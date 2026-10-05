<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/CommunityStore.php';require __DIR__.'/../src/MpesaClient.php';require __DIR__.'/../src/MpesaReconciler.php';
try{
 $config=MpesaConfig::load();if(!$config||!MpesaClient::statusConfigured())throw new RuntimeException('Status queries unavailable');
 $client=new MpesaClient($config);$result=(new MpesaReconciler(CommunityStore::open(),$config))->run([$client,'status']);
 echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit($result['retry']||$result['review']?1:0);
}catch(Throwable $error){fwrite(STDERR,"Payment verification unavailable. Check private configuration, API roles and storage.\n");exit(1);}
