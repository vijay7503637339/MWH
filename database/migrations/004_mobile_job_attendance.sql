-- Mobile job lifecycle support.
-- The original attendance schema required a photo, but the current mobile check-in flow
-- records the server timestamp first. Photo capture can be added later without blocking check-in.
ALTER TABLE attendance_records
    MODIFY COLUMN check_in_photo_path VARCHAR(500) NULL;

ALTER TABLE attendance_records
    ADD COLUMN IF NOT EXISTS check_in_latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS check_in_longitude DECIMAL(10,7) NULL;
