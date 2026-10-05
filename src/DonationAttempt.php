<?php
declare(strict_types=1);
final class DonationAttempt {
    public const STATES=['qr_pending'=>'Preparing QR','awaiting'=>'Awaiting confirmation','qr_failed'=>'QR generation failed','received'=>'Donation received','review'=>'Payment needs review'];
    public function __construct(private PDO $db,private array $config) {}
    public function create(int $amount,string $token):array {
        $c=$this->config;
        $q=$this->db->prepare('INSERT INTO donation_attempts(reference,submission_token,environment,merchant,merchant_name,payment_number,merchant_type,amount,created_at) VALUES (?,?,?,?,?,?,?,?,?) ON CONFLICT(submission_token) DO NOTHING');
        $q->execute(['NY'.strtoupper(bin2hex(random_bytes(5))),$token,$c['environment'],$c['merchant'],$c['name'],$c['number'],$c['type'],$amount,gmdate('Y-m-d\TH:i:s\Z')]);
        return $this->find($token);
    }
    public function find(string $token):?array {$q=$this->db->prepare('SELECT * FROM donation_attempts WHERE submission_token=?');$q->execute([$token]);return $q->fetch()?:null;}
    public function generate(array $attempt,callable $generate):void {
        // QR generation never moves funds. A lease permits safe recovery after a crash.
        $lease=bin2hex(random_bytes(16));$now=time();
        $q=$this->db->prepare("UPDATE donation_attempts SET lease_until=?,lease_token=?,state='qr_pending' WHERE id=? AND qr IS NULL AND lease_until<=?");$q->execute([$now+60,$lease,$attempt['id'],$now]);if(!$q->rowCount())return;
        try {
            if($attempt['environment']!==$this->config['environment']||$attempt['merchant']!==$this->config['merchant'])throw new RuntimeException('Merchant changed');
            $result=$generate($attempt);
            $this->db->prepare("UPDATE donation_attempts SET state='awaiting',qr=?,provider_id=?,lease_until=0 WHERE id=? AND lease_token=?")->execute([$result['qr'],$result['provider_id'],$attempt['id'],$lease]);
        }catch(Throwable $error){$this->db->prepare("UPDATE donation_attempts SET state='qr_failed',lease_until=0 WHERE id=? AND lease_token=?")->execute([$attempt['id'],$lease]);}
    }
    public static function paymentState(PDO $db,array $attempt):string {
        $q=$db->prepare('SELECT payment_state FROM donation_attempt_summary WHERE id=?');$q->execute([$attempt['id']]);
        $state=$q->fetchColumn();return in_array($state,['received','review'],true)?self::STATES[$state]:'Awaiting confirmation';
    }
}
