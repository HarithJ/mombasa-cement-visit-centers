<?php
declare(strict_types=1);
// The decision service: what a decision stores and which messages it queues.
require __DIR__.'/../src/BookingDecisions.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function database(string $path): PDO { return new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
putenv('BOOKING_EMAIL_ENABLED=1'); putenv('BOOKING_SMS_ENABLED=1'); putenv('FEEDBACK_EMAIL_ENABLED=1');
putenv('BOOKING_SITE_URL=https://visits.example.com');
$input = ['fullName' => 'Vera Visitor', 'phone' => '0712345678', 'email' => 'vera@example.test', 'location' => 'galana', 'visitDate' => '2099-05-01', 'timeSlot' => '10:00', 'attendees' => '3', 'overnight' => false, 'arrivalDate' => null, 'departureDate' => null, 'overnightGuests' => null, 'organization' => null];
$overnight = ['overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '2'];
$beforeVisit = strtotime('2099-04-30T09:00:00Z');
$directory = sys_get_temp_dir().'/nyumba-decisions-'.bin2hex(random_bytes(8));
$path = $directory.'/bookings.sqlite';
try {
    $users = UserStore::open($path);
    $priya = $users->add(['name' => 'Priya Planner', 'email' => 'priya@example.test', 'phone' => '0711000001', 'role' => 'team']);
    $ada = $users->add(['name' => 'Ada Admin', 'email' => 'ada@example.test', 'phone' => '0711000002', 'role' => 'admin']);
    $tom = $users->add(['name' => 'Tom Team', 'email' => 'tom@example.test', 'phone' => '0711000003', 'role' => 'team']);
    $gone = $users->add(['name' => 'Olga Gone', 'email' => 'olga@example.test', 'phone' => '0711000004', 'role' => 'team']);
    $users->deactivate($gone['id']);
    $store = new BookingStore($path);
    $db = database($path);
    $decisions = new BookingDecisions($db);
    $emails = function (array $booking, string $kind) use ($db): array {
        $query = $db->prepare('SELECT user_id, idempotency_key, status, payload FROM booking_emails WHERE booking_id = ? AND kind = ? ORDER BY id');
        $query->execute([$booking['id'], $kind]);
        return array_map(fn($row) => $row + json_decode($row['payload'], true), $query->fetchAll());
    };
    $sms = function (array $booking) use ($db): array {
        $query = $db->prepare("SELECT recipient, message FROM booking_sms WHERE booking_id = ? AND audience = 'decision' ORDER BY id");
        $query->execute([$booking['id']]);
        return $query->fetchAll();
    };

    // Confirming a pending booking.
    $booking = $store->create($input, 'confirm');
    $ref = $booking['reference'];
    $result = $decisions->decide($booking['id'], $priya, 'confirmed', null, 'first', $beforeVisit);
    check($result['ok'] === true && $result['error'] === null && $result['booking']['status'] === 'confirmed', 'A pending booking can be confirmed');
    $history = $decisions->history($booking['id']);
    check(count($history) === 1 && $history[0]['decision'] === 'confirmed' && $history[0]['previous_status'] === 'pending' && $history[0]['reason'] === null && (int)$history[0]['user_id'] === $priya['id'] && $history[0]['user_name'] === 'Priya Planner' && $history[0]['decided_at'] === gmdate('Y-m-d\TH:i:s\Z', $beforeVisit), 'The decision is recorded under the user, at the given time');
    $visitor = $emails($booking, 'decision_visitor');
    check(count($visitor) === 1 && $visitor[0]['to'] === ['vera@example.test'] && $visitor[0]['subject'] === "Your visit to Galana Farm is confirmed — $ref", 'Visitor confirmed email subject');
    check(str_contains($visitor[0]['html'], 'Your visit is confirmed') && str_contains($visitor[0]['html'], 'We look forward to welcoming you.'), 'Visitor confirmed email heading');
    foreach ([
        "Hi Vera Visitor,\nGood news: your visit to Galana Farm on Fri 1 May 2099 at 10:00 am EAT is confirmed.\nKeep your booking reference handy, and get in touch if you have any questions before you arrive.\n",
        "Booking reference: $ref", 'Transport: Not requested', 'Status: Confirmed', 'Questions before your visit?', 'Jaco',
    ] as $line) check(str_contains($visitor[0]['text'], $line), 'Visitor confirmed email has: '.$line);
    check(!str_contains($visitor[0]['text'], 'Priya'), 'The confirmed email does not name who confirmed');
    check(str_contains($visitor[0]['html'], "Please keep your booking reference: $ref"), 'Visitor confirmed email footer');
    check($sms($booking) === [['recipient' => '+254712345678', 'message' => "Hi Vera Visitor, your visit to Galana Farm on 1 May 2099 at 10:00 am EAT is confirmed. We look forward to welcoming you! Ref $ref."]], 'Visitor confirmed SMS');
    $team = $emails($booking, 'decision_team');
    check(array_map(fn($row) => (int)$row['user_id'], $team) === [$ada['id'], $tom['id']], 'Team decision email goes to every other active user');
    check($team[1]['to'] === ['tom@example.test'] && $team[1]['subject'] === "Confirmed by Priya Planner: visit on Fri 1 May 2099 — $ref", 'Team confirmed email subject');
    check(str_contains($team[1]['html'], 'Visit confirmed'), 'Team confirmed eyebrow');
    check(str_contains($team[1]['text'], "Priya Planner confirmed Vera Visitor's visit to Galana Farm on Fri 1 May 2099 at 10:00 am EAT (3 people). Vera Visitor has been told by email and SMS. No action needed."), 'Team confirmed body');
    check(str_contains($team[1]['text'], "Booking reference: $ref") && str_contains($team[1]['text'], "View in admin panel:\nhttps://visits.example.com/admin/bookings/".$booking['id']), 'Team email has the booking details and an admin link');
    check(!str_contains($team[1]['html'], 'bgcolor="#173f35" style="background:#173f35;border-radius'), 'Team decision emails have no buttons');
    check(count(array_unique(array_column([...$visitor, ...$team], 'idempotency_key'))) === 3, 'Every message has its own idempotency key');
    echo "PASS confirming a pending booking records it and tells the visitor and the rest of the team\n";

    // Confirming schedules the feedback invitation.
    $feedback = $emails($booking, 'feedback');
    check(count($feedback) === 1 && $feedback[0]['status'] === 'pending' && $feedback[0]['to'] === ['vera@example.test'], 'A confirmed booking gets a feedback invitation');
    echo "PASS confirming schedules the feedback invitation\n";

    // Rejecting: the reason is required, 1-500 characters after trimming.
    $booking = $store->create($input, 'reject');
    $ref = $booking['reference'];
    foreach ([[null, BookingDecisions::REASON_MISSING], ["  \n ", BookingDecisions::REASON_MISSING], [str_repeat('x', 501), BookingDecisions::REASON_TOO_LONG]] as [$reason, $message]) {
        $result = $decisions->decide($booking['id'], $priya, 'rejected', $reason, 'first', $beforeVisit);
        check($result['ok'] === false && $result['error'] === 'invalid' && $result['errors'] === ['reason' => $message] && $result['booking']['status'] === 'pending', 'Reason rule: '.$message);
    }
    check($decisions->history($booking['id']) === [] && $emails($booking, 'decision_team') === [] && $sms($booking) === [], 'An invalid rejection changes nothing');
    $reason = '  Flooded access road. '.str_repeat('y', 477).'  ';
    $result = $decisions->decide($booking['id'], $priya, 'rejected', $reason, 'first', $beforeVisit);
    check($result['ok'] && $result['booking']['status'] === 'rejected' && $result['decision']['reason'] === trim($reason), 'A 500-character reason is accepted and stored trimmed');
    $visitor = $emails($booking, 'decision_visitor');
    check(count($visitor) === 1 && $visitor[0]['subject'] === "About your visit request to Galana Farm — $ref", 'Visitor declined email subject');
    check(str_contains($visitor[0]['html'], 'Visit not confirmed') && str_contains($visitor[0]['html'], 'We can&#039;t host your visit on this date.'), 'Visitor declined email heading');
    foreach ([
        "Hi Vera Visitor,\nSorry, we're unable to confirm your visit to Galana Farm on Fri 1 May 2099 at 10:00 am EAT.\nPlease contact Priya Planner to talk about it or find another date:\n\nPriya Planner — +254711000001 · priya@example.test\n",
        "Booking reference: $ref", 'Visit date: Fri 1 May 2099', 'Time: 10:00 am EAT', 'Attendees: 3', 'Status: Not confirmed',
    ] as $line) check(str_contains($visitor[0]['text'], $line), 'Visitor declined email has: '.$line);
    check(str_contains($visitor[0]['html'], "Please quote your booking reference: $ref") && str_contains($visitor[0]['html'], 'mailto:priya@example.test') && str_contains($visitor[0]['html'], 'tel:+254711000001'), 'Visitor declined email has the decider contact box and footer');
    check($sms($booking) === [['recipient' => '+254712345678', 'message' => "Hi Vera Visitor, sorry, we can't confirm your visit to Galana Farm on 1 May 2099. Please contact Priya Planner on +254711000001 or priya@example.test. Ref $ref."]], 'Visitor declined SMS');
    foreach ([...$visitor, ...array_map(fn($m) => ['text' => $m['message'], 'html' => ''], $sms($booking))] as $message) check(!str_contains($message['text'].$message['html'], 'Flooded'), 'The visitor never sees the reason');
    $team = $emails($booking, 'decision_team');
    check(count($team) === 2 && $team[0]['subject'] === "Rejected by Priya Planner: visit on Fri 1 May 2099 — $ref" && str_contains($team[0]['html'], 'Visit rejected'), 'Team rejected email subject');
    check(str_contains($team[0]['text'], "Priya Planner rejected Vera Visitor's visit to Galana Farm on Fri 1 May 2099 at 10:00 am EAT (3 people). The visitor has been asked to contact Priya Planner.") && str_contains($team[0]['text'], 'Reason: Flooded access road.'), 'Team rejected email has the body and the reason');
    echo "PASS rejecting needs a reason the team sees and the visitor never does\n";

    // Rejecting a confirmed booking cancels its queued feedback invitation; confirming again restores it.
    $booking = $store->create($input, 'feedback');
    $decisions->decide($booking['id'], $priya, 'confirmed', null, 'first', $beforeVisit);
    $result = $decisions->decide($booking['id'], $ada, 'rejected', 'Road closed', 'change', $beforeVisit);
    check($result['ok'] && $emails($booking, 'feedback')[0]['status'] === 'suppressed', 'Rejecting cancels the queued feedback invitation');
    $decisions->decide($booking['id'], $tom, 'confirmed', null, 'change', $beforeVisit);
    check(count($emails($booking, 'feedback')) === 1 && $emails($booking, 'feedback')[0]['status'] === 'pending', 'Confirming again restores the one invitation');
    $rejected = $store->create($input, 'rejected-first');
    $decisions->decide($rejected['id'], $priya, 'rejected', 'No', 'first', $beforeVisit);
    check($emails($rejected, 'feedback') === [], 'A rejected booking gets no feedback invitation');
    echo "PASS feedback invitations follow the decision\n";

    // First decision wins; 'change' only flips confirmed <-> rejected; nothing goes back to pending.
    $booking = $store->create($input, 'modes');
    $ref = $booking['reference'];
    check($decisions->decide($booking['id'], $ada, 'rejected', 'x', 'change', $beforeVisit)['error'] === 'conflict', "'change' refuses a pending booking");
    check($decisions->decide($booking['id'], $priya, 'confirmed', null, 'first', $beforeVisit)['ok'], 'First decision');
    $late = $decisions->decide($booking['id'], $tom, 'rejected', 'Too late', 'first', $beforeVisit);
    check($late['ok'] === false && $late['error'] === 'conflict' && $late['booking']['status'] === 'confirmed', 'A second first decision is refused');
    check($decisions->decide($booking['id'], $tom, 'confirmed', null, 'change', $beforeVisit)['error'] === 'conflict', "'change' to the current status is a conflict");
    $threw = false;
    try { $decisions->decide($booking['id'], $tom, 'pending', null, 'change', $beforeVisit); } catch (InvalidArgumentException) { $threw = true; }
    check($threw, 'A booking never goes back to pending');
    check(count($decisions->history($booking['id'])) === 1, 'Refused decisions leave no history');
    $changed = $decisions->decide($booking['id'], $ada, 'rejected', 'Flooding', 'change', $beforeVisit);
    check($changed['ok'] && $changed['decision']['previous_status'] === 'confirmed', 'Confirmed can change to rejected');
    $visitor = $emails($booking, 'decision_visitor')[1];
    check($visitor['subject'] === "Change to your visit to Galana Farm — $ref" && str_contains($visitor['html'], 'We can no longer host your visit on this date.') && str_contains($visitor['text'], "Sorry, we're no longer able to host your visit to Galana Farm on Fri 1 May 2099 at 10:00 am EAT, which we'd confirmed earlier.\nPlease contact Ada Admin"), 'Visitor email for a confirmed visit now declined');
    check($sms($booking)[1]['message'] === "Hi Vera Visitor, sorry, we can no longer host your visit to Galana Farm on 1 May 2099. Please contact Ada Admin on +254711000002 or ada@example.test. Ref $ref.", 'Visitor SMS for a confirmed visit now declined');
    $team = array_values(array_filter($emails($booking, 'decision_team'), fn($row) => str_starts_with($row['subject'], 'Changed')));
    check(array_map(fn($row) => (int)$row['user_id'], $team) === [$priya['id'], $tom['id']] && $team[0]['subject'] === "Changed by Ada Admin: visit on Fri 1 May 2099 — $ref", 'Changed team email goes to everyone but the decider');
    check(str_contains($team[0]['text'], "Ada Admin changed Vera Visitor's visit to Galana Farm on Fri 1 May 2099 at 10:00 am EAT (3 people) from confirmed to rejected. The visitor has been asked to contact Ada Admin.") && str_contains($team[0]['text'], 'Reason: Flooding'), 'Changed-to-rejected team email');
    check($decisions->decide($booking['id'], $tom, 'confirmed', null, 'change', $beforeVisit)['ok'], 'Rejected can change to confirmed');
    $visitor = $emails($booking, 'decision_visitor')[2];
    check($visitor['subject'] === "Your visit to Galana Farm is confirmed — $ref" && str_contains($visitor['text'], "Good news: we've been able to confirm your visit to Galana Farm on Fri 1 May 2099 at 10:00 am EAT after all."), 'Visitor email for a declined visit now confirmed');
    $team = array_values(array_filter($emails($booking, 'decision_team'), fn($row) => str_starts_with($row['subject'], 'Changed by Tom')));
    check(count($team) === 2 && str_contains($team[0]['text'], 'from rejected to confirmed. Vera Visitor has been told by email and SMS. No action needed.') && !str_contains($team[0]['text'], 'Reason:'), 'Changed-to-confirmed team email');
    $history = $decisions->history($booking['id']);
    check(array_column($history, 'decision') === ['confirmed', 'rejected', 'confirmed'] && array_column($history, 'user_name') === ['Tom Team', 'Ada Admin', 'Priya Planner'], 'History is newest first');
    echo "PASS the first decision wins, and changes flip between confirmed and rejected with their own wording\n";

    // Decisions stop at 23:59:59 Nairobi on the visit date (20:59:59 UTC).
    $booking = $store->create($input, 'cutoff');
    $closed = $decisions->decide($booking['id'], $priya, 'confirmed', null, 'first', strtotime('2099-05-01T21:00:00Z'));
    check($closed['ok'] === false && $closed['error'] === 'closed' && $decisions->history($booking['id']) === [], 'No decisions after the visit day');
    check($decisions->decide($booking['id'], $priya, 'confirmed', null, 'first', strtotime('2099-05-01T20:59:59Z'))['ok'], 'Decisions allowed until 23:59:59 Nairobi');
    check($decisions->decide($booking['id'], $priya, 'rejected', 'x', 'change', strtotime('2099-05-01T21:00:00Z'))['error'] === 'closed', 'No changes after the visit day');
    echo "PASS decisions close at the end of the visit day\n";

    // Visitors without an email get the SMS only; scenario wording.
    $booking = $store->create(array_merge($input, ['email' => '', 'transportRequested' => 'on'] + $overnight), 'no-email');
    $decisions->decide($booking['id'], $priya, 'confirmed', null, 'first', $beforeVisit);
    check($emails($booking, 'decision_visitor') === [] && count($emails($booking, 'decision_team')) === 2, 'No visitor email without an address');
    check($sms($booking)[0]['message'] === 'Hi Vera Visitor, your visit to Galana Farm on 1 May 2099 at 10:00 am EAT is confirmed, including transport and your overnight stay. We look forward to welcoming you! Ref '.$booking['reference'].'.', 'Overnight + transport SMS');
    foreach ([
        [['transportRequested' => 'on'], ', including transport.', 'Your transport is confirmed too. Our team will contact you about pickup details.', ['Transport: Confirmed']],
        [$overnight, ', including your overnight stay.', "Your overnight stay is confirmed too. Our team will contact you if there's anything to arrange.", ['Transport: Not requested', 'Overnight stay: Confirmed']],
        [['transportRequested' => 'on'] + $overnight, ', including transport and your overnight stay.', 'Your transport and overnight stay are confirmed too. Our team will contact you about pickup details.', ['Transport: Confirmed', 'Overnight stay: Confirmed']],
    ] as $i => [$extra, $smsEnding, $line, $rows]) {
        $variant = $store->create(array_merge($input, $extra), 'scenario-'.$i);
        $decisions->decide($variant['id'], $priya, 'confirmed', null, 'first', $beforeVisit);
        check(str_contains($sms($variant)[0]['message'], 'EAT is confirmed'.$smsEnding), 'Confirmed SMS scenario: '.$smsEnding);
        $text = $emails($variant, 'decision_visitor')[0]['text'];
        check(str_contains($text, "is confirmed.\n$line\n"), 'Confirmed email scenario: '.$line);
        foreach ($rows as $row) check(str_contains($text, $row), 'Confirmed email row: '.$row);
    }
    echo "PASS visitors without email get SMS only, and the confirmed wording follows the scenario\n";

    // Messaging switches are respected.
    putenv('BOOKING_EMAIL_ENABLED=0'); putenv('BOOKING_SMS_ENABLED=0');
    $booking = $store->create($input, 'quiet');
    check($decisions->decide($booking['id'], $priya, 'confirmed', null, 'first', $beforeVisit)['ok'] && $emails($booking, 'decision_visitor') === [] && $emails($booking, 'decision_team') === [] && $sms($booking) === [], 'No messages when email and SMS are off');
    echo "PASS disabled email and SMS queue nothing\n";
} finally {
    unset($db, $store, $users, $decisions);
    foreach (glob($directory.'/*') as $file) unlink($file);
    if (is_dir($directory)) rmdir($directory);
}
echo "Decision service tests passed.\n";
