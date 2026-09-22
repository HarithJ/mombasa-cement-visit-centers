<?php
declare(strict_types=1);
final class ResendClient {
    public function __construct(private string $apiKey) {}
    public function send(array $payload, string $key): string {
        if ($this->apiKey === '' || preg_match('/[\r\n]/', $this->apiKey)) throw new RuntimeException('Resend API key is missing or invalid');
        $curl = curl_init('https://api.resend.com/emails');
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$this->apiKey, 'Content-Type: application/json', 'Idempotency-Key: '.$key],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($body === false) throw new RuntimeException('Resend network failure');
        if ($status < 200 || $status >= 300) throw new RuntimeException('Resend HTTP '.$status);
        $result = json_decode($body, true);
        if (!is_string($result['id'] ?? null) || $result['id'] === '') throw new RuntimeException('Resend response missing email ID');
        return $result['id'];
    }
}
