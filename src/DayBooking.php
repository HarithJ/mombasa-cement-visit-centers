<?php
declare(strict_types=1);
final class DayBooking {
    public static function validate(array $input, array $destinations): array {
        $data = [];
        foreach (['fullName', 'email', 'phone', 'location', 'visitDate', 'timeSlot', 'attendees', 'transportRequested', 'overnight', 'arrivalDate', 'departureDate', 'overnightGuests'] as $key) $data[$key] = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $errors = [];
        if ($data['fullName'] === '' || mb_strlen($data['fullName']) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $data['fullName'])) $errors['fullName'] = 'Enter your full name (up to 120 characters).';
        if ($data['email'] !== '' && (strlen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) $errors['email'] = 'Enter a valid email address (up to 254 characters).';
        $phone = $data['phone'];
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($phone) > 40 || !preg_match('/^\+?[0-9][0-9 ()-]*$/D', $phone) || strlen($digits) < 7 || strlen($digits) > 15) $errors['phone'] = 'Enter a phone number with 7–15 digits, optionally using +, spaces, parentheses or hyphens.';
        elseif (preg_match('/^0[17]\d{8}$/D', $digits)) $data['phone'] = '+254'.substr($digits, 1);
        elseif (str_starts_with($phone, '+') || preg_match('/^254\d{9}$/D', $digits)) $data['phone'] = '+'.$digits;
        else $data['phone'] = $digits;
        if (!isset($destinations[$data['location']])) $errors['location'] = 'Choose one of the three destinations.';
        if (!in_array($data['timeSlot'], $destinations[$data['location']]['slots'] ?? [], true)) $errors['timeSlot'] = 'Choose a time offered for this destination.';
        $zone = new DateTimeZone('Africa/Nairobi');
        $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $data['visitDate']) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['visitDate'], $zone) : false;
        if (!$date || $date->format('Y-m-d') !== $data['visitDate']) $errors['visitDate'] = 'Choose a valid visit date.';
        elseif ($data['visitDate'] < (new DateTimeImmutable('now', $zone))->format('Y-m-d')) $errors['visitDate'] = 'Choose today or a future date.';
        if (!preg_match('/^[1-9][0-9]*$/D', $data['attendees']) || filter_var($data['attendees'], FILTER_VALIDATE_INT) === false) $errors['attendees'] = 'Enter a whole number of at least 1, including yourself.';
        if (isset($input['transportRequested']) && (!is_string($input['transportRequested']) || !in_array($input['transportRequested'], ['', 'on'], true))) $errors['transportRequested'] = 'Choose whether you would like transport arranged.';
        $active = $data['location'] === 'galana' && $data['overnight'] !== '';
        if ($active) {
            if (!in_array($data['overnight'], ['on', '1'], true)) $errors['overnight'] = 'Choose whether you would like to stay overnight.';
            if ($data['arrivalDate'] !== $data['visitDate'] || !$date) $errors['arrivalDate'] = 'Arrival must match your valid visit date.';
            $departure = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $data['departureDate']) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['departureDate'], $zone) : false;
            if (!$departure || $departure->format('Y-m-d') !== $data['departureDate']) $errors['departureDate'] = 'Choose a valid departure date.';
            elseif ($data['departureDate'] <= $data['arrivalDate']) $errors['departureDate'] = 'Departure must be after arrival.';
            if (!preg_match('/^[1-9][0-9]*$/D', $data['overnightGuests']) || filter_var($data['overnightGuests'], FILTER_VALIDATE_INT) === false) $errors['overnightGuests'] = 'Enter a whole number of staying guests, at least 1.';
            elseif (!isset($errors['attendees']) && (int)$data['overnightGuests'] > (int)$data['attendees']) $errors['overnightGuests'] = 'Staying guests cannot exceed total attendees.';
            $data['overnight'] = 'on';
        } else {
            $data['overnight'] = ''; $data['arrivalDate'] = null; $data['departureDate'] = null; $data['overnightGuests'] = null;
        }
        return [$data, $errors];
    }
}
