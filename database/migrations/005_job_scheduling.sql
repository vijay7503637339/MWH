-- Admin job assignment/scheduling support.
ALTER TABLE jobs
    ADD COLUMN start_time TIME NULL AFTER start_date,
    ADD COLUMN end_time TIME NULL AFTER end_date;

CREATE INDEX idx_jobs_schedule ON jobs(start_date, start_time, status);
