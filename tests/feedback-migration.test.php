<?php
declare(strict_types=1);
require __DIR__.'/../src/BookingStore.php';
$directory = sys_get_temp_dir().'/feedback-migration-'.bin2hex(random_bytes(8));
mkdir($directory,0700); $path=$directory.'/bookings.sqlite';
try {
    $db = new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE schema_migrations(version TEXT PRIMARY KEY)');
    foreach (glob(__DIR__.'/../migrations/00[1-4]-*.sql') as $file) {
        $db->exec(file_get_contents($file));
        $db->prepare('INSERT INTO schema_migrations VALUES (?)')->execute([basename($file)]);
    }
    $db->exec("INSERT INTO bookings(reference,submission_token,full_name,phone,email,destination,visit_date,booked_time,attendees,status,created_at) VALUES ('NY-LEGACY','legacy','Legacy Visitor','0712345678','legacy@example.com','feeding','2099-05-01','11:00',1,'automatically_confirmed','2026-01-01')");
    $db->exec("INSERT INTO booking_emails(booking_id,kind,payload,idempotency_key,status,attempts,first_attempt_at,next_attempt_at,provider_id,last_error) VALUES (1,'visitor','{\"text\":\"Original payload\"}','visitor/old','accepted',2,100,160,'original-id',NULL),(1,'destination','{}','booking/old','pending',1,200,260,NULL,'Email API request failed')");
    $db->exec("INSERT INTO bookings(reference,submission_token,full_name,phone,destination,visit_date,booked_time,attendees,status,created_at) VALUES ('NY-NOEMAIL','noemail','Old Visitor','0712345678','feeding','2099-05-01','11:00',1,'automatically_confirmed','2026-01-01')");
    $db->exec("INSERT INTO booking_emails(booking_id,kind,payload,idempotency_key,status,attempts,first_attempt_at,last_error) VALUES (2,'destination','{}','review/old','review',8,100,'Original review reason')");
    $before=$db->query('SELECT * FROM booking_emails ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    putenv('FEEDBACK_EMAIL_ENABLED=1');putenv('BOOKING_SITE_URL=https://visits.example.com');
    $store=new BookingStore($path);new BookingStore($path);
    $after=$db->query('SELECT * FROM booking_emails ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if(count($before)!==count($after))throw new RuntimeException('Migration changed email count');
    foreach($before as $i=>$row)foreach($row as $key=>$value)if($after[$i][$key]!==$value)throw new RuntimeException('Migration changed existing email '.$key);
    if($store->findByToken('legacy')['email']!=='legacy@example.com')throw new RuntimeException('Migration changed visitor email');
    if((int)$db->query('SELECT COUNT(*) FROM feedback_invitations')->fetchColumn()!==0)throw new RuntimeException('Migration backfilled invitations');
    echo "PASS repeatable migration preserves bookings, both email kinds, payloads, provider IDs and retries without backfill\n";
} finally {unset($db,$store);foreach(glob($directory.'/*') as $file)unlink($file);rmdir($directory);}
