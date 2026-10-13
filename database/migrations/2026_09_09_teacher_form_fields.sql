-- Add fields used by the current teacher profile and education forms.

ALTER TABLE teacher_profiles
    ADD COLUMN teaching_mode VARCHAR(100) NULL AFTER preferred_employment_type,
    ADD COLUMN city VARCHAR(150) NULL AFTER location,
    ADD COLUMN state VARCHAR(150) NULL AFTER city;

ALTER TABLE teacher_education
    ADD COLUMN other_degree VARCHAR(255) NULL AFTER degree,
    ADD COLUMN field_of_study VARCHAR(255) NULL AFTER institution,
    ADD COLUMN class_of_degree VARCHAR(150) NULL AFTER status;