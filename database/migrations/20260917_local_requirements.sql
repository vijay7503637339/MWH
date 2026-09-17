-- MWH Local: contractor-owned staffing requirements
-- Adds the storage needed by /api/local/requirements/create.php.

CREATE TABLE IF NOT EXISTS local_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    job_role_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    openings_count INT UNSIGNED NOT NULL DEFAULT 1,
    work_location VARCHAR(255) NOT NULL,
    work_address VARCHAR(500) NULL,
    shift_date DATE NOT NULL,
    shift_start TIME NULL,
    shift_end TIME NULL,
    minimum_experience VARCHAR(50) NULL,
    payout_amount DECIMAL(12,2) NOT NULL,
    payout_period VARCHAR(30) NOT NULL DEFAULT 'per_shift',
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_local_req_contractor_status(contractor_user_id, status, created_at),
    INDEX idx_local_req_shift_date_status(shift_date, status),
    INDEX idx_local_req_job_role_status(job_role_id, status),
    CONSTRAINT fk_local_req_contractor_user
        FOREIGN KEY (contractor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_req_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_local_req_job_role
        FOREIGN KEY (job_role_id) REFERENCES job_roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
