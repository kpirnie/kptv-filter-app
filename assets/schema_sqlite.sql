PRAGMA foreign_keys = ON;

-- SQLite schema converted from MySQL/MariaDB

CREATE TABLE IF NOT EXISTS kptv_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  u_role INTEGER NOT NULL DEFAULT 0,
  u_active INTEGER NOT NULL DEFAULT 0,
  u_name TEXT NOT NULL,
  u_pass TEXT NOT NULL,
  u_hash TEXT NOT NULL,
  u_email TEXT NOT NULL,
  u_lname TEXT,
  u_fname TEXT,
  u_created TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  u_updated TEXT,
  last_login TEXT,
  login_attempts INTEGER DEFAULT 0,
  locked_until TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_uname ON kptv_users(u_name);
CREATE UNIQUE INDEX IF NOT EXISTS idx_uemail ON kptv_users(u_email);
CREATE INDEX IF NOT EXISTS idx_uactive ON kptv_users(u_active);

CREATE TABLE IF NOT EXISTS kptv_stream_providers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  u_id INTEGER NOT NULL,
  sp_should_filter INTEGER NOT NULL DEFAULT 1,
  sp_priority INTEGER NOT NULL DEFAULT 99,
  sp_name TEXT NOT NULL,
  sp_cnx_limit INTEGER NOT NULL DEFAULT 1,
  sp_sub_length INTEGER NOT NULL DEFAULT 0,
  sp_contact TEXT,
  sp_cost REAL NOT NULL DEFAULT 0,
  sp_expires TEXT,
  sp_type INTEGER NOT NULL DEFAULT 0,
  sp_domain TEXT NOT NULL,
  sp_username TEXT,
  sp_password TEXT,
  sp_stream_type INTEGER NOT NULL DEFAULT 0,
  sp_refresh_period INTEGER NOT NULL DEFAULT 1,
  sp_last_synced TEXT,
  sp_added TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  sp_updated TEXT
);

CREATE INDEX IF NOT EXISTS idx_stream_providers_uid ON kptv_stream_providers(u_id);
CREATE INDEX IF NOT EXISTS idx_spname ON kptv_stream_providers(sp_name);

CREATE TABLE IF NOT EXISTS kptv_streams (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  u_id INTEGER NOT NULL,
  p_id INTEGER NOT NULL DEFAULT 0,
  s_type_id INTEGER NOT NULL DEFAULT 0,
  s_active INTEGER NOT NULL DEFAULT 0,
  s_channel TEXT NOT NULL DEFAULT '0',
  s_name TEXT NOT NULL,
  s_orig_name TEXT NOT NULL,
  s_stream_uri TEXT NOT NULL DEFAULT '',
  s_tvg_id TEXT,
  s_tvg_group TEXT,
  s_tvg_logo TEXT,
  s_extras TEXT,
  s_created TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  s_updated TEXT
);

CREATE INDEX IF NOT EXISTS idx_streams_uid ON kptv_streams(u_id);
CREATE INDEX IF NOT EXISTS idx_streams_pid ON kptv_streams(p_id);
CREATE INDEX IF NOT EXISTS idx_streams_stypeid ON kptv_streams(s_type_id);
CREATE INDEX IF NOT EXISTS idx_streams_sactive ON kptv_streams(s_active);
CREATE INDEX IF NOT EXISTS idx_streams_schannel ON kptv_streams(s_channel);
CREATE INDEX IF NOT EXISTS idx_streams_sactive_stvgid ON kptv_streams(s_active, s_tvg_id);
CREATE INDEX IF NOT EXISTS idx_streams_sname_supdated ON kptv_streams(s_name, s_updated);

-- Optional full text search (requires SQLite FTS5). Comment out if your SQLite build lacks FTS5.
-- This provides roughly similar capability to MySQL FULLTEXT on s_name and s_orig_name.
CREATE VIRTUAL TABLE IF NOT EXISTS kptv_streams_fts
USING fts5(
  s_name,
  s_orig_name,
  content='kptv_streams',
  content_rowid='id'
);

-- Keep FTS table in sync with base table
CREATE TRIGGER IF NOT EXISTS kptv_streams_ai AFTER INSERT ON kptv_streams BEGIN
  INSERT INTO kptv_streams_fts(rowid, s_name, s_orig_name) VALUES (new.id, new.s_name, new.s_orig_name);
END;
CREATE TRIGGER IF NOT EXISTS kptv_streams_ad AFTER DELETE ON kptv_streams BEGIN
  INSERT INTO kptv_streams_fts(kptv_streams_fts, rowid, s_name, s_orig_name) VALUES('delete', old.id, old.s_name, old.s_orig_name);
END;
CREATE TRIGGER IF NOT EXISTS kptv_streams_au AFTER UPDATE ON kptv_streams BEGIN
  INSERT INTO kptv_streams_fts(kptv_streams_fts, rowid, s_name, s_orig_name) VALUES('delete', old.id, old.s_name, old.s_orig_name);
  INSERT INTO kptv_streams_fts(rowid, s_name, s_orig_name) VALUES (new.id, new.s_name, new.s_orig_name);
END;

CREATE TABLE IF NOT EXISTS kptv_stream_filters (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  u_id INTEGER NOT NULL,
  sf_active INTEGER NOT NULL DEFAULT 1,
  sf_type_id INTEGER NOT NULL DEFAULT 0,
  sf_filter TEXT NOT NULL,
  sf_created TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  sf_updated TEXT
);

CREATE INDEX IF NOT EXISTS idx_filters_uid_sfactive_sftypeid ON kptv_stream_filters(u_id, sf_active, sf_type_id);

CREATE TABLE IF NOT EXISTS kptv_stream_missing (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  u_id INTEGER NOT NULL,
  p_id INTEGER NOT NULL,
  stream_id INTEGER NOT NULL DEFAULT 0,
  other_id INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE INDEX IF NOT EXISTS idx_missing_uid ON kptv_stream_missing(u_id);
CREATE INDEX IF NOT EXISTS idx_missing_pid ON kptv_stream_missing(p_id);
CREATE INDEX IF NOT EXISTS idx_missing_streamid ON kptv_stream_missing(stream_id);
CREATE INDEX IF NOT EXISTS idx_missing_otherid ON kptv_stream_missing(other_id);

CREATE TABLE IF NOT EXISTS kptv_stream_temp (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  u_id INTEGER NOT NULL,
  p_id INTEGER NOT NULL,
  s_type_id INTEGER NOT NULL DEFAULT 0,
  s_orig_name TEXT NOT NULL,
  s_stream_uri TEXT NOT NULL DEFAULT '',
  s_tvg_id TEXT,
  s_tvg_logo TEXT,
  s_extras TEXT,
  s_group TEXT,
  s_orig_name_lower TEXT GENERATED ALWAYS AS (lower(s_orig_name)) VIRTUAL
);

-- ===== "ON UPDATE current_timestamp()" replacements =====
-- SQLite doesn't support column-level ON UPDATE; use triggers to maintain *_updated timestamps.

CREATE TRIGGER IF NOT EXISTS kptv_users_set_updated
AFTER UPDATE ON kptv_users
FOR EACH ROW
WHEN new.u_updated IS old.u_updated
BEGIN
  UPDATE kptv_users SET u_updated = CURRENT_TIMESTAMP WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS kptv_streams_set_updated
AFTER UPDATE ON kptv_streams
FOR EACH ROW
WHEN new.s_updated IS old.s_updated
BEGIN
  UPDATE kptv_streams SET s_updated = CURRENT_TIMESTAMP WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS kptv_stream_providers_set_updated
AFTER UPDATE ON kptv_stream_providers
FOR EACH ROW
WHEN new.sp_updated IS old.sp_updated
BEGIN
  UPDATE kptv_stream_providers SET sp_updated = CURRENT_TIMESTAMP WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS kptv_stream_filters_set_updated
AFTER UPDATE ON kptv_stream_filters
FOR EACH ROW
WHEN new.sf_updated IS old.sf_updated
BEGIN
  UPDATE kptv_stream_filters SET sf_updated = CURRENT_TIMESTAMP WHERE id = new.id;
END;
