-- Interest in places not yet open for booking, kept apart from bookings.
CREATE TABLE interest_registrations (
    id INTEGER PRIMARY KEY,
    submission_token TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    organization TEXT,
    phone TEXT NOT NULL,
    email TEXT,
    destination TEXT NOT NULL,
    preferred_date TEXT NOT NULL,
    attendees INTEGER NOT NULL CHECK(attendees > 0),
    created_at TEXT NOT NULL
);
CREATE INDEX interest_registrations_created ON interest_registrations(created_at);

-- A queued email now belongs to either a booking or an interest registration.
ALTER TABLE booking_emails RENAME TO booking_emails_before_interest;
CREATE TABLE booking_emails (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id),
    interest_id INTEGER REFERENCES interest_registrations(id),
    kind TEXT NOT NULL DEFAULT 'destination' CHECK(kind IN ('destination', 'visitor', 'feedback', 'interest')),
    due_at INTEGER NOT NULL DEFAULT 0,
    lease_token TEXT,
    payload TEXT NOT NULL,
    idempotency_key TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'accepted', 'review', 'suppressed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    first_attempt_at INTEGER,
    next_attempt_at INTEGER NOT NULL DEFAULT 0,
    provider_id TEXT,
    last_error TEXT,
    UNIQUE(booking_id, kind),
    UNIQUE(interest_id, kind),
    CHECK((kind = 'interest' AND interest_id IS NOT NULL AND booking_id IS NULL) OR (kind <> 'interest' AND booking_id IS NOT NULL AND interest_id IS NULL))
);
INSERT INTO booking_emails(id, booking_id, kind, due_at, lease_token, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error)
SELECT id, booking_id, kind, due_at, lease_token, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error FROM booking_emails_before_interest;
DROP TABLE booking_emails_before_interest;
