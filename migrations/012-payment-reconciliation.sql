CREATE TABLE mpesa_queries (
 id INTEGER PRIMARY KEY, environment TEXT NOT NULL, merchant TEXT NOT NULL, receipt TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','waiting','complete','review')),
 attempts INTEGER NOT NULL DEFAULT 0, next_at INTEGER NOT NULL DEFAULT 0, last_error TEXT,
 UNIQUE(environment,merchant,receipt)
);
CREATE TABLE mpesa_query_runs (
 token TEXT PRIMARY KEY, query_id INTEGER NOT NULL REFERENCES mpesa_queries(id),
 conversation TEXT, originator TEXT, created_at TEXT NOT NULL
);
CREATE TABLE mpesa_query_results (
 id INTEGER PRIMARY KEY, token TEXT NOT NULL REFERENCES mpesa_query_runs(token),
 fingerprint TEXT NOT NULL, payload TEXT NOT NULL, trusted INTEGER NOT NULL CHECK(trusted IN (0,1)),
 processed INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL,
 UNIQUE(token,fingerprint,trusted)
);
CREATE TABLE mpesa_adjustments (
 id INTEGER PRIMARY KEY, transaction_id INTEGER NOT NULL UNIQUE REFERENCES mpesa_transactions(id),
 amount INTEGER NOT NULL CHECK(amount<0), actor TEXT NOT NULL, evidence TEXT NOT NULL, created_at TEXT NOT NULL
);
