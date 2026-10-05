<?php
declare(strict_types=1);
require_once __DIR__.'/CommunityStore.php';
require_once __DIR__.'/SchoolRequest.php';
require_once __DIR__.'/EmailTemplate.php';
final class SchoolEmailWorker {
    private Closure $clock;
    public function __construct(private PDO $db, ?Closure $clock=null) {$this->clock=$clock??fn()=>time();}
    public static function configuration(): ?array {
        $recipients=array_values(array_unique(array_filter(array_map('trim',explode(',',getenv('SCHOOL_NOTIFICATION_TO')?:'')))));
        $from=getenv('RESEND_FROM')?:''; $base=rtrim(getenv('COMMUNITY_BASE_URL')?:'','/');
        if(getenv('SCHOOL_EMAIL_ENABLED')!=='1'||!$recipients||!filter_var($from,FILTER_VALIDATE_EMAIL)||!getenv('RESEND_API_KEY')||!filter_var($base,FILTER_VALIDATE_URL)||parse_url($base,PHP_URL_SCHEME)!=='https'||parse_url($base,PHP_URL_USER)||parse_url($base,PHP_URL_QUERY)||parse_url($base,PHP_URL_FRAGMENT))return null;
        foreach($recipients as $email)if(!filter_var($email,FILTER_VALIDATE_EMAIL))return null;
        return ['to'=>$recipients,'from'=>$from,'base'=>$base];
    }
    private function payload(array $request,array $config):array {
        $details=['Reference'=>$request['reference']];foreach(SchoolRequest::FIELDS as $key=>[$label])$details[$label]=$key==='support'?SchoolRequest::TYPES[$request[$key]]:$request[$key];
        $content=['title'=>'New school support request','preheader'=>$request['school'].' has requested support.','eyebrow'=>'School support','paragraphs'=>['A new request is ready for consideration.'],'details'=>$details,'notes'=>['Receiving this request does not approve construction.'],'action'=>['label'=>'Review request','url'=>$config['base'].'/admin/school-requests/'.$request['id']],'footer'=>'Private notification for the Nyumba team.'];
        return ['from'=>$config['from'],'to'=>$config['to'],'subject'=>'New school support request '.$request['reference'],'html'=>EmailTemplate::html($content),'text'=>EmailTemplate::text($content)];
    }
    public function run(callable $send,int $limit=25):array {
        $counts=['accepted'=>0,'retry'=>0,'review'=>0,'configuration_required'=>0];
        $config=self::configuration();if(!$config){$counts['configuration_required']=1;return $counts;}
        for($i=0;$i<$limit;$i++){
            $this->db->exec('BEGIN IMMEDIATE');
            try{
                $now=($this->clock)();
                $q=$this->db->prepare("SELECT * FROM school_notifications WHERE status='pending' AND next_attempt_at<=? ORDER BY id LIMIT 1");$q->execute([$now]);$job=$q->fetch();$q->closeCursor();
                if(!$job){$this->db->exec('COMMIT');break;}
                if($job['first_attempt_at']!==null&&$now-$job['first_attempt_at']>=23*3600){$this->db->prepare("UPDATE school_notifications SET status='review',last_error='Retry window expired; check provider before resending' WHERE id=?")->execute([$job['id']]);$this->db->exec('COMMIT');$counts['review']++;continue;}
                $q=$this->db->prepare('SELECT * FROM school_requests WHERE id=?');$q->execute([$job['request_id']]);$request=$q->fetch();
                $payload=$job['payload']?json_decode($job['payload'],true,512,JSON_THROW_ON_ERROR):$this->payload($request,$config);
                $lease=bin2hex(random_bytes(16));
                $this->db->prepare('UPDATE school_notifications SET payload=?,first_attempt_at=COALESCE(first_attempt_at,?),next_attempt_at=?,lease_token=? WHERE id=?')->execute([json_encode($payload,JSON_THROW_ON_ERROR),$now,$now+60,$lease,$job['id']]);
                $this->db->exec('COMMIT');
                try{
                    $id=$send($payload,$job['idempotency_key']);
                    if(!is_string($id)||$id==='')throw new RuntimeException('Missing provider identifier');
                    $q=$this->db->prepare("UPDATE school_notifications SET status='accepted',provider_id=?,attempts=attempts+1,last_error=NULL WHERE id=? AND lease_token=? AND status='pending'");$q->execute([$id,$job['id'],$lease]);$counts['accepted']+=$q->rowCount();
                }catch(Throwable $error){
                    $q=$this->db->prepare("UPDATE school_notifications SET attempts=attempts+1,next_attempt_at=?,last_error='Email provider request failed' WHERE id=? AND lease_token=? AND status='pending'");$q->execute([$now+min(3600,60*(2**min(6,(int)$job['attempts']))),$job['id'],$lease]);$counts['retry']+=$q->rowCount();
                }
            }catch(Throwable $error){try{$this->db->exec('ROLLBACK');}catch(Throwable $ignored){}throw $error;}
        }
        return $counts;
    }
}
