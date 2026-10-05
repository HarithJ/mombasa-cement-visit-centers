CREATE TABLE school_requests (
 id INTEGER PRIMARY KEY,
 reference TEXT NOT NULL UNIQUE,
 submission_token TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL, relationship TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL,
 school TEXT NOT NULL, county TEXT NOT NULL, locality TEXT NOT NULL,
 support TEXT NOT NULL CHECK(support IN ('classroom','school_wall','both')),
 description TEXT NOT NULL, created_at TEXT NOT NULL
);
CREATE INDEX school_requests_created ON school_requests(created_at DESC, id DESC);
CREATE TABLE school_notifications (
 id INTEGER PRIMARY KEY,
 request_id INTEGER NOT NULL UNIQUE REFERENCES school_requests(id),
 idempotency_key TEXT NOT NULL UNIQUE,
 status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','accepted','review')),
 payload TEXT, first_attempt_at INTEGER, next_attempt_at INTEGER NOT NULL DEFAULT 0,
 attempts INTEGER NOT NULL DEFAULT 0, lease_token TEXT, provider_id TEXT, last_error TEXT
);
CREATE TABLE community_throttle (
 bucket TEXT PRIMARY KEY, started_at INTEGER NOT NULL, attempts INTEGER NOT NULL
);
