-- Track recent successful logins for teacher and school dashboards.

CREATE TABLE IF NOT EXISTS user_login_activity (
    id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    device_name VARCHAR(100) NULL DEFAULT 'web',
    location VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_login_activity_user (user_id),
    KEY idx_user_login_activity_login_at (login_at),
    CONSTRAINT fk_user_login_activity_user FOREIGN KEY (user_id) REFERENCES initkey (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;