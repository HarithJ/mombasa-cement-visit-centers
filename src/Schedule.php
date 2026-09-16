<?php
declare(strict_types=1);
final class Schedule {
    public static function load(): array {
        $path = getenv('BOOKING_SCHEDULE') ?: __DIR__.'/../config/destinations.php';
        $resolved = realpath($path);
        $public = realpath(__DIR__.'/../public');
        if (!$resolved || str_starts_with($resolved, $public.'/')) throw new RuntimeException('Schedule configuration must be a private file');
        $schedule = require $resolved;
        foreach (['sahajanand', 'galana', 'feeding'] as $id) {
            $destination = $schedule[$id] ?? null;
            if (!is_array($destination) || !is_string($destination['name'] ?? null) || !is_array($destination['slots'] ?? null) || !$destination['slots']) throw new RuntimeException('Invalid destination configuration');
            foreach ($destination['slots'] as $slot) if (!is_string($slot) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $slot)) throw new RuntimeException('Invalid slot configuration');
        }
        return array_intersect_key($schedule, array_flip(['sahajanand', 'galana', 'feeding']));
    }
}
