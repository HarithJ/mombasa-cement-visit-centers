<?php
declare(strict_types=1);
// New bookings wait for a decision: what is stored and which messages are queued when a visit is requested.
require __DIR__.'/../src/UserStore.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function freshPath(string $name): string {
    $directory = sys_get_temp_dir().'/nyumba-approval-'.$name.'-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    return $directory.'/bookings.sqlite';
}
function cleanUp(string $path): void { foreach (glob(dirname($path).'/*') as $file) unlink($file); rmdir(dirname($path)); }
function database(string $path): PDO { return new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
putenv('BOOKING_EMAIL_ENABLED=0'); putenv('BOOKING_SMS_ENABLED=0'); putenv('FEEDBACK_EMAIL_ENABLED=0');
putenv('BOOKING_SITE_URL=https://visits.example.com');
$input = ['fullName' => 'Vera Visitor', 'phone' => '+254712345678', 'email' => 'vera@example.test', 'location' => 'galana', 'visitDate' => '2099-05-01', 'timeSlot' => '10:00', 'attendees' => '3', 'overnight' => false, 'arrivalDate' => null, 'departureDate' => null, 'overnightGuests' => null, 'organization' => 'Acme School'];

// Existing bookings count as confirmed, with no history and no messages.
$path = freshPath('migration');
try {
    $db = database($path);
    $db->exec('CREATE TABLE schema_migrations(version TEXT PRIMARY KEY)');
    foreach (glob(__DIR__.'/../migrations/*.sql') as $file) {
        if (strcmp(basename($file), '021') >= 0) continue;
        $db->exec(file_get_contents($file));
        $db->prepare('INSERT INTO schema_migrations VALUES (?)')->execute([basename($file)]);
    }
    $db->exec("INSERT INTO bookings(reference,submission_token,full_name,phone,destination,visit_date,booked_time,attendees,status,created_at,overnight,arrival_date,departure_date,overnight_guests) VALUES ('NY-OLD','old','Old Visitor','0712345678','galana','2099-05-01','11:00',2,'automatically_confirmed','2026-01-01T00:00:00Z',1,'2099-05-01','2099-05-02',2)");
    $db->exec("INSERT INTO booking_emails(booking_id,kind,payload,idempotency_key,status) VALUES (1,'destination','{}','booking/NY-OLD','accepted')");
    $db->exec("INSERT INTO booking_sms(booking_id,audience,recipient,message,status) VALUES (1,'manager','+254700000001','Old','accepted')");
    new BookingStore($path);
    $old = $db->query("SELECT * FROM bookings WHERE reference = 'NY-OLD'")->fetch();
    check($old['status'] === 'confirmed' && $old['overnight'] === 1 && $old['departure_date'] === '2099-05-02', 'Existing bookings become confirmed and keep their details');
    check((int)$db->query('SELECT COUNT(*) FROM booking_decisions')->fetchColumn() === 0, 'No history rows for existing bookings');
    check((int)$db->query('SELECT COUNT(*) FROM booking_emails')->fetchColumn() === 1 && (int)$db->query('SELECT COUNT(*) FROM booking_sms')->fetchColumn() === 1, 'No messages for existing bookings');
    $invalid = false;
    try { $db->exec("UPDATE bookings SET overnight_guests = 9 WHERE id = 1"); } catch (PDOException) { $invalid = true; }
    check($invalid, 'Overnight rules still apply after the rebuild');
    $invalid = false;
    try { $db->exec("UPDATE bookings SET status = 'automatically_confirmed' WHERE id = 1"); } catch (PDOException) { $invalid = true; }
    check($invalid, 'Only pending, confirmed and rejected are valid statuses');
    echo "PASS existing bookings become confirmed without history or messages\n";
} finally { unset($db); cleanUp($path); }

// New bookings are pending, and no feedback invitation is created until a booking is confirmed.
$path = freshPath('pending');
try {
    putenv('FEEDBACK_EMAIL_ENABLED=1');
    $store = new BookingStore($path);
    $booking = $store->create($input, 'pending');
    check($booking['status'] === 'pending', 'New bookings are pending');
    $db = database($path);
    check((int)$db->query('SELECT COUNT(*) FROM feedback_invitations')->fetchColumn() === 0 && (int)$db->query("SELECT COUNT(*) FROM booking_emails WHERE kind = 'feedback'")->fetchColumn() === 0, 'No feedback invitation for a pending booking');
    echo "PASS new bookings are pending, with no feedback invitation yet\n";
} finally { putenv('FEEDBACK_EMAIL_ENABLED=0'); unset($db, $store); cleanUp($path); }

// The team email goes to every active user, each with their own Accept and Reject links.
$path = freshPath('team-email');
try {
    putenv('BOOKING_EMAIL_ENABLED=1');
    $users = UserStore::open($path);
    $ada = $users->add(['name' => 'Ada Admin', 'email' => 'ada@example.test', 'phone' => '0711000001', 'role' => 'admin']);
    $tom = $users->add(['name' => 'Tom Team', 'email' => 'tom@example.test', 'phone' => '0711000002', 'role' => 'team']);
    $old = $users->add(['name' => 'Olga Gone', 'email' => 'olga@example.test', 'phone' => '0711000003', 'role' => 'admin']);
    $users->deactivate($old['id']);
    $store = new BookingStore($path);
    $booking = $store->create($input, 'team-email');
    $store->create($input, 'team-email');
    $db = database($path);
    $requests = $db->query("SELECT * FROM booking_emails WHERE kind = 'decision_request' ORDER BY user_id")->fetchAll();
    check(count($requests) === 2, 'One team email per active user, none for deactivated users, none on replay');
    check((int)$db->query("SELECT COUNT(*) FROM booking_emails WHERE kind = 'destination'")->fetchColumn() === 0, 'No shared team email any more');
    $links = [];
    foreach ([[$ada, $requests[0]], [$tom, $requests[1]]] as [$user, $row]) {
        $payload = json_decode($row['payload'], true);
        check((int)$row['user_id'] === $user['id'] && $payload['to'] === [$user['email']], 'Each team email goes to just its user');
        check($row['idempotency_key'] === 'decision-request/'.$booking['reference'].'/'.$user['id'], 'Team email keys are per booking and user');
        check(preg_match_all('~https://visits\.example\.com/decide\?token=([a-f0-9]{64})~', $payload['text'], $found) === 2, 'Two personal links in the text email');
        [$accept, $reject] = $found[1];
        check(str_contains($payload['text'], "Accept visit:\nhttps://visits.example.com/decide?token=$accept") && str_contains($payload['text'], "Reject visit:\nhttps://visits.example.com/decide?token=$reject"), 'Accept and Reject buttons in the text email');
        check(str_contains($payload['html'], "decide?token=$accept") && str_contains($payload['html'], "decide?token=$reject") && str_contains($payload['html'], 'Accept visit') && str_contains($payload['html'], 'Reject visit'), 'Accept and Reject buttons in the HTML email');
        foreach (['accept' => $accept, 'reject' => $reject] as $action => $token) {
            $link = $db->prepare('SELECT * FROM decision_links WHERE token_hash = ?');
            $link->execute([hash('sha256', $token)]);
            $link = $link->fetch();
            check($link && (int)$link['booking_id'] === $booking['id'] && (int)$link['user_id'] === $user['id'] && $link['action'] === $action && (int)$link['credential_version'] === $user['credential_version'], "The $action link belongs to this user and booking");
            $links[] = $token;
        }
    }
    check(count(array_unique($links)) === 4 && (int)$db->query('SELECT COUNT(*) FROM decision_links')->fetchColumn() === 4, 'Every link is different');
    $stored = json_encode($db->query('SELECT * FROM decision_links')->fetchAll());
    foreach ($links as $token) check(!str_contains($stored, $token), 'Only token hashes are stored');
    $contacts = require __DIR__.'/../config/contacts.php';
    $recipients = array_merge(...array_map(fn($row) => json_decode($row['payload'], true)['to'], $db->query("SELECT payload FROM booking_emails WHERE kind <> 'visitor'")->fetchAll()));
    foreach ([...array_column($contacts['galana']['people'], 'email'), 'harithjaved@gmail.com', 'apoorvchit@gmail.com'] as $email) check(!in_array($email, $recipients, true), 'Recipients come from the users table only');
    echo "PASS the team email goes to each active user with their own Accept and Reject links\n";

    $tomEmail = json_decode($requests[1]['payload'], true);
    $ref = $booking['reference'];
    check($tomEmail['subject'] === "Please confirm: visit to Galana Farm on Fri 1 May 2099 — $ref", 'Team email subject');
    foreach (['Needs a decision', 'A new visit to confirm.'] as $heading) check(str_contains($tomEmail['html'], $heading), 'Team email heading: '.$heading);
    foreach ([
        "Hi Tom,\nVera Visitor has asked to visit Galana Farm on Fri 1 May 2099 at 10:00 am EAT for a total of 3 people.\nNo transport or overnight stay was requested.\nPlease accept or reject the visit. The first person to decide settles it, and everyone else is told.",
        "Booking reference: $ref", 'Visit date: Fri 1 May 2099', 'Time: 10:00 am EAT', 'Attendees: 3', 'Transport: Not requested', 'Visitor: Vera Visitor', 'Organization: Acme School', 'Phone: +254712345678', 'Email: vera@example.test',
        "Call the visitor:\ntel:+254712345678",
        "These buttons are personal to you; please don't forward this email. They work until 23:59 on Fri 1 May 2099. You can also decide from the admin panel.",
    ] as $line) check(str_contains($tomEmail['text'], $line), 'Team email text: '.$line);
    foreach ([
        [['overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '2'], "They've asked to stay overnight."],
        [['transportRequested' => 'on'], "They've asked for company transport."],
        [['overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '2', 'transportRequested' => 'on'], "They've asked for company transport and to stay overnight."],
    ] as $i => [$extra, $line]) {
        $variant = $store->create(array_merge($input, $extra), 'scenario-'.$i);
        $query = $db->prepare("SELECT payload FROM booking_emails WHERE kind = 'decision_request' AND booking_id = ? AND user_id = ?");
        $query->execute([$variant['id'], $tom['id']]);
        check(str_contains(json_decode($query->fetchAll()[0]["payload"], true)["text"], "for a total of 3 people.\n$line\n"), 'Team email scenario line: '.$line);
    }
    echo "PASS the team email uses the approved wording\n";

    putenv('BOOKING_SITE_URL=');
    $refused = false;
    try { $store->create($input, 'no-site'); } catch (RuntimeException) { $refused = true; }
    check($refused && $store->findByToken('no-site') === null, 'Personal links need a canonical BOOKING_SITE_URL; nothing is saved without one');
    echo "PASS bookings are refused rather than sent with broken links\n";
} finally { putenv('BOOKING_EMAIL_ENABLED=0'); putenv('BOOKING_SITE_URL=https://visits.example.com'); unset($db, $store, $users); cleanUp($path); }

// The visitor is told the request is received and not confirmed yet.
$path = freshPath('visitor');
try {
    putenv('BOOKING_EMAIL_ENABLED=1'); putenv('BOOKING_SMS_ENABLED=1');
    $store = new BookingStore($path);
    $scenarios = [
        'day' => [[], null, ''],
        'day_transport' => [['transportRequested' => 'on'], "You've also asked for transport. We'll confirm it together with your visit.", ', with transport'],
        'overnight' => [['overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '2'], "You've also asked to stay overnight. We'll confirm it together with your visit.", ' and stay overnight'],
        'overnight_transport' => [['overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '2', 'transportRequested' => 'on'], "You've also asked for transport and an overnight stay. We'll confirm both together with your visit.", ', with transport and an overnight stay'],
    ];
    $db = database($path);
    foreach ($scenarios as $name => [$extra, $line, $smsExtra]) {
        $booking = $store->create(array_merge($input, $extra), 'visitor-'.$name);
        $ref = $booking['reference'];
        $query = $db->prepare("SELECT payload FROM booking_emails WHERE kind = 'visitor' AND booking_id = ?");
        $query->execute([$booking['id']]);
        $email = json_decode($query->fetchAll()[0]['payload'], true);
        check($email['to'] === ['vera@example.test'] && $email['subject'] === "Your request to visit Galana Farm — $ref", "$name: visitor email subject");
        check(str_contains($email['html'], 'Awaiting confirmation') && str_contains($email['html'], "We&#039;ve received your request."), "$name: visitor email heading");
        $intro = "Hi Vera Visitor,\nThanks for asking to visit Galana Farm on Fri 1 May 2099 at 10:00 am EAT. The Galana team will check the date and confirm by email and SMS, usually within a day.\n";
        check(str_contains($email['text'], $intro.($line === null ? "\nBooking reference" : $line."\n\nBooking reference")), "$name: visitor email paragraphs");
        foreach (['Status: Awaiting confirmation', "Your visit isn't confirmed yet. Please don't travel until you hear from us.", 'Questions before your visit?', 'Jaco'] as $text) check(str_contains($email['text'], $text), "$name: visitor email has $text");
        check(str_contains($email['html'], "Please keep your booking reference: $ref"), "$name: visitor email footer");
        $query = $db->prepare("SELECT message FROM booking_sms WHERE audience = 'visitor' AND booking_id = ?");
        $query->execute([$booking['id']]);
        check($query->fetchAll()[0]['message'] === "Hi Vera Visitor, thanks for your request to visit Galana Farm on 1 May 2099 at 10:00 am EAT$smsExtra. It isn't confirmed yet; we'll let you know shortly. Ref $ref.", "$name: visitor SMS");
    }
    echo "PASS the visitor email and SMS say the request isn't confirmed yet\n";
} finally { putenv('BOOKING_EMAIL_ENABLED=0'); putenv('BOOKING_SMS_ENABLED=0'); unset($db, $store); cleanUp($path); }

// The new-booking SMS goes to active admins only, on the phone number on their account.
$path = freshPath('admin-sms');
try {
    putenv('BOOKING_SMS_ENABLED=1');
    $users = UserStore::open($path);
    $users->add(['name' => 'Ada Admin', 'email' => 'ada@example.test', 'phone' => '0711000001', 'role' => 'admin']);
    $users->add(['name' => 'Tom Team', 'email' => 'tom@example.test', 'phone' => '0711000002', 'role' => 'team']);
    $users->add(['name' => 'Abe Admin', 'email' => 'abe@example.test', 'phone' => '+44 7700 900123', 'role' => 'admin']);
    $gone = $users->add(['name' => 'Olga Gone', 'email' => 'olga@example.test', 'phone' => '0711000003', 'role' => 'admin']);
    $users->deactivate($gone['id']);
    $store = new BookingStore($path);
    $db = database($path);
    $booking = $store->create($input, 'admin-sms');
    $store->create($input, 'admin-sms');
    $ref = $booking['reference'];
    $jobs = $db->query('SELECT audience, recipient, message FROM booking_sms ORDER BY id')->fetchAll();
    check(array_column($jobs, 'audience') === ['visitor', 'manager', 'manager'] && array_column($jobs, 'recipient') === ['+254712345678', '+447700900123', '+254711000001'], 'Visitor plus every active admin, once each; no team users, deactivated admins or contacts.php numbers');
    check($jobs[1]['message'] === "New visit request: Vera Visitor, Galana Farm, 1 May 2099 at 10:00 am EAT, 3 people. Awaiting confirmation; see your email to accept or reject. Ref $ref.", 'Admin SMS for a day visit');
    foreach ([
        [['transportRequested' => 'on'], '3 people, transport.'],
        [['overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '2'], '3 people, overnight.'],
        [['overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '1', 'transportRequested' => 'on', 'attendees' => '1'], '1 person, transport, overnight.'],
    ] as $i => [$extra, $ending]) {
        $variant = $store->create(array_merge($input, $extra), 'admin-sms-'.$i);
        $query = $db->prepare("SELECT message FROM booking_sms WHERE audience = 'manager' AND booking_id = ?");
        $query->execute([$variant['id']]);
        check(str_contains($query->fetchAll()[0]['message'], "10:00 am EAT, $ending Awaiting confirmation;"), 'Admin SMS lists the requests: '.$ending);
    }
    echo "PASS the new-booking SMS goes to active admins only\n";
} finally { putenv('BOOKING_SMS_ENABLED=0'); unset($db, $store, $users); cleanUp($path); }
