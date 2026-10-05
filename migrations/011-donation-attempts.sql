CREATE TABLE donation_attempts (
 id INTEGER PRIMARY KEY, reference TEXT NOT NULL UNIQUE, submission_token TEXT NOT NULL UNIQUE,
 environment TEXT NOT NULL, merchant TEXT NOT NULL, merchant_name TEXT NOT NULL,
 payment_number TEXT NOT NULL, merchant_type TEXT NOT NULL,
 amount INTEGER NOT NULL CHECK(amount>0),
 state TEXT NOT NULL DEFAULT 'qr_pending' CHECK(state IN ('qr_pending','awaiting','qr_failed')),
 qr TEXT, provider_id TEXT, lease_until INTEGER NOT NULL DEFAULT 0, lease_token TEXT,
 created_at TEXT NOT NULL
);
CREATE INDEX donation_attempt_created ON donation_attempts(created_at DESC,id DESC);
CREATE INDEX mpesa_account_lookup ON mpesa_transactions(environment,merchant,account_reference);
CREATE VIEW donation_attempt_summary AS
 SELECT a.*, COUNT(t.id) AS receipt_count, GROUP_CONCAT(t.receipt, ', ') AS receipts,
 CASE WHEN COUNT(t.id)=1 AND MAX(t.state)='verified' AND MAX(t.amount)=a.amount THEN 'received'
      WHEN COUNT(t.id)>0 THEN 'review'
      ELSE a.state END AS payment_state
 FROM donation_attempts a LEFT JOIN mpesa_transactions t ON t.environment=a.environment AND t.merchant=a.merchant AND t.account_reference=a.reference
 GROUP BY a.id;
