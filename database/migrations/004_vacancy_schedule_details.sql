-- Fields required by the mobile vacancy/job detail UI.
ALTER TABLE vacancies
    ADD COLUMN IF NOT EXISTS shift_date DATE NULL AFTER published_at,
    ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER shift_date,
    ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time,
    ADD COLUMN IF NOT EXISTS responsibilities TEXT NULL AFTER requirements,
    ADD COLUMN IF NOT EXISTS dress_code VARCHAR(500) NULL AFTER responsibilities;
