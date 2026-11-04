-- Galana Farm is now the only destination. Bookings and interest registrations kept from the
-- other places stay in their tables, untouched. Messages still waiting to go out for them are
-- parked rather than deleted, so nothing is sent about places no longer offered.
UPDATE booking_emails SET status = 'suppressed', last_error = 'Destination retired'
WHERE status = 'pending' AND (
    kind = 'interest'
    OR booking_id IN (SELECT id FROM bookings WHERE destination <> 'galana')
);
UPDATE booking_sms SET status = 'review', last_error = 'destination_retired'
WHERE status = 'pending' AND booking_id IN (SELECT id FROM bookings WHERE destination <> 'galana');
