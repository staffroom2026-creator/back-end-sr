-- Track each school opening a teacher profile for teacher dashboard analytics.

CREATE TABLE IF NOT EXISTS teacher_profile_views (
    view_id VARCHAR(255) NOT NULL,
    teacher_user_id VARCHAR(300) NOT NULL,
    school_user_id VARCHAR(300) NOT NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    PRIMARY KEY (view_id),
    KEY idx_teacher_profile_views_teacher (teacher_user_id),
    KEY idx_teacher_profile_views_school (school_user_id),
    KEY idx_teacher_profile_views_date (viewed_at),
    CONSTRAINT fk_teacher_profile_views_teacher FOREIGN KEY (teacher_user_id) REFERENCES initkey (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teacher_profile_views_school FOREIGN KEY (school_user_id) REFERENCES initkey (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;