-- JobNest schema (single source of truth). MySQL 8 / MariaDB 10.6+.
-- Run once as a MySQL admin account: mysql -u root -p < database/schema.sql
-- Safe to re-run: existing tables and data are kept.

CREATE DATABASE IF NOT EXISTS jobnest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jobnest;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('applicant', 'company', 'admin') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- cv_path only stores a random file name; the folder is fixed in config (storage/private/cv).
CREATE TABLE IF NOT EXISTS applicant_profiles (
    user_id INT UNSIGNED PRIMARY KEY,
    phone VARCHAR(20) NULL,
    address VARCHAR(500) NULL,
    education VARCHAR(1000) NULL,
    skills VARCHAR(1000) NULL,
    cv_path VARCHAR(64) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    website VARCHAR(255) NULL,
    verification_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    verified_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_companies_user (user_id),
    KEY idx_companies_status (verification_status),
    CONSTRAINT fk_companies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_companies_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- deleted_at = soft delete by the company owner or by an admin (moderation).
CREATE TABLE IF NOT EXISTS job_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    salary_min INT UNSIGNED NULL,
    salary_max INT UNSIGNED NULL,
    deadline DATE NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    deleted_at DATETIME NULL,
    deleted_by_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_jobs_public (status, deleted_at, deadline),
    KEY idx_jobs_company (company_id),
    CONSTRAINT fk_jobs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- cv_path is a private copy of the applicant's CV at the time of applying.
CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_post_id INT UNSIGNED NOT NULL,
    applicant_id INT UNSIGNED NOT NULL,
    cv_path VARCHAR(64) NOT NULL,
    cover_letter TEXT NULL,
    status ENUM('submitted', 'reviewed', 'accepted', 'rejected') NOT NULL DEFAULT 'submitted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_applications_job_applicant (job_post_id, applicant_id),
    KEY idx_applications_applicant (applicant_id),
    CONSTRAINT fk_applications_job FOREIGN KEY (job_post_id) REFERENCES job_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_applications_applicant FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_jobs (
    user_id INT UNSIGNED NOT NULL,
    job_post_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, job_post_id),
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_job FOREIGN KEY (job_post_id) REFERENCES job_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Never store passwords, tokens, or CV content here.
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    target_type VARCHAR(32) NULL,
    target_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logs_created (created_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Login throttling. The email is stored as a SHA-256 hash, never in plain text.
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email_hash CHAR(64) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip (ip_address, attempted_at),
    KEY idx_attempts_email (email_hash, ip_address, attempted_at)
) ENGINE=InnoDB;
