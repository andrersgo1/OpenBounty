-- OpenBounty 2.0 migration. ADDITIVE ONLY: no data is dropped, renamed or rewritten.
-- Safe to run more than once. Back up your database first, then run it against the existing `openbounty` database.
USE openbounty;

-- 1) Optional difficulty on bounties (NULL for existing rows = "not specified").
SET @ob_sql := (SELECT IF(COUNT(*) = 0,
  "ALTER TABLE bounties ADD COLUMN difficulty ENUM('beginner','intermediate','advanced') NULL AFTER skill_id",
  'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bounties' AND COLUMN_NAME = 'difficulty');
PREPARE ob_stmt FROM @ob_sql; EXECUTE ob_stmt; DEALLOCATE PREPARE ob_stmt;

-- 2) Public portfolio details kept apart from the users table. A missing row means "public, no extra links".
CREATE TABLE IF NOT EXISTS user_profiles (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  headline VARCHAR(160) NULL,
  github_url VARCHAR(255) NULL,
  website_url VARCHAR(255) NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3) Read state for notifications. Notifications themselves are derived from submissions, reviews and payments.
CREATE TABLE IF NOT EXISTS notification_reads (
  user_id BIGINT UNSIGNED NOT NULL,
  nkey VARCHAR(64) NOT NULL,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, nkey),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4) Optional starter skill catalog so profiles, filters and SkillMentor have something to work with.
--    INSERT IGNORE never touches existing rows. Delete this block if you prefer to manage skills yourself.
INSERT IGNORE INTO skills (name, category) VALUES
  ('JavaScript','Language'),('TypeScript','Language'),('Python','Language'),('PHP','Language'),('Go','Language'),('Rust','Language'),
  ('Java','Language'),('C#','Language'),('Ruby','Language'),('SQL','Language'),('Solidity','Language'),('HTML/CSS','Frontend'),
  ('React','Frontend'),('Node.js','Backend'),('Documentation','Other'),('Testing','Other');
