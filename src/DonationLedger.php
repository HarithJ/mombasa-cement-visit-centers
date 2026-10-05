<?php
declare(strict_types=1);
final class DonationLedger {
    public const STATES=['verified'=>'Verified donation','unmatched'=>'Unmatched payment','unverified'=>'Unverified receipt','conflict'=>'Review required','reversed'=>'Reversed'];
    public function __construct(private PDO $db,private array $config) {}
    public static function amount(mixed $value): int {
        if(!is_string($value)&&!is_int($value)&&!is_float($value))throw new InvalidArgumentException('Invalid amount');
        $text=(string)$value;
        if(!preg_match('/^([0-9]{1,9})(?:\.([0-9]{1,2}))?$/D',$text,$m))throw new InvalidArgumentException('Invalid amount');
        $amount=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
        if($amount<=0)throw new InvalidArgumentException('Invalid amount');
        return $amount;
    }
    public function normalize(array $body):array {
        foreach(['TransID'=>32,'TransTime'=>14,'BusinessShortCode'=>10,'BillRefNumber'=>100]as $key=>$length){if(!is_string($body[$key]??null)||strlen($body[$key])>$length)throw new InvalidArgumentException('Invalid receipt');}
        if(!preg_match('/^[A-Z0-9]{8,32}$/D',$body['TransID'])||$body['BusinessShortCode']!==$this->config['merchant'])throw new InvalidArgumentException('Invalid merchant or receipt');
        $time=DateTimeImmutable::createFromFormat('!YmdHis',$body['TransTime'],new DateTimeZone('Africa/Nairobi'));
        if(!$time||$time->format('YmdHis')!==$body['TransTime'])throw new InvalidArgumentException('Invalid transaction time');
        $optional=static function(string $key,int $max)use($body):?string{if(!isset($body[$key]))return null;if(!is_string($body[$key])||strlen($body[$key])>$max)throw new InvalidArgumentException('Invalid payer');return $body[$key];};
        return ['receipt'=>$body['TransID'],'amount'=>self::amount($body['TransAmount']??null),'account_reference'=>trim($body['BillRefNumber']),'payer'=>$optional('MSISDN',128),'payer_name'=>$optional('FirstName',120),'transacted_at'=>$time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')];
    }
    private function classification(array $receipt):string {
        $q=$this->db->prepare('SELECT 1 FROM donation_attempts WHERE environment=? AND merchant=? AND reference=?');
        $q->execute([$this->config['environment'],$this->config['merchant'],$receipt['account_reference']]);
        return $this->config['dedicated']||hash_equals($this->config['reference'],$receipt['account_reference'])||$q->fetchColumn()?'verified':'unmatched';
    }
    public function receive(array $body,bool $trusted,string $source='callback',string $evidence='',bool $resolve=false):int {
        $receipt=$this->normalize($body);$payload=json_encode($receipt,JSON_THROW_ON_ERROR);$fingerprint=hash('sha256',$payload);$source.= $trusted?'/verified':'/unverified';
        $this->db->exec('BEGIN IMMEDIATE');
        try{
            $q=$this->db->prepare('SELECT * FROM mpesa_transactions WHERE environment=? AND merchant=? AND receipt=?');$q->execute([$this->config['environment'],$this->config['merchant'],$receipt['receipt']]);$saved=$q->fetch();
            if(!$saved){
                $this->db->prepare('INSERT INTO mpesa_transactions(environment,merchant,receipt,amount,account_reference,payer,payer_name,transacted_at,received_at,state) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$this->config['environment'],$this->config['merchant'],$receipt['receipt'],$receipt['amount'],$receipt['account_reference'],$receipt['payer'],$receipt['payer_name'],$receipt['transacted_at'],gmdate('Y-m-d\TH:i:s\Z'),$trusted?$this->classification($receipt):'unverified']);
                $id=(int)$this->db->lastInsertId();
            }else{
                $id=(int)$saved['id'];
                $same=$saved['amount']===$receipt['amount']&&$saved['account_reference']===$receipt['account_reference']&&$saved['transacted_at']===$receipt['transacted_at'];
                if($trusted&&($saved['state']==='unverified'||($resolve&&$saved['state']!=='reversed'))){
                    // An untrusted notification must not poison a later authoritative receipt.
                    $this->db->prepare('UPDATE mpesa_transactions SET amount=?,account_reference=?,payer=?,payer_name=?,transacted_at=?,state=? WHERE id=?')->execute([$receipt['amount'],$receipt['account_reference'],$receipt['payer'],$receipt['payer_name'],$receipt['transacted_at'],$this->classification($receipt),$id]);
                }elseif($trusted&&!$same&&$saved['state']!=='reversed')$this->db->prepare("UPDATE mpesa_transactions SET state='conflict' WHERE id=?")->execute([$id]);
            }
            $this->db->prepare('INSERT OR IGNORE INTO mpesa_events(transaction_id,fingerprint,source,evidence,payload,created_at) VALUES (?,?,?,?,?,?)')->execute([$id,$fingerprint,$source,$evidence,$payload,gmdate('Y-m-d\TH:i:s\Z')]);
            $this->db->exec('COMMIT');return $id;
        }catch(Throwable $error){$this->db->exec('ROLLBACK');throw $error;}
    }
    public function reconcileStatement(array $body,string $actor,string $evidence,bool $reversed):int {
        if(trim($actor)===''||strlen($actor)>100||strlen(trim($evidence))<10||strlen($evidence)>1000)throw new InvalidArgumentException('Operator and verified evidence reference required');
        $receipt=$this->normalize($body);
        if(!$reversed)return $this->receive($body,true,'statement/'.$actor,$evidence,true);
        $this->db->exec('BEGIN IMMEDIATE');
        try{
            $q=$this->db->prepare('SELECT * FROM mpesa_transactions WHERE environment=? AND merchant=? AND receipt=?');$q->execute([$this->config['environment'],$this->config['merchant'],$receipt['receipt']]);$saved=$q->fetch();
            if(!$saved||!in_array($saved['state'],['verified','unmatched','reversed'],true)||(int)$saved['amount']!==$receipt['amount'])throw new InvalidArgumentException('Verify original receipt and full reversal amount first');
            $this->db->prepare('INSERT OR IGNORE INTO mpesa_adjustments(transaction_id,amount,actor,evidence,created_at) VALUES (?,?,?,?,?)')->execute([$saved['id'],-$saved['amount'],$actor,$evidence,gmdate('Y-m-d\TH:i:s\Z')]);
            $this->db->prepare("UPDATE mpesa_transactions SET state='reversed' WHERE id=?")->execute([$saved['id']]);
            $this->db->exec('COMMIT');return (int)$saved['id'];
        }catch(Throwable $error){$this->db->exec('ROLLBACK');throw $error;}
    }

}
