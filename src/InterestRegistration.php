<?php
declare(strict_types=1);
require_once __DIR__.'/PhoneNumber.php';
final class InterestRegistration {
    // Places not yet open for booking. Visitors register interest instead; reopening a place means removing it here.
    public const PLACES = ['sahajanand', 'feeding'];
    public static function interestOnly(string $location): bool { return in_array($location, self::PLACES, true); }

    public static function validate(array $input, array $destinations): array {
        $data = [];
        foreach (['fullName', 'organization', 'email', 'phone', 'location', 'visitDate', 'attendees'] as $key) $data[$key] = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $errors = [];
        if ($data['fullName'] === '' || mb_strlen($data['fullName']) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $data['fullName'])) $errors['fullName'] = 'Enter your full name (up to 120 characters).';
        if (mb_strlen($data['organization']) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $data['organization'])) $errors['organization'] = 'Enter an organization name up to 120 characters.';
        if ($data['email'] !== '' && (strlen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) $errors['email'] = 'Enter a valid email address (up to 254 characters).';
        $phone = PhoneNumber::normalize($data['phone']);
        if ($phone === null) $errors['phone'] = 'Enter a phone number with 7–15 digits, optionally using +, spaces, parentheses or hyphens.';
        else $data['phone'] = $phone;
        if (!isset($destinations[$data['location']]) || !self::interestOnly($data['location'])) $errors['location'] = 'Choose a destination that is opening soon.';
        [$first, $last] = self::dateRange();
        $zone = new DateTimeZone('Africa/Nairobi');
        $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $data['visitDate']) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['visitDate'], $zone) : false;
        if (!$date || $date->format('Y-m-d') !== $data['visitDate']) $errors['visitDate'] = 'Choose a valid preferred visit date.';
        elseif ($data['visitDate'] < $first || $data['visitDate'] > $last) $errors['visitDate'] = 'Choose a preferred date from tomorrow up to 12 months ahead.';
        if (!preg_match('/^[1-9][0-9]*$/D', $data['attendees']) || filter_var($data['attendees'], FILTER_VALIDATE_INT) === false) $errors['attendees'] = 'Enter a whole number of at least 1, including yourself.';
        return [$data, $errors];
    }

    // Preferred dates run from tomorrow to twelve months ahead, in Nairobi.
    public static function dateRange(): array {
        $today = new DateTimeImmutable('today', new DateTimeZone('Africa/Nairobi'));
        return [$today->modify('+1 day')->format('Y-m-d'), $today->modify('+12 months')->format('Y-m-d')];
    }
}
