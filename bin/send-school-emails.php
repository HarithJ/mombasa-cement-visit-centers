<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/SchoolEmailWorker.php';
require __DIR__.'/../src/ResendClient.php';
try{
 $client=new ResendClient(getenv('RESEND_API_KEY')?:'');
 $result=(new SchoolEmailWorker(CommunityStore::open()))->run([$client,'send']);
 echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
 exit($result['retry']||$result['review']||$result['configuration_required']?1:0);
}catch(Throwable $error){fwrite(STDERR,"School email worker failed. Check private configuration and storage.\n");exit(1);}
