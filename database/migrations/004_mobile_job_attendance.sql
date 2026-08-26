-- Mobile job lifecycle support.
-- The current mobile check-in flow records the server timestamp first.
-- Photo capture can be added later without blocking check-in.
ALTER TABLE attendance_records
    MODIFY COLUMN check_in_photo_path VARCHAR(500) NULL;
