-- Apply once to the dedicated Cloudflare D1 waitlist database, never Laravel's DB.
CREATE TABLE IF NOT EXISTS waitlist_signups (
  id TEXT PRIMARY KEY NOT NULL,
  email TEXT COLLATE NOCASE NOT NULL UNIQUE CHECK (length(email) <= 254),
  name TEXT CHECK (name IS NULL OR length(name) <= 100),
  property_count TEXT CHECK (property_count IS NULL OR property_count IN ('0', '1', '2-5', '6-10', '11+')),
  consent_version TEXT NOT NULL,
  consent_text TEXT NOT NULL,
  created_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL CHECK (expires_at > created_at)
);
CREATE INDEX IF NOT EXISTS idx_waitlist_signups_expires_at ON waitlist_signups (expires_at);

-- Short-lived HMAC identifiers, not raw IPs. Purged on subsequent form requests.
CREATE TABLE IF NOT EXISTS waitlist_rate_limits (
  identifier TEXT PRIMARY KEY NOT NULL,
  attempts INTEGER NOT NULL CHECK (attempts BETWEEN 1 AND 10),
  expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_waitlist_rate_limits_expires_at ON waitlist_rate_limits (expires_at);
