-- Adds explicit application submission fields so applying requires more than a single click.
-- Run once against the target database.

ALTER TABLE applications
    ADD COLUMN cv_url VARCHAR(500) NULL AFTER cover_letter,
    ADD COLUMN additional_info TEXT NULL AFTER cv_url;
