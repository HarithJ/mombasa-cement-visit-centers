CREATE TABLE booking_sms (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER NOT NULL REFERENCES bookings(id),
    audience TEXT NOT NULL CHECK(audience IN ('visitor','manager')),
    recipient TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sending','accepted','review')),
    attempts INTEGER NOT NULL DEFAULT 0,
    first_attempt_at INTEGER,
    next_attempt_at INTEGER NOT NULL DEFAULT 0,
    lease_until INTEGER,
    lease_token TEXT,
    profile TEXT,
    provider_id TEXT,
    last_error TEXT,
    UNIQUE(booking_id,audience,recipient)
);
CREATE INDEX booking_sms_due ON booking_sms(status,next_attempt_at);
CREATE TABLE sms_control (
    id INTEGER PRIMARY KEY CHECK(id = 1),
    paused INTEGER NOT NULL DEFAULT 0 CHECK(paused IN (0,1)),
    reason TEXT
);
INSERT INTO sms_control(id) VALUES (1);
