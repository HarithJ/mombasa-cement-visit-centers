<?php
declare(strict_types=1);
require_once __DIR__.'/SmsFailure.php';
require_once __DIR__.'/PhoneNumber.php';
final class AfricasTalkingClient {
    /** @param ?Closure(string, array, string): array{status: int, body: string} $transport */
    public function __construct(
        private string $username,
        #[SensitiveParameter] private string $apiKey,
        private string $environment,
        private string $senderId = '',
        private ?Closure $transport = null,
    ) {}

    public static function fromEnvironment(?Closure $transport = null): self {
        return new self(
            getenv('AFRICAS_TALKING_USERNAME') ?: '',
            getenv('AFRICAS_TALKING_API_KEY') ?: '',
            getenv('AFRICAS_TALKING_ENVIRONMENT') ?: '',
            getenv('AFRICAS_TALKING_SENDER_ID') ?: '',
            $transport,
        );
    }

    public function validateConfiguration(): void {
        if (!in_array($this->environment, ['sandbox', 'live'], true)
            || !preg_match('/^[^\s\x00-\x1F\x7F]+$/D', $this->username)
            || !preg_match('/^[^\s\x00-\x1F\x7F]+$/D', $this->apiKey)
            || preg_match('/[\x00-\x1F\x7F]/', $this->senderId)
            || ($this->environment === 'sandbox') !== ($this->username === 'sandbox')) {
            throw new SmsFailure('Invalid Africa’s Talking configuration', 'configuration');
        }
        if ($this->transport === null && !extension_loaded('curl')) throw new SmsFailure('PHP cURL is required', 'configuration');
    }

    public function profile(): string {
        return json_encode([$this->environment, $this->username, $this->senderId], JSON_THROW_ON_ERROR);
    }

    /** Returns provider acceptance ID, not a handset delivery receipt. */
    public function send(string $recipient, string $message): string {
        $this->validateConfiguration();
        if (!PhoneNumber::smsRoutable($recipient) || trim($message) === '') {
            throw new SmsFailure('Provide one international-format recipient and a nonempty message', 'rejected');
        }
        $url = $this->environment === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';
        $form = ['username' => $this->username, 'to' => $recipient, 'message' => $message];
        if ($this->senderId !== '') $form['from'] = $this->senderId;
        $headers = ['apiKey: '.$this->apiKey, 'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];
        $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        try {
            $response = ($this->transport ?? $this->request(...))($url, $headers, $body);
        } catch (SmsFailure $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new SmsFailure('SMS transport failure; acceptance unknown');
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new SmsFailure('SMS API HTTP '.$response['status'].'; request not confirmed', in_array($response['status'], [401, 403], true) ? 'configuration' : ($response['status'] === 429 ? 'retryable' : 'uncertain'));
        }
        $result = json_decode($response['body'], true);
        $recipients = $result['SMSMessageData']['Recipients'] ?? null;
        if (!is_array($recipients) || count($recipients) !== 1 || !is_array($recipients[0] ?? null)
            || ($recipients[0]['number'] ?? null) !== $recipient || !is_int($recipients[0]['statusCode'] ?? null)) {
            throw new SmsFailure('Invalid SMS API response; acceptance unknown');
        }
        $entry = $recipients[0];
        if (!in_array($entry['statusCode'], [100, 101, 102], true)) throw new SmsFailure('SMS recipient rejected (code '.$entry['statusCode'].')', in_array($entry['statusCode'], [401, 402, 405], true) ? 'configuration' : 'rejected');
        if (!is_string($entry['messageId'] ?? null) || trim($entry['messageId']) === '') {
            throw new SmsFailure('Invalid SMS API response; acceptance unknown');
        }
        return $entry['messageId'];
    }

    private function request(string $url, array $headers, string $body): array {
        $curl = curl_init($url);
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $response = curl_exec($curl);
            if ($response === false) {
                if (in_array(curl_errno($curl), [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT], true)) throw new SmsFailure('SMS connection unavailable', 'retryable');
                throw new SmsFailure('SMS transport failure; acceptance unknown');
            }
            return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $response];
        } finally { unset($curl); }
    }
}
