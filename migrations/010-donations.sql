CREATE TABLE mpesa_transactions (
 id INTEGER PRIMARY KEY,
 environment TEXT NOT NULL, merchant TEXT NOT NULL, receipt TEXT NOT NULL,
 amount INTEGER NOT NULL CHECK(amount>0), currency TEXT NOT NULL DEFAULT 'KES',
 account_reference TEXT NOT NULL, payer TEXT, payer_name TEXT,
 transacted_at TEXT NOT NULL, received_at TEXT NOT NULL,
 state TEXT NOT NULL CHECK(state IN ('verified','unmatched','unverified','conflict','reversed')),
 UNIQUE(environment,merchant,receipt)
);
CREATE INDEX mpesa_transaction_dates ON mpesa_transactions(transacted_at DESC,id DESC);
CREATE TABLE mpesa_events (
 id INTEGER PRIMARY KEY, transaction_id INTEGER NOT NULL REFERENCES mpesa_transactions(id),
 fingerprint TEXT NOT NULL, source TEXT NOT NULL, evidence TEXT NOT NULL DEFAULT '',
 payload TEXT NOT NULL, created_at TEXT NOT NULL,
 UNIQUE(transaction_id,fingerprint,source,evidence)
);
