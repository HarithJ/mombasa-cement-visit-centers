-- Staff accounts. Users are never deleted, only deactivated, so past decisions keep a name.
-- credential_version goes up whenever a user's sessions and unused links must stop working
-- (deactivation, email change, and later a password reset).
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    phone TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'team')),
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
    password_hash TEXT NOT NULL,
    credential_version INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
