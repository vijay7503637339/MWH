-- Soft-hide applications in admin screens without deleting application history.
-- Existing application rows and linked jobs/attendance/payments remain untouched.

ALTER TABLE applications
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
    ADD INDEX idx_applications_deleted_applied (deleted_at, applied_at);
