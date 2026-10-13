-- Persists whether a school has completed its first-login onboarding.
ALTER TABLE school_profiles
    ADD COLUMN setup_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified;
