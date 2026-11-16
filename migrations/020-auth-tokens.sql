-- Single-use links that sign a user in (login, welcome) or let them set a password (reset).
-- Only a hash of each token is kept. A token stops working once used, once expired, or when
-- the user's credential_version moves on (deactivation, email change, password reset).
CREATE TABLE auth_tokens (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    purpose TEXT NOT NULL CHECK (purpose IN ('login', 'reset', 'welcome')),
    token_hash TEXT NOT NULL UNIQUE,
    credential_version INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    used_at INTEGER
);
-- Request limits count recent links per user and purpose.
CREATE INDEX auth_tokens_recent ON auth_tokens(user_id, purpose, created_at);

-- Account emails share the booking email worker but not its booking-bound table.
CREATE TABLE account_emails (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    kind TEXT NOT NULL CHECK (kind IN ('login', 'reset', 'welcome')),
    due_at INTEGER NOT NULL DEFAULT 0,
    lease_token TEXT,
    payload TEXT NOT NULL,
    idempotency_key TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'review', 'suppressed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    first_attempt_at INTEGER,
    next_attempt_at INTEGER NOT NULL DEFAULT 0,
    provider_id TEXT,
    last_error TEXT
);
