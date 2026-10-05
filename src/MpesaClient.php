<?php
declare(strict_types=1);
require_once __DIR__.'/MpesaConfig.php';
final class MpesaClient {
    private Closure $transport;
    public function __construct(private array $config,?callable $transport=null) {$this->transport=$transport?Closure::fromCallable($transport):self::http(...);}
    public static function qrConfigured():bool {return getenv('MPESA_QR_ENABLED')==='1'&&getenv('MPESA_CONSUMER_KEY')&&getenv('MPESA_CONSUMER_SECRET')&&MpesaConfig::load()!==null;}
    private static function http(string $method,string $url,array $headers,?array $body):array {
        $curl=curl_init($url);$response='';
        curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$headers,CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>1500000)return 0;$response.=$chunk;return strlen($chunk);}]);
        if($body!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        if($ok===false||$status<200||$status>=300)throw new RuntimeException('M-Pesa provider unavailable');
        $data=json_decode($response,true,32,JSON_THROW_ON_ERROR);if(!is_array($data))throw new RuntimeException('Invalid provider response');return $data;
    }
    public function request(string $path,array $body):array {
        $base=$this->config['environment']==='production'?'https://api.safaricom.co.ke':'https://sandbox.safaricom.co.ke';
        $key=getenv('MPESA_CONSUMER_KEY')?:'';$secret=getenv('MPESA_CONSUMER_SECRET')?:'';
        if($key===''||$secret==='')throw new RuntimeException('M-Pesa credentials unavailable');
        $auth=($this->transport)('GET',$base.'/oauth/v1/generate?grant_type=client_credentials',['Authorization: Basic '.base64_encode($key.':'.$secret)],null);
        $token=$auth['access_token']??null;if(!is_string($token)||!preg_match('/^[A-Za-z0-9._~-]+$/D',$token))throw new RuntimeException('Invalid provider token');
        return ($this->transport)('POST',$base.$path,['Authorization: Bearer '.$token,'Content-Type: application/json'],$body);
    }
    public static function statusConfigured():bool {
        $base=getenv('COMMUNITY_BASE_URL')?:'';
        return getenv('MPESA_STATUS_ENABLED')==='1'&&getenv('MPESA_CONSUMER_KEY')&&getenv('MPESA_CONSUMER_SECRET')&&getenv('MPESA_INITIATOR')&&getenv('MPESA_SECURITY_CREDENTIAL')&&filter_var($base,FILTER_VALIDATE_URL)&&parse_url($base,PHP_URL_SCHEME)==='https'&&!parse_url($base,PHP_URL_USER)&&!parse_url($base,PHP_URL_QUERY)&&!parse_url($base,PHP_URL_FRAGMENT);
    }
    public function status(string $receipt,string $token):array {
        if(!self::statusConfigured())throw new RuntimeException('Status queries unavailable');
        $url=rtrim(getenv('COMMUNITY_BASE_URL'),'/').'/mpesa/result/'.$token;
        return $this->request('/mpesa/transactionstatus/v1/query',['Initiator'=>getenv('MPESA_INITIATOR'),'SecurityCredential'=>getenv('MPESA_SECURITY_CREDENTIAL'),'CommandID'=>'TransactionStatusQuery','TransactionID'=>$receipt,'PartyA'=>$this->config['merchant'],'IdentifierType'=>'4','ResultURL'=>$url,'QueueTimeOutURL'=>$url,'Remarks'=>'Donation reconciliation','Occasion'=>'Donation status']);
    }
    public function qr(array $attempt):array {
        $result=$this->request('/mpesa/qrcode/v1/generate',['MerchantName'=>$attempt['merchant_name'],'RefNo'=>$attempt['reference'],'Amount'=>intdiv((int)$attempt['amount'],100),'TrxCode'=>$attempt['merchant_type']==='paybill'?'PB':'BG','CPI'=>$attempt['payment_number'],'Size'=>'300']);
        if(!in_array((string)($result['ResponseCode']??''),['0','00'],true)||!is_string($result['QRCode']??null)||strlen($result['QRCode'])>1000000)throw new RuntimeException('QR unavailable');
        $png=base64_decode($result['QRCode'],true);$dimensions=$png!==false?@getimagesizefromstring($png):false;
        if(!$dimensions||$dimensions[2]!==IMAGETYPE_PNG||$dimensions[0]<100||$dimensions[0]>2048||$dimensions[1]<100||$dimensions[1]>2048)throw new RuntimeException('Invalid QR image');
        return ['qr'=>base64_encode($png),'provider_id'=>is_string($result['RequestID']??null)?substr($result['RequestID'],0,200):null];
    }
}
