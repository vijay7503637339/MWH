-- Keep production audit_logs compatible with the admin job assignment audit writer.
-- Some existing MWH databases were created before actor_type was present.
ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS actor_type VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER id;
