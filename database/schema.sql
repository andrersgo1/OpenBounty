-- OpenBounty + SkillMentor initial MySQL schema (MySQL 8+)
-- Review and apply to a NEW database only. Do not rerun over existing production data.
CREATE DATABASE IF NOT EXISTS openbounty CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE openbounty;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('member','reviewer','admin') NOT NULL DEFAULT 'member',
 wallet_address VARCHAR(80) NULL,
 bio TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE skills (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 category VARCHAR(100) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE user_skills (
 user_id BIGINT UNSIGNED NOT NULL, skill_id BIGINT UNSIGNED NOT NULL,
 proficiency ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
 PRIMARY KEY(user_id,skill_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bounties (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 creator_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL, description TEXT NOT NULL,
 acceptance_criteria TEXT NOT NULL, skill_id BIGINT UNSIGNED NULL,
 reward_xlm DECIMAL(18,7) NOT NULL,
 status ENUM('draft','open','in_review','approved','payment_pending','paid','closed') NOT NULL DEFAULT 'draft',
 deadline DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(creator_id) REFERENCES users(id), FOREIGN KEY(skill_id) REFERENCES skills(id),
 CHECK (reward_xlm > 0)
) ENGINE=InnoDB;
CREATE TABLE submissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bounty_id BIGINT UNSIGNED NOT NULL, contributor_id BIGINT UNSIGNED NOT NULL,
 submission_url VARCHAR(2048) NOT NULL, notes TEXT NULL,
 status ENUM('submitted','in_review','changes_requested','rejected','approved') NOT NULL DEFAULT 'submitted',
 reviewer_id BIGINT UNSIGNED NULL, review_notes TEXT NULL, reviewed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(bounty_id) REFERENCES bounties(id), FOREIGN KEY(contributor_id) REFERENCES users(id), FOREIGN KEY(reviewer_id) REFERENCES users(id),
 INDEX idx_submission_bounty_status(bounty_id,status)
) ENGINE=InnoDB;
CREATE TABLE payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bounty_id BIGINT UNSIGNED NOT NULL UNIQUE,
 submission_id BIGINT UNSIGNED NOT NULL, payer_id BIGINT UNSIGNED NOT NULL,
 recipient_wallet VARCHAR(80) NOT NULL, amount_xlm DECIMAL(18,7) NOT NULL,
 network ENUM('stellar_testnet') NOT NULL DEFAULT 'stellar_testnet',
 status ENUM('pending','prepared','submitted','confirmed','verified','failed') NOT NULL DEFAULT 'pending',
 paiflow_deployment_id VARCHAR(100) NULL, transaction_hash VARCHAR(100) NULL,
 verification_json JSON NULL, failure_reason TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(bounty_id) REFERENCES bounties(id), FOREIGN KEY(submission_id) REFERENCES submissions(id), FOREIGN KEY(payer_id) REFERENCES users(id),
 UNIQUE KEY uq_payment_tx_hash(transaction_hash)
) ENGINE=InnoDB;

CREATE TABLE mentors (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 headline VARCHAR(180) NOT NULL, about TEXT NOT NULL, hourly_rate DECIMAL(10,2) NULL,
 currency CHAR(3) NOT NULL DEFAULT 'PHP', status ENUM('pending','active','paused','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE mentor_skills (
 mentor_id BIGINT UNSIGNED NOT NULL, skill_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(mentor_id,skill_id), FOREIGN KEY(mentor_id) REFERENCES mentors(id) ON DELETE CASCADE,
 FOREIGN KEY(skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE mentor_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, mentor_id BIGINT UNSIGNED NOT NULL, learner_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL, learning_goal TEXT NOT NULL, scheduled_at DATETIME NULL,
 status ENUM('requested','accepted','completed','cancelled','rejected') NOT NULL DEFAULT 'requested',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(mentor_id) REFERENCES mentors(id), FOREIGN KEY(learner_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE learning_projects (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, session_id BIGINT UNSIGNED NULL, learner_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL, description TEXT NOT NULL, evidence_url VARCHAR(2048) NULL,
 mentor_feedback TEXT NULL, status ENUM('planned','in_progress','submitted','reviewed','completed') NOT NULL DEFAULT 'planned',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(session_id) REFERENCES mentor_sessions(id), FOREIGN KEY(learner_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_user_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id BIGINT UNSIGNED NULL,
 details JSON NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
