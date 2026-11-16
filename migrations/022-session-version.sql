-- Admin sessions remember session_version and end once it moves on: on password reset, deactivation
-- and email change. Decision links keep checking credential_version, which a password reset leaves alone.
ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1;
