-- Internal Admin Dashboard foundation.
-- Apply after the existing Staffroom schema migrations.

ALTER TABLE initkey MODIFY role ENUM('teacher','school','admin','police') NOT NULL;
ALTER TABLE jobs MODIFY status VARCHAR(40) NOT NULL DEFAULT 'published';
ALTER TABLE jobs ADD COLUMN reviewed_at DATETIME NULL, ADD COLUMN reviewed_by VARCHAR(300) NULL, ADD COLUMN internal_review_note TEXT NULL;
ALTER TABLE school_profiles ADD COLUMN admin_status ENUM('active','suspended') NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS internal_admin_profiles (
    user_id VARCHAR(300) NOT NULL,
    admin_role ENUM('Super Admin','Operations Admin','Verification Admin','Support Admin') NOT NULL DEFAULT 'Support Admin',
    permissions JSON NULL,
    status ENUM('active','suspended','pending','revoked') NOT NULL DEFAULT 'active',
    created_by VARCHAR(300) NULL,
    last_active_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_internal_admin_role (admin_role),
    KEY idx_internal_admin_status (status),
    CONSTRAINT fk_internal_admin_user FOREIGN KEY (user_id) REFERENCES initkey(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_internal_admin_creator FOREIGN KEY (created_by) REFERENCES initkey(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO internal_admin_profiles (user_id, admin_role, status)
SELECT user_id, 'Super Admin', 'active' FROM initkey WHERE role = 'police';

CREATE TABLE IF NOT EXISTS internal_admin_permissions (
    permission_id VARCHAR(80) NOT NULL,
    user_id VARCHAR(300) NOT NULL,
    module_name VARCHAR(50) NOT NULL,
    permissions JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (permission_id), UNIQUE KEY uq_internal_admin_permission (user_id,module_name),
    CONSTRAINT fk_internal_permission_user FOREIGN KEY (user_id) REFERENCES initkey(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_admin_invitations (
    invitation_id VARCHAR(80) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    admin_role VARCHAR(50) NOT NULL,
    permissions JSON NULL,
    token_hash CHAR(64) NOT NULL,
    status ENUM('pending','accepted','expired','revoked') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    invited_by VARCHAR(300) NOT NULL,
    accepted_by VARCHAR(300) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (invitation_id), KEY idx_admin_invite_email_status (email,status), KEY idx_admin_invite_expiry (expires_at),
    CONSTRAINT fk_admin_invite_creator FOREIGN KEY (invited_by) REFERENCES initkey(user_id),
    CONSTRAINT fk_admin_invite_acceptor FOREIGN KEY (accepted_by) REFERENCES initkey(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_job_review_history (
    review_id VARCHAR(80) NOT NULL,
    job_id VARCHAR(300) NOT NULL,
    reviewer_id VARCHAR(300) NOT NULL,
    previous_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    internal_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id), KEY idx_job_review_job (job_id), KEY idx_job_review_created (created_at),
    CONSTRAINT fk_job_review_reviewer FOREIGN KEY (reviewer_id) REFERENCES initkey(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_school_status_history (
    history_id VARCHAR(80) NOT NULL,
    school_id VARCHAR(300) NOT NULL,
    actor_id VARCHAR(300) NOT NULL,
    previous_status VARCHAR(30) NOT NULL,
    new_status VARCHAR(30) NOT NULL,
    reason TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id), KEY idx_school_history_school (school_id),
    CONSTRAINT fk_school_history_actor FOREIGN KEY (actor_id) REFERENCES initkey(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_teacher_status_history (
    history_id VARCHAR(80) NOT NULL,
    teacher_id VARCHAR(300) NOT NULL,
    actor_id VARCHAR(300) NOT NULL,
    previous_status VARCHAR(30) NOT NULL,
    new_status VARCHAR(30) NOT NULL,
    reason TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id), KEY idx_teacher_history_teacher (teacher_id),
    CONSTRAINT fk_teacher_history_actor FOREIGN KEY (actor_id) REFERENCES initkey(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_reports (
    report_id VARCHAR(80) NOT NULL,
    reporter_id VARCHAR(300) NULL,
    reported_entity_type VARCHAR(50) NOT NULL,
    reported_entity_id VARCHAR(300) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status ENUM('new','assigned','investigating','resolved','dismissed') NOT NULL DEFAULT 'new',
    assigned_to VARCHAR(300) NULL,
    resolution_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id), KEY idx_report_status (status), KEY idx_report_created (created_at),
    CONSTRAINT fk_report_reporter FOREIGN KEY (reporter_id) REFERENCES initkey(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_report_assignee FOREIGN KEY (assigned_to) REFERENCES initkey(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_notifications (
    notification_id VARCHAR(80) NOT NULL,
    user_id VARCHAR(300) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(80) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    related_id VARCHAR(300) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id), KEY idx_internal_notification_user (user_id,is_read,created_at),
    CONSTRAINT fk_internal_notification_user FOREIGN KEY (user_id) REFERENCES initkey(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_platform_settings (
    setting_key VARCHAR(150) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(30) NOT NULL DEFAULT 'string',
    description VARCHAR(500) NULL,
    updated_by VARCHAR(300) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_setting_updater FOREIGN KEY (updated_by) REFERENCES initkey(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_audit_logs (
    audit_id VARCHAR(80) NOT NULL,
    actor_user_id VARCHAR(300) NOT NULL,
    actor_role VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(300) NOT NULL,
    previous_value JSON NULL,
    new_value JSON NULL,
    reason TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id), KEY idx_audit_actor (actor_user_id), KEY idx_audit_entity (entity_type,entity_id), KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES initkey(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
