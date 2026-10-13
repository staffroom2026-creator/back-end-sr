-- Staffroom backend integration migration.
-- Run after reviewing duplicate rows in the target database.

ALTER TABLE teacher_profiles
    ADD COLUMN setup_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_visibility,
    ADD INDEX idx_teacher_profiles_location (location),
    ADD INDEX idx_teacher_profiles_experience (experience_years),
    ADD INDEX idx_teacher_profiles_trcn_status (trcn_status),
    ADD UNIQUE KEY uq_teacher_profiles_user (user_id);

CREATE TABLE IF NOT EXISTS teacher_subjects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_user_id VARCHAR(300) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teacher_subject (teacher_user_id, subject),
    KEY idx_teacher_subject_subject (subject),
    CONSTRAINT fk_teacher_subject_user FOREIGN KEY (teacher_user_id) REFERENCES initkey (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE applications
    ADD COLUMN rejection_message TEXT NULL AFTER status;

CREATE TABLE IF NOT EXISTS saved_teachers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    saved_teacher_id VARCHAR(300) NOT NULL,
    school_user_id VARCHAR(300) NOT NULL,
    teacher_user_id VARCHAR(300) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_saved_teacher (school_user_id, teacher_user_id),
    KEY idx_saved_teachers_school (school_user_id),
    CONSTRAINT fk_saved_teacher_school FOREIGN KEY (school_user_id) REFERENCES initkey (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_teacher_teacher FOREIGN KEY (teacher_user_id) REFERENCES initkey (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_alert_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_user_id VARCHAR(300) NOT NULL,
    subjects TEXT NULL,
    teaching_level VARCHAR(100) NULL,
    location VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_alert_teacher (teacher_user_id),
    CONSTRAINT fk_job_alert_teacher FOREIGN KEY (teacher_user_id) REFERENCES initkey (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications
    ADD COLUMN related_id VARCHAR(300) NULL AFTER type,
    ADD COLUMN related_type VARCHAR(100) NULL AFTER related_id,
    ADD INDEX idx_notifications_user_read_created (user_id, is_read, created_at);

ALTER TABLE jobs
    ADD INDEX idx_jobs_level (teaching_level),
    ADD INDEX idx_jobs_deadline (application_deadline);
