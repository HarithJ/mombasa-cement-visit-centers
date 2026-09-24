CREATE TABLE admin_login_attempts (
    address TEXT PRIMARY KEY,
    failures INTEGER NOT NULL,
    started_at INTEGER NOT NULL
);
