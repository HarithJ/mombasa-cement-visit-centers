ALTER TABLE bookings ADD COLUMN email TEXT;
ALTER TABLE booking_emails RENAME TO booking_emails_legacy;
CREATE TABLE booking_emails (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER NOT NULL REFERENCES bookings(id),
    kind TEXT NOT NULL DEFAULT 'destination' CHECK(kind IN ('destination', 'visitor')),
    payload TEXT NOT NULL,
    idempotency_key TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'accepted', 'review')),
    attempts INTEGER NOT NULL DEFAULT 0,
    first_attempt_at INTEGER,
    next_attempt_at INTEGER NOT NULL DEFAULT 0,
    provider_id TEXT,
    last_error TEXT,
    UNIQUE(booking_id, kind)
);
INSERT INTO booking_emails(id, booking_id, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error)
SELECT id, booking_id, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error FROM booking_emails_legacy;
DROP TABLE booking_emails_legacy;
