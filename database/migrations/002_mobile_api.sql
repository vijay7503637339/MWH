-- Mobile API authentication and support/referral persistence.
CREATE TABLE IF NOT EXISTS api_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at TIMESTAMP NOT NULL,
 revoked_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_api_tokens_user(user_id,expires_at),
 CONSTRAINT fk_api_token_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(190) NOT NULL,
 message TEXT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'open',
 admin_reply TEXT NULL,
 replied_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_support_user_status(user_id,status,created_at),
 CONSTRAINT fk_support_ticket_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referrals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 referrer_user_id BIGINT UNSIGNED NOT NULL,
 referred_mobile VARCHAR(20) NULL,
 referred_user_id BIGINT UNSIGNED NULL,
 referral_code VARCHAR(50) NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'shared',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_referral_referrer(referrer_user_id,created_at),
 CONSTRAINT fk_referral_referrer FOREIGN KEY(referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_referral_user FOREIGN KEY(referred_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
