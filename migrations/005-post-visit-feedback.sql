ALTER TABLE booking_emails RENAME TO booking_emails_before_feedback;
CREATE TABLE booking_emails (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER NOT NULL REFERENCES bookings(id),
    kind TEXT NOT NULL DEFAULT 'destination' CHECK(kind IN ('destination', 'visitor', 'feedback')),
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
    UNIQUE(booking_id, kind)
);
INSERT INTO booking_emails(id, booking_id, kind, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error)
SELECT id, booking_id, kind, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error FROM booking_emails_before_feedback;
DROP TABLE booking_emails_before_feedback;

CREATE TABLE feedback_invitations (
    booking_id INTEGER PRIMARY KEY REFERENCES bookings(id),
    token_hash TEXT NOT NULL UNIQUE,
    version TEXT NOT NULL CHECK(version IN ('v1','v2','v3'))
);
CREATE TABLE feedback_responses (
    booking_id INTEGER PRIMARY KEY REFERENCES bookings(id),
    attendance TEXT NOT NULL CHECK(attendance IN ('attended','not_attended')),
    rating INTEGER,
    enjoyment TEXT NOT NULL,
    improvement TEXT NOT NULL,
    comments TEXT NOT NULL,
    submitted_at TEXT NOT NULL,
    CHECK((attendance = 'attended' AND rating BETWEEN 1 AND 5 AND rating IS NOT NULL) OR (attendance = 'not_attended' AND rating IS NULL))
);
