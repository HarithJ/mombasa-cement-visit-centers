<?php
declare(strict_types=1);
final class MpesaConfig {
    public static function load(): ?array {
        $config=['environment'=>getenv('MPESA_ENVIRONMENT')?:'', 'name'=>trim(getenv('MPESA_MERCHANT_NAME')?:''), 'merchant'=>getenv('MPESA_SHORTCODE')?:'', 'number'=>getenv('MPESA_PAYMENT_NUMBER')?:getenv('MPESA_SHORTCODE')?:'', 'type'=>getenv('MPESA_MERCHANT_TYPE')?:'', 'dedicated'=>getenv('MPESA_DEDICATED')==='1', 'reference'=>getenv('MPESA_DONATION_REFERENCE')?:''];
        if(getenv('MPESA_ENABLED')!=='1'||!in_array($config['environment'],['sandbox','production'],true)||!in_array($config['type'],['paybill','till'],true)||!preg_match('/^[0-9]{5,10}$/D',$config['merchant'])||!preg_match('/^[0-9]{5,10}$/D',$config['number'])||$config['name']===''||mb_strlen($config['name'])>100)return null;
        if(($config['type']==='paybill'||!$config['dedicated'])&&!preg_match('/^[A-Za-z0-9-]{1,20}$/D',$config['reference']))return null;
        // Shared tills cannot reliably carry an account reference.
        if($config['type']==='till'&&!$config['dedicated'])return null;
        return $config;
    }
    public static function trustedCallback(): bool {
        $peer=$_SERVER['REMOTE_ADDR']??'';
        $addresses=array_filter(array_map('trim',explode(',',getenv('MPESA_TRUSTED_CALLBACK_IPS')?:'')));
        if(in_array($peer,$addresses,true))return true;
        $proxies=array_filter(array_map('trim',explode(',',getenv('MPESA_CALLBACK_PROXY_IPS')?:'')));
        $secret=getenv('MPESA_INGRESS_TOKEN')?:'';
        return strlen($secret)>=32&&in_array($peer,$proxies,true)&&hash_equals($secret,$_SERVER['HTTP_X_NYUMBA_MPESA_VERIFIED']??'');
    }
}
