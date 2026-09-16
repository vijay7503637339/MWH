-- External Google Play account deletion request workflow.
CREATE TABLE IF NOT EXISTS account_deletion_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL,
    email VARCHAR(190) NULL,
    reason VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    processed_by_admin_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY idx_adr_status_requested (status, requested_at),
    KEY idx_adr_user (user_id),
    KEY idx_adr_mobile (mobile),
    KEY idx_adr_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
