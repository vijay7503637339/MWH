-- Maan World Hospitality (MWH)
-- Initial MySQL 8 schema
-- IMPORTANT: This is a schema/migration file. Do not commit production credentials.
-- Character set: utf8mb4 | Engine: InnoDB

CREATE DATABASE IF NOT EXISTS mwh
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE mwh;

-- =========================
-- ADMINISTRATION
-- =========================

CREATE TABLE admin_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_arp_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_arp_permission FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_admin_role_status (role_id, status),
    CONSTRAINT fk_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB;

-- =========================
-- CANDIDATE / AUTHENTICATION
-- =========================

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(190) NULL UNIQUE,
    account_status VARCHAR(30) NOT NULL DEFAULT 'pending_verification',
    rejection_reason VARCHAR(500) NULL,
    verified_at TIMESTAMP NULL,
    verified_by_admin_id BIGINT UNSIGNED NULL,
    blocked_at TIMESTAMP NULL,
    blocked_by_admin_id BIGINT UNSIGNED NULL,
    block_reason VARCHAR(500) NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_users_status (account_status),
    INDEX idx_users_verified (verified_at),
    CONSTRAINT fk_user_verified_admin FOREIGN KEY (verified_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_user_blocked_admin FOREIGN KEY (blocked_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE otp_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mobile VARCHAR(20) NOT NULL,
    purpose VARCHAR(30) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
    verified_at TIMESTAMP NULL,
    requested_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_mobile_purpose_created (mobile, purpose, created_at),
    INDEX idx_otp_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE candidate_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    gender VARCHAR(30) NULL,
    full_address VARCHAR(500) NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'India',
    postcode VARCHAR(20) NULL,
    referral_code VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_candidate_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_candidate_city (city),
    INDEX idx_candidate_referral (referral_code)
) ENGINE=InnoDB;

CREATE TABLE professional_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    experience_years DECIMAL(4,1) NOT NULL DEFAULT 0,
    previous_company_name VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_professional_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================
-- MASTER DATA
-- =========================

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE job_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uq_job_role_category_name (category_id, name),
    INDEX idx_job_roles_category_active (category_id, is_active),
    CONSTRAINT fk_job_role_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE job_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    category_id BIGINT UNSIGNED NOT NULL,
    job_role_id BIGINT UNSIGNED NOT NULL,
    max_travel_distance_km DECIMAL(6,2) NULL,
    preferred_working_hours DECIMAL(4,1) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_preference_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_preference_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_preference_role FOREIGN KEY (job_role_id) REFERENCES job_roles(id),
    INDEX idx_preferences_role_distance (job_role_id, max_travel_distance_km)
) ENGINE=InnoDB;

-- =========================
-- DOCUMENTS & PAYMENT PROFILE
-- =========================

CREATE TABLE candidate_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    original_file_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(500) NULL,
    reviewed_at TIMESTAMP NULL,
    reviewed_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_documents_user_type (user_id, document_type),
    INDEX idx_documents_review_status (review_status),
    CONSTRAINT fk_document_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_document_admin FOREIGN KEY (reviewed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE candidate_payment_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    payment_type VARCHAR(20) NOT NULL,
    account_holder_name VARCHAR(150) NULL,
    bank_name VARCHAR(150) NULL,
    account_number_encrypted TEXT NULL,
    ifsc_code VARCHAR(20) NULL,
    branch_name VARCHAR(150) NULL,
    upi_id VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================
-- HOTELS & VACANCIES
-- =========================

CREATE TABLE hotels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    address_line VARCHAR(500) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    postcode VARCHAR(20) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'India',
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    contact_name VARCHAR(150) NULL,
    contact_phone VARCHAR(20) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_hotels_city_status (city, status),
    CONSTRAINT fk_hotel_created_admin FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE hotel_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hotel_id BIGINT UNSIGNED NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    original_file_name VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hotel_images_hotel (hotel_id, sort_order),
    CONSTRAINT fk_hotel_image_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vacancies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hotel_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    job_role_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    openings_count INT UNSIGNED NOT NULL DEFAULT 1,
    payout_amount DECIMAL(12,2) NOT NULL,
    payout_period VARCHAR(30) NOT NULL DEFAULT 'per_job',
    working_hours DECIMAL(4,1) NULL,
    requirements TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    closing_at TIMESTAMP NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_vacancy_hotel_status (hotel_id, status),
    INDEX idx_vacancy_role_status (job_role_id, status),
    CONSTRAINT fk_vacancy_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id),
    CONSTRAINT fk_vacancy_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_vacancy_role FOREIGN KEY (job_role_id) REFERENCES job_roles(id),
    CONSTRAINT fk_vacancy_admin FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- APPLICATIONS & JOBS
-- =========================

CREATE TABLE applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vacancy_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'applied',
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by_admin_id BIGINT UNSIGNED NULL,
    rejection_reason VARCHAR(500) NULL,
    notes TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_applications_user_status (user_id, status),
    INDEX idx_applications_vacancy_status (vacancy_id, status),
    CONSTRAINT fk_application_vacancy FOREIGN KEY (vacancy_id) REFERENCES vacancies(id),
    CONSTRAINT fk_application_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_application_admin FOREIGN KEY (reviewed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL UNIQUE,
    vacancy_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    hotel_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'assigned',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    start_date DATE NULL,
    end_date DATE NULL,
    payout_amount DECIMAL(12,2) NOT NULL,
    assigned_by_admin_id BIGINT UNSIGNED NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jobs_user_status (user_id, status),
    INDEX idx_jobs_hotel_status (hotel_id, status),
    CONSTRAINT fk_job_application FOREIGN KEY (application_id) REFERENCES applications(id),
    CONSTRAINT fk_job_vacancy FOREIGN KEY (vacancy_id) REFERENCES vacancies(id),
    CONSTRAINT fk_job_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_job_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id),
    CONSTRAINT fk_job_admin FOREIGN KEY (assigned_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- ATTENDANCE / CHECK-IN
-- =========================

CREATE TABLE attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    check_in_at TIMESTAMP NOT NULL,
    check_in_photo_path VARCHAR(500) NOT NULL,
    check_in_capture_source VARCHAR(20) NOT NULL DEFAULT 'camera',
    check_in_latitude DECIMAL(10,7) NULL,
    check_in_longitude DECIMAL(10,7) NULL,
    check_in_notes VARCHAR(500) NULL,
    check_out_at TIMESTAMP NULL,
    check_out_notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_attendance_job_date (job_id, check_in_at),
    INDEX idx_attendance_user_date (user_id, check_in_at),
    CONSTRAINT fk_attendance_job FOREIGN KEY (job_id) REFERENCES jobs(id),
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =========================
-- PAYMENTS
-- =========================

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    transaction_reference VARCHAR(190) NULL,
    notes VARCHAR(500) NULL,
    entered_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payments_user_date (user_id, payment_date),
    INDEX idx_payments_job (job_id),
    UNIQUE KEY uq_payment_reference (transaction_reference),
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_payment_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_admin FOREIGN KEY (entered_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- NOTIFICATIONS & SUPPORT
-- =========================

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    admin_user_id BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    data_json JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, read_at, created_at),
    INDEX idx_notifications_admin_read (admin_user_id, read_at, created_at),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE support_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    support_phone VARCHAR(30) NULL,
    support_whatsapp VARCHAR(30) NULL,
    support_email VARCHAR(190) NULL,
    support_hours VARCHAR(190) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- AUDIT / SECURITY
-- =========================

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type VARCHAR(20) NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_entity (entity_type, entity_id, created_at),
    INDEX idx_audit_actor (actor_type, actor_id, created_at)
) ENGINE=InnoDB;

-- =========================
-- BASE ADMIN PERMISSIONS / ROLES
-- No admin password is seeded here.
-- Create the first admin through a secure deployment/bootstrap process.
-- =========================

INSERT INTO admin_roles (name, description) VALUES
('super_admin', 'Full system access'),
('operations_admin', 'Candidate, hotel, vacancy, application and job operations'),
('finance_admin', 'Payment and transaction management')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO admin_permissions (permission_key, description) VALUES
('dashboard.view', 'View dashboard'),
('candidates.view', 'View candidates'),
('candidates.verify', 'Verify or reject candidates'),
('candidates.block', 'Block or unblock candidates'),
('documents.review', 'Review candidate documents'),
('hotels.manage', 'Create, edit, deactivate hotels'),
('vacancies.manage', 'Create, edit, publish and close vacancies'),
('applications.manage', 'Review and update applications'),
('jobs.manage', 'Assign and manage jobs'),
('attendance.view', 'View candidate attendance'),
('payments.manage', 'Enter and manage payments'),
('support.manage', 'Manage support contact settings'),
('admins.manage', 'Manage admin users and roles'),
('audit.view', 'View audit logs')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r CROSS JOIN admin_permissions p
WHERE r.name = 'super_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r
JOIN admin_permissions p ON p.permission_key IN (
    'dashboard.view','candidates.view','candidates.verify','candidates.block',
    'documents.review','hotels.manage','vacancies.manage','applications.manage',
    'jobs.manage','attendance.view','support.manage'
)
WHERE r.name = 'operations_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r
JOIN admin_permissions p ON p.permission_key IN (
    'dashboard.view','candidates.view','payments.manage','attendance.view'
)
WHERE r.name = 'finance_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
