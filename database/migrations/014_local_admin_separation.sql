-- MWH Local admin separation
-- Uses the existing user_app_access source-of-truth to separate Local users
-- from the legacy MWH Hospitality candidate management screens.

CREATE INDEX idx_user_app_access_user_app_role
    ON user_app_access (user_id, app_key, role_key, status);
