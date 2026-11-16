-- Bookings now wait for a decision: pending, then confirmed or rejected by a user.
-- Existing bookings count as confirmed, with no history and no messages.
-- The new table is built under a temporary name and renamed, so the references other tables
-- hold to "bookings" stay pointing at it.
CREATE TABLE bookings_with_decisions (
    id INTEGER PRIMARY KEY,
    reference TEXT NOT NULL UNIQUE,
    submission_token TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    destination TEXT NOT NULL,
    visit_date TEXT NOT NULL,
    booked_time TEXT NOT NULL,
    attendees INTEGER NOT NULL CHECK(attendees > 0),
    status TEXT NOT NULL CHECK(status IN ('pending', 'confirmed', 'rejected')),
    created_at TEXT NOT NULL,
    overnight INTEGER NOT NULL DEFAULT 0 CHECK(overnight IN (0, 1)),
    arrival_date TEXT,
    departure_date TEXT,
    overnight_guests INTEGER,
    email TEXT,
    transport_requested INTEGER NOT NULL DEFAULT 0 CHECK(transport_requested IN (0, 1)),
    organization TEXT
);
INSERT INTO bookings_with_decisions(id, reference, submission_token, full_name, phone, destination, visit_date, booked_time, attendees, status, created_at, overnight, arrival_date, departure_date, overnight_guests, email, transport_requested, organization)
SELECT id, reference, submission_token, full_name, phone, destination, visit_date, booked_time, attendees, 'confirmed', created_at, overnight, arrival_date, departure_date, overnight_guests, email, transport_requested, organization FROM bookings;
DROP TABLE bookings;
ALTER TABLE bookings_with_decisions RENAME TO bookings;
CREATE TRIGGER bookings_overnight_insert BEFORE INSERT ON bookings
WHEN (NEW.overnight = 0 AND (NEW.arrival_date IS NOT NULL OR NEW.departure_date IS NOT NULL OR NEW.overnight_guests IS NOT NULL))
OR (NEW.overnight = 1 AND (NEW.destination <> 'galana' OR NEW.arrival_date IS NULL OR NEW.arrival_date <> NEW.visit_date OR NEW.departure_date IS NULL OR NEW.departure_date <= NEW.arrival_date OR NEW.overnight_guests IS NULL OR NEW.overnight_guests < 1 OR NEW.overnight_guests > NEW.attendees))
BEGIN SELECT RAISE(ABORT, 'Invalid overnight details'); END;
CREATE TRIGGER bookings_overnight_update BEFORE UPDATE ON bookings
WHEN (NEW.overnight = 0 AND (NEW.arrival_date IS NOT NULL OR NEW.departure_date IS NOT NULL OR NEW.overnight_guests IS NOT NULL))
OR (NEW.overnight = 1 AND (NEW.destination <> 'galana' OR NEW.arrival_date IS NULL OR NEW.arrival_date <> NEW.visit_date OR NEW.departure_date IS NULL OR NEW.departure_date <= NEW.arrival_date OR NEW.overnight_guests IS NULL OR NEW.overnight_guests < 1 OR NEW.overnight_guests > NEW.attendees))
BEGIN SELECT RAISE(ABORT, 'Invalid overnight details'); END;

-- Every decision and change, newest last. previous_status is what the booking was before it.
CREATE TABLE booking_decisions (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER NOT NULL REFERENCES bookings(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    decision TEXT NOT NULL CHECK(decision IN ('confirmed', 'rejected')),
    reason TEXT,
    previous_status TEXT NOT NULL CHECK(previous_status IN ('pending', 'confirmed', 'rejected')),
    decided_at TEXT NOT NULL,
    CHECK((decision = 'rejected') = (reason IS NOT NULL))
);
CREATE INDEX booking_decisions_booking ON booking_decisions(booking_id, id);

-- Personal Accept/Reject links from the team email. Only the SHA-256 of each token is kept.
-- A link stops working when its user's credential_version moves on (deactivation, email change).
CREATE TABLE decision_links (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER NOT NULL REFERENCES bookings(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    action TEXT NOT NULL CHECK(action IN ('accept', 'reject')),
    token_hash TEXT NOT NULL UNIQUE,
    credential_version INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(booking_id, user_id, action)
);

-- Team emails are now one per user, and a booking can get several decision messages, so
-- one-per-booking only holds for the original kinds. user_id is the staff recipient.
CREATE TABLE booking_emails_with_decisions (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id),
    interest_id INTEGER REFERENCES interest_registrations(id),
    user_id INTEGER REFERENCES users(id),
    kind TEXT NOT NULL DEFAULT 'destination' CHECK(kind IN ('destination', 'visitor', 'feedback', 'interest', 'decision_request', 'decision_visitor', 'decision_team')),
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
    CHECK((kind = 'interest' AND interest_id IS NOT NULL AND booking_id IS NULL) OR (kind <> 'interest' AND booking_id IS NOT NULL AND interest_id IS NULL)),
    CHECK((kind IN ('decision_request', 'decision_team')) = (user_id IS NOT NULL))
);
INSERT INTO booking_emails_with_decisions(id, booking_id, interest_id, kind, due_at, lease_token, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error)
SELECT id, booking_id, interest_id, kind, due_at, lease_token, payload, idempotency_key, status, attempts, first_attempt_at, next_attempt_at, provider_id, last_error FROM booking_emails;
DROP TABLE booking_emails;
ALTER TABLE booking_emails_with_decisions RENAME TO booking_emails;
CREATE UNIQUE INDEX booking_emails_once_per_booking ON booking_emails(booking_id, kind) WHERE kind IN ('destination', 'visitor', 'feedback');
CREATE UNIQUE INDEX booking_emails_once_per_interest ON booking_emails(interest_id, kind) WHERE interest_id IS NOT NULL;
CREATE UNIQUE INDEX booking_emails_request_per_user ON booking_emails(booking_id, user_id) WHERE kind = 'decision_request';

-- Visitor SMS after each decision ('decision') can repeat per booking; the others stay one per recipient.
CREATE TABLE booking_sms_with_decisions (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER NOT NULL REFERENCES bookings(id),
    audience TEXT NOT NULL CHECK(audience IN ('visitor', 'manager', 'decision')),
    recipient TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'sending', 'accepted', 'review')),
    attempts INTEGER NOT NULL DEFAULT 0,
    first_attempt_at INTEGER,
    next_attempt_at INTEGER NOT NULL DEFAULT 0,
    lease_until INTEGER,
    lease_token TEXT,
    profile TEXT,
    provider_id TEXT,
    last_error TEXT
);
INSERT INTO booking_sms_with_decisions SELECT id, booking_id, audience, recipient, message, status, attempts, first_attempt_at, next_attempt_at, lease_until, lease_token, profile, provider_id, last_error FROM booking_sms;
DROP TABLE booking_sms;
ALTER TABLE booking_sms_with_decisions RENAME TO booking_sms;
CREATE INDEX booking_sms_due ON booking_sms(status, next_attempt_at);
CREATE UNIQUE INDEX booking_sms_once_per_recipient ON booking_sms(booking_id, audience, recipient) WHERE audience IN ('visitor', 'manager');
