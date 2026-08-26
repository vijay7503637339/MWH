-- Keep production audit_logs compatible with the admin job assignment audit writer.
-- Some existing MWH databases were created before actor_id was present.
ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS actor_id BIGINT UNSIGNED NULL AFTER actor_type;
