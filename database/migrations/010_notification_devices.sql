-- FCM device-token registry for candidate push notifications.
CREATE TABLE IF NOT EXISTS notification_devices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 fcm_token TEXT NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 platform VARCHAR(20) NOT NULL DEFAULT 'android',
 app_language VARCHAR(10) NOT NULL DEFAULT 'en',
 last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_notification_devices_user(user_id),
 INDEX idx_notification_devices_seen(last_seen_at),
 CONSTRAINT fk_notification_device_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
