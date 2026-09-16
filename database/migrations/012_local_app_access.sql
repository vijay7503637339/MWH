-- MWH Local shared identity/access layer.
-- Keeps MWH Hospitality users reusable by the MWH Local app without duplicating chefs/staff.

CREATE TABLE IF NOT EXISTS user_app_access (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    app_key VARCHAR(50) NOT NULL,
    role_key VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    source_app VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_app_role (user_id, app_key, role_key),
    INDEX idx_app_role_status (app_key, role_key, status),
    CONSTRAINT fk_user_app_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contractor_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    business_name VARCHAR(190) NULL,
    business_type VARCHAR(100) NULL,
    whatsapp_mobile VARCHAR(20) NULL,
    full_address VARCHAR(500) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    postcode VARCHAR(20) NULL,
    gstin VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_contractor_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_contractor_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing MWH candidate/chef accounts can be used by MWH Local without creating duplicate users.
INSERT IGNORE INTO user_app_access (user_id, app_key, role_key, status, source_app)
SELECT u.id, 'mwh_local', 'staff', 'active', 'mwh_hospitality'
FROM users u
INNER JOIN candidate_profiles cp ON cp.user_id = u.id
WHERE u.deleted_at IS NULL;
