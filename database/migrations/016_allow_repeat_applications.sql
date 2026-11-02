-- Allow the same candidate to apply to the same vacancy more than once.
-- Run this migration once on existing databases.

ALTER TABLE applications
    DROP INDEX uq_application_candidate_vacancy;
