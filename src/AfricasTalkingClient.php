<?php
declare(strict_types=1);
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

    /** Returns provider acceptance ID, not a handset delivery receipt. */
    public function send(string $recipient, string $message): string {
        if (!in_array($this->environment, ['sandbox', 'live'], true)
            || !preg_match('/^[^\s\x00-\x1F\x7F]+$/D', $this->username)
            || !preg_match('/^[^\s\x00-\x1F\x7F]+$/D', $this->apiKey)
            || preg_match('/[\x00-\x1F\x7F]/', $this->senderId)
            || ($this->environment === 'sandbox') !== ($this->username === 'sandbox')) {
            throw new RuntimeException('Invalid Africa’s Talking configuration');
        }
        if (!preg_match('/^\+[1-9][0-9]{6,14}$/D', $recipient) || trim($message) === '') {
            throw new RuntimeException('Provide one international-format recipient and a nonempty message');
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
        } catch (Throwable $error) {
            throw new RuntimeException('SMS transport failure; acceptance unknown');
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('SMS API HTTP '.$response['status'].'; request not confirmed');
        }
        $result = json_decode($response['body'], true);
        $recipients = $result['SMSMessageData']['Recipients'] ?? null;
        if (!is_array($recipients) || count($recipients) !== 1 || !is_array($recipients[0] ?? null)
            || ($recipients[0]['number'] ?? null) !== $recipient || !is_int($recipients[0]['statusCode'] ?? null)) {
            throw new RuntimeException('Invalid SMS API response; acceptance unknown');
        }
        $entry = $recipients[0];
        if ($entry['statusCode'] !== 101) throw new RuntimeException('SMS recipient rejected (code '.$entry['statusCode'].')');
        if (!is_string($entry['messageId'] ?? null) || trim($entry['messageId']) === '') {
            throw new RuntimeException('Invalid SMS API response; acceptance unknown');
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
            if ($response === false) throw new RuntimeException('SMS transport failure; acceptance unknown');
            return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $response];
        } finally { unset($curl); }
    }
}
