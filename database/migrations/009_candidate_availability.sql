-- Persist candidate availability for the mobile Home status switch.
ALTER TABLE candidate_profiles
    ADD COLUMN availability_status VARCHAR(20) NOT NULL DEFAULT 'available' AFTER referral_code;

CREATE INDEX idx_candidate_availability ON candidate_profiles(availability_status);
