<?php
declare(strict_types=1);
// Which of the four message variants a booking gets: day, day_transport, overnight or overnight_transport.
final class VisitScenario {
    public static function key(array $booking): string {
        return (!empty($booking['overnight']) ? 'overnight' : 'day').(!empty($booking['transport_requested']) ? '_transport' : '');
    }
}
