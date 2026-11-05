-- ============================================================================
-- QuickContractors Claims Tracker System V1.0
-- Authoritative 13-Table DDL Schema Script
-- Database Engine: InnoDB | Character Set: utf8mb4
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if resetting environment (dependency order)
DROP TABLE IF EXISTS claim_guidance_revisions;
DROP TABLE IF EXISTS claim_guidance;
DROP TABLE IF EXISTS claim_history;
DROP TABLE IF EXISTS claim_activity_logs;
DROP TABLE IF EXISTS action_history;
DROP TABLE IF EXISTS follow_ups;
DROP TABLE IF EXISTS service_calls;
DROP TABLE IF EXISTS claim_parties;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS claim_statuses;
DROP TABLE IF EXISTS claim_types;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. users
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    role VARCHAR(50) NOT NULL COMMENT 'Allowed: Claim_Adjuster, Supervisor, Director',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = deactivated',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. stores
CREATE TABLE stores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_code VARCHAR(50) NOT NULL UNIQUE,
    store_name VARCHAR(150) NOT NULL,
    address TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. claim_types
CREATE TABLE claim_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Leak, Damage, Outdoor Damage'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. claim_statuses
CREATE TABLE claim_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Open, In_Progress, Waiting, Resolved, Closed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. claims
CREATE TABLE claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique business claim job identifier',
    store_id INT UNSIGNED NOT NULL,
    claim_type_id INT UNSIGNED NOT NULL,
    status_id INT UNSIGNED NOT NULL DEFAULT 1,
    priority VARCHAR(30) NOT NULL DEFAULT 'Normal' COMMENT 'Low, Normal, High, Urgent, Critical, Escalated',
    assigned_handler_id INT UNSIGNED NOT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_claims_store FOREIGN KEY (store_id) REFERENCES stores(id),
    CONSTRAINT fk_claims_type FOREIGN KEY (claim_type_id) REFERENCES claim_types(id),
    CONSTRAINT fk_claims_status FOREIGN KEY (status_id) REFERENCES claim_statuses(id),
    CONSTRAINT fk_claims_handler FOREIGN KEY (assigned_handler_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. claim_parties
CREATE TABLE claim_parties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id INT UNSIGNED NOT NULL UNIQUE COMMENT 'Strict one-to-one relationship with claims',
    party_type VARCHAR(50) NOT NULL COMMENT 'Third_Party_Contractor, E_Tech',
    contractor_email_address VARCHAR(190) NOT NULL,
    legal_company_name VARCHAR(150) NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_parties_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. service_calls
CREATE TABLE service_calls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id INT UNSIGNED NOT NULL UNIQUE COMMENT 'Zero-or-one service call per claim',
    sc_job_id VARCHAR(100) DEFAULT NULL UNIQUE,
    call_status VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
    scheduled_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sc_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. follow_ups
CREATE TABLE follow_ups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id INT UNSIGNED NOT NULL,
    handler_id INT UNSIGNED NOT NULL,
    due_date DATE NOT NULL COMMENT 'Pure calendar date without timezone shifts',
    status VARCHAR(30) NOT NULL DEFAULT 'Scheduled' COMMENT 'Scheduled, Completed, Cancelled, Superseded',
    notes TEXT DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_followups_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    CONSTRAINT fk_followups_handler FOREIGN KEY (handler_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. action_history
CREATE TABLE action_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_details TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_actionhist_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    CONSTRAINT fk_actionhist_actor FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. claim_activity_logs
CREATE TABLE claim_activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    activity_type VARCHAR(100) NOT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_actor FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. claim_history
CREATE TABLE claim_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    event_description TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_actor FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. claim_guidance
CREATE TABLE claim_guidance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Draft' COMMENT 'Draft, Approved, Inactive',
    author_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_guidance_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. claim_guidance_revisions
CREATE TABLE claim_guidance_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guidance_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    status VARCHAR(30) NOT NULL,
    revision_author_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rev_guidance FOREIGN KEY (guidance_id) REFERENCES claim_guidance(id) ON DELETE CASCADE,
    CONSTRAINT fk_rev_author FOREIGN KEY (revision_author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;2