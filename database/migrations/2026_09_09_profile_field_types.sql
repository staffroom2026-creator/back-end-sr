-- Add fields used by the current teacher, school, and administrator forms.

ALTER TABLE school_profiles
    ADD COLUMN country VARCHAR(100) NULL AFTER school_type;

ALTER TABLE initkey
    ADD COLUMN profile_photo VARCHAR(500) NULL AFTER phone;