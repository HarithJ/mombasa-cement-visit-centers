-- School requests and M-Pesa donations were removed from the site; drop their data.
-- Dependents go first so foreign keys never point at a dropped table.
DROP VIEW IF EXISTS donation_attempt_summary;
DROP TABLE IF EXISTS mpesa_adjustments;
DROP TABLE IF EXISTS mpesa_query_results;
DROP TABLE IF EXISTS mpesa_query_runs;
DROP TABLE IF EXISTS mpesa_queries;
DROP TABLE IF EXISTS mpesa_events;
DROP TABLE IF EXISTS donation_attempts;
DROP TABLE IF EXISTS mpesa_transactions;
DROP TABLE IF EXISTS school_notifications;
DROP TABLE IF EXISTS school_requests;
DROP TABLE IF EXISTS community_throttle;
