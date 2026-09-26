<?php
declare(strict_types=1);
final class SmsFailure extends RuntimeException {
    public function __construct(string $message, public readonly string $category = 'uncertain') {
        parent::__construct($message);
    }
}
