-- Unified teacher profile storage. Run after the existing Staffroom schema migrations.

ALTER TABLE teacher_profiles
    ADD COLUMN role_title VARCHAR(255) NULL AFTER user_id,
    ADD COLUMN preferred_employment_type VARCHAR(100) NULL AFTER experience_years,
    ADD COLUMN teaching_levels TEXT NULL AFTER skills,
    ADD COLUMN preferred_location VARCHAR(255) NULL AFTER location,
    MODIFY COLUMN availability VARCHAR(50) NULL;

CREATE TABLE IF NOT EXISTS teacher_education (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    education_id VARCHAR(300) NOT NULL,
    user_id VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    degree VARCHAR(255) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    start_year SMALLINT UNSIGNED NOT NULL,
    end_year SMALLINT UNSIGNED NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Completed',
    record_hash CHAR(64) GENERATED ALWAYS AS (SHA2(CONCAT_WS('|', user_id, degree, institution, start_year), 256)) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teacher_education_id (education_id),
    UNIQUE KEY uq_teacher_education_record (user_id, record_hash),
    KEY idx_teacher_education_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_experience (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    experience_id VARCHAR(300) NOT NULL,
    user_id VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    role VARCHAR(255) NOT NULL,
    school VARCHAR(255) NOT NULL,
    location VARCHAR(255) NULL,
    start_date CHAR(7) NOT NULL,
    end_date CHAR(7) NULL,
    description TEXT NULL,
    record_hash CHAR(64) GENERATED ALWAYS AS (SHA2(CONCAT_WS('|', user_id, role, school, start_date), 256)) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teacher_experience_id (experience_id),
    UNIQUE KEY uq_teacher_experience_record (user_id, record_hash),
    KEY idx_teacher_experience_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
