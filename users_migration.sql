-- Run this once if the existing users table is missing columns from the full schema.
ALTER TABLE users
    ADD COLUMN full_name VARCHAR(100) DEFAULT NULL AFTER password,
    ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_login;
