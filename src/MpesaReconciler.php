<?php
declare(strict_types=1);
require_once __DIR__.'/DonationLedger.php';
final class MpesaReconciler {
    private Closure $clock;
    public function __construct(private PDO $db,private array $config,?Closure $clock=null){$this->clock=$clock??fn()=>time();}
    public function enqueue(string $receipt):void {
        if(!preg_match('/^[A-Z0-9]{8,32}$/D',$receipt))throw new InvalidArgumentException('Invalid receipt');
        $this->db->prepare('INSERT OR IGNORE INTO mpesa_queries(environment,merchant,receipt) VALUES (?,?,?)')->execute([$this->config['environment'],$this->config['merchant'],$receipt]);
    }
    public function acceptResult(string $token,array $payload,bool $trusted):void {
        $q=$this->db->prepare('SELECT 1 FROM mpesa_query_runs r JOIN mpesa_queries q ON q.id=r.query_id WHERE r.token=? AND q.environment=? AND q.merchant=?');$q->execute([$token,$this->config['environment'],$this->config['merchant']]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Unknown query');
        $json=json_encode($payload,JSON_THROW_ON_ERROR);
        $this->db->prepare('INSERT OR IGNORE INTO mpesa_query_results(token,fingerprint,payload,trusted,created_at) VALUES (?,?,?,?,?)')->execute([$token,hash('sha256',$json),$json,$trusted?1:0,gmdate('Y-m-d\TH:i:s\Z')]);
    }
    private function confirmedReceipt(array $payload,array $run):array {
        $result=$payload['Result']??[];
        if(!is_array($result)||!isset($result['ResultCode'])||(string)$result['ResultCode']!=='0'||($result['ConversationID']??null)!==$run['conversation']||($result['OriginatorConversationID']??null)!==$run['originator'])throw new InvalidArgumentException('Unconfirmed query result');
        $params=[];$items=$result['ResultParameters']['ResultParameter']??[];
        if(!is_array($items))throw new InvalidArgumentException('Invalid parameters');
        foreach($items as $item){if(!is_array($item)||!is_string($item['Key']??null)||!is_scalar($item['Value']??null)||isset($params[$item['Key']]))throw new InvalidArgumentException('Ambiguous parameters');$params[$item['Key']]=(string)$item['Value'];}
        // Query acceptance (ResultCode=0) does not prove the underlying payment completed.
        if(($params['TransactionStatus']??'')!=='Completed'||($params['ReceiptNo']??'')!==$run['receipt']||!preg_match('/^'.preg_quote($this->config['merchant'],'/').'\s*-\s*\S/u',$params['CreditPartyName']??''))throw new InvalidArgumentException('Payment not independently confirmed');
        $reference=$params['BillReferenceNumber']??null;
        // Dedicated merchants need no reference for attribution; shared ones do.
        if($reference===null){if(!$this->config['dedicated'])throw new InvalidArgumentException('Missing attribution');$reference='';}
        return ['TransID'=>$run['receipt'],'TransTime'=>$params['TransactionCompletedDateTime']??'', 'TransAmount'=>$params['Amount']??'', 'BusinessShortCode'=>$this->config['merchant'],'BillRefNumber'=>$reference];
    }
    public function run(callable $query,int $limit=25):array {
        $counts=['requested'=>0,'verified'=>0,'retry'=>0,'review'=>0];
        $q=$this->db->prepare("SELECT receipt FROM mpesa_transactions WHERE environment=? AND merchant=? AND state IN ('unverified','conflict')");$q->execute([$this->config['environment'],$this->config['merchant']]);foreach($q->fetchAll()as $row)$this->enqueue($row['receipt']);
        $q=$this->db->prepare('SELECT s.*,r.conversation,r.originator,q.receipt,q.id AS query_id FROM mpesa_query_results s JOIN mpesa_query_runs r ON r.token=s.token JOIN mpesa_queries q ON q.id=r.query_id WHERE s.processed=0 AND s.trusted=1 AND r.conversation IS NOT NULL AND q.environment=? AND q.merchant=? ORDER BY s.id LIMIT ?');
        $q->bindValue(1,$this->config['environment']);$q->bindValue(2,$this->config['merchant']);$q->bindValue(3,$limit,PDO::PARAM_INT);$q->execute();
        foreach($q->fetchAll()as $result){
            try{
                $body=$this->confirmedReceipt(json_decode($result['payload'],true,32,JSON_THROW_ON_ERROR),$result);
                $ledger=new DonationLedger($this->db,$this->config);$id=$ledger->receive($body,true,'status-query','query '.$result['query_id']);
                $state=$this->db->prepare('SELECT state FROM mpesa_transactions WHERE id=?');$state->execute([$id]);$needsReview=$state->fetchColumn()==='conflict';
                $this->db->prepare('UPDATE mpesa_queries SET status=?,last_error=? WHERE id=?')->execute([$needsReview?'review':'complete',$needsReview?'Conflicting verified evidence':null,$result['query_id']]);$counts[$needsReview?'review':'verified']++;
            }catch(InvalidArgumentException|JsonException $error){$this->db->prepare("UPDATE mpesa_queries SET status='review',last_error='Provider result requires review' WHERE id=? AND status<>'complete'")->execute([$result['query_id']]);$counts['review']++;}
            $this->db->prepare('UPDATE mpesa_query_results SET processed=1 WHERE id=?')->execute([$result['id']]);
        }
        for($i=0;$i<$limit;$i++){
            $this->db->exec('BEGIN IMMEDIATE');
            try{
                $now=($this->clock)();$q=$this->db->prepare("SELECT * FROM mpesa_queries WHERE environment=? AND merchant=? AND status IN ('pending','waiting') AND next_at<=? ORDER BY id LIMIT 1");$q->execute([$this->config['environment'],$this->config['merchant'],$now]);$job=$q->fetch();$q->closeCursor();
                if(!$job){$this->db->exec('COMMIT');break;}
                if($job['attempts']>=5){$this->db->prepare("UPDATE mpesa_queries SET status='review',last_error='No conclusive provider result after five checks' WHERE id=?")->execute([$job['id']]);$this->db->exec('COMMIT');$counts['review']++;continue;}
                $token=bin2hex(random_bytes(32));$this->db->prepare('INSERT INTO mpesa_query_runs(token,query_id,created_at) VALUES (?,?,?)')->execute([$token,$job['id'],gmdate('Y-m-d\TH:i:s\Z')]);
                $this->db->prepare("UPDATE mpesa_queries SET status='waiting',attempts=attempts+1,next_at=? WHERE id=?")->execute([$now+min(3600,300*(2**$job['attempts'])),$job['id']]);$this->db->exec('COMMIT');
                try{
                    $response=$query($job['receipt'],$token);
                    if((string)($response['ResponseCode']??'')!=='0'||!is_string($response['ConversationID']??null)||!is_string($response['OriginatorConversationID']??null)||$response['ConversationID']===''||$response['OriginatorConversationID']==='')throw new RuntimeException('Query unavailable');
                    $this->db->prepare('UPDATE mpesa_query_runs SET conversation=?,originator=? WHERE token=?')->execute([substr($response['ConversationID'],0,200),substr($response['OriginatorConversationID'],0,200),$token]);$counts['requested']++;
                }catch(Throwable $error){$this->db->prepare("UPDATE mpesa_queries SET last_error='Status query unavailable; retry scheduled' WHERE id=? AND status='waiting'")->execute([$job['id']]);$counts['retry']++;}
            }catch(Throwable $error){try{$this->db->exec('ROLLBACK');}catch(Throwable $ignored){}throw $error;}
        }
        return $counts;
    }
}
