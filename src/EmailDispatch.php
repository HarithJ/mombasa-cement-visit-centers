<?php
declare(strict_types=1);
require_once __DIR__.'/EmailWorker.php';
require_once __DIR__.'/ResendClient.php';
// Sends queued emails: from the worker timer, and from a web request right after its page has gone out.
final class EmailDispatch {
    // One pass over both queues. Sign-in and reset links always send; booking and feedback emails follow their own switches.
    public static function run(PDO $db, callable $send, string $from): array {
        $result = (new EmailWorker($db, null, 'account_emails'))->run($send, $from);
        if (BookingEmail::configured() || Feedback::enabled()) {
            foreach ((new EmailWorker($db))->run($send, $from) as $key => $count) $result[$key] += $count;
        }
        return $result;
    }

    // Call during a request that may queue email. When the request ends, the page is sent first and then
    // the queue is tried once, so emails leave within a second. Anything that fails stays queued for the timer.
    // Under PHP-FPM the defaults finish the response with fastcgi_finish_request() and send through Resend.
    public static function afterResponse(string $dbPath, ?callable $finish = null, ?callable $send = null, ?string $from = null): void {
        $finish ??= function_exists('fastcgi_finish_request') ? 'fastcgi_finish_request' : null;
        if ($finish === null) return;
        if ($send === null) {
            $key = getenv('RESEND_API_KEY') ?: '';
            if ($key === '' || !extension_loaded('curl')) return;
            $send = [new ResendClient($key), 'send'];
        }
        $from ??= getenv('RESEND_FROM') ?: '';
        register_shutdown_function(function () use ($dbPath, $finish, $send, $from) {
            // Save the session first: the visitor's next page must not wait for the session lock while we send.
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            $finish();
            ignore_user_abort(true);
            try {
                $db = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $db->exec('PRAGMA busy_timeout = 5000');
                self::run($db, $send, $from);
            } catch (Throwable $error) {
                error_log('Nyumba email send after response failed: '.get_class($error));
            }
        });
    }
}
