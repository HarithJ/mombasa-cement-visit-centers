<?php
declare(strict_types=1);
require __DIR__.'/../src/BookingStore.php';
new BookingStore(getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite');
echo "Database migrations applied. Existing bookings preserved.\n";
