<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/CommunityStore.php';require __DIR__.'/../src/MpesaConfig.php';require __DIR__.'/../src/MpesaReconciler.php';
// Read private evidence from stdin, never shell arguments or public requests.
try{
 $config=MpesaConfig::load();if(!$config)throw new RuntimeException('Merchant configuration required');
 $input=stream_get_contents(STDIN,32769);if(strlen($input)>32768)throw new InvalidArgumentException('Evidence too large');
 $data=json_decode($input,true,16,JSON_THROW_ON_ERROR);if(!is_array($data))throw new InvalidArgumentException('Evidence required');
 $db=CommunityStore::open();
 if(($data['action']??'')==='query'){
  (new MpesaReconciler($db,$config))->enqueue($data['receipt']??'');echo "Receipt queued for provider verification.\n";
 }elseif(in_array($data['action']??'',['confirm','reverse'],true)&&($data['reviewed_merchant_evidence']??false)===true){
  $id=(new DonationLedger($db,$config))->reconcileStatement($data['receipt_data']??[],$data['verified_by']??'',$data['evidence_reference']??'',$data['action']==='reverse');echo 'Reconciled transaction '.$id.".\n";
 }else throw new InvalidArgumentException('Verified merchant evidence is required');
}catch(Throwable $error){fwrite(STDERR,"Reconciliation failed. Check merchant, input, actor and verified evidence; no success is implied.\n");exit(1);}
