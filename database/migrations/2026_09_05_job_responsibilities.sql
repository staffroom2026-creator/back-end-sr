-- Store job responsibilities as a JSON array in the jobs table.
-- Run once against the target StaffRoom database.

ALTER TABLE jobs
    ADD COLUMN responsibilities TEXT NULL AFTER requirements;
