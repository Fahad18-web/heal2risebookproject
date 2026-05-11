-- ============================================================
-- Heal2Rise Book - Migration Script (Fixed)
-- ============================================================
-- Safe to run multiple times. All DROP statements are
-- conditional — no errors if already applied.
-- ============================================================

-- ============================================================
-- 1. team_members — drop old foreign key (conditional)
-- ============================================================

-- MySQL does not support DROP FOREIGN KEY IF EXISTS directly,
-- so we use a prepared statement that checks first.
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA  = DATABASE()
      AND TABLE_NAME    = 'team_members'
      AND CONSTRAINT_NAME = 'team_members_ibfk_1'
);
SET @sql = IF(
    @fk_exists > 0,
    'ALTER TABLE team_members DROP FOREIGN KEY team_members_ibfk_1',
    'SELECT "FK team_members_ibfk_1 does not exist — skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2. team_members — drop old columns (each conditional)
-- ============================================================

-- Drop ngo_id
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_members' AND COLUMN_NAME = 'ngo_id');
SET @sql = IF(@col > 0,
    'ALTER TABLE team_members DROP COLUMN ngo_id',
    'SELECT "ngo_id already gone — skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop cases_assigned
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_members' AND COLUMN_NAME = 'cases_assigned');
SET @sql = IF(@col > 0,
    'ALTER TABLE team_members DROP COLUMN cases_assigned',
    'SELECT "cases_assigned already gone — skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- max_cases is KEPT — it is used as per-member workload cap
-- DROP COLUMN max_cases  <-- intentionally removed

-- ============================================================
-- 3. team_members — add new columns (each conditional)
-- ============================================================

-- Modify password to NOT NULL (safe to run even if already NOT NULL)
ALTER TABLE team_members MODIFY COLUMN password VARCHAR(255) NOT NULL;

-- Add category column
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_members' AND COLUMN_NAME = 'category');
SET @sql = IF(@col = 0,
    "ALTER TABLE team_members ADD COLUMN category ENUM('mental_health_counselor','psychiatrist','social_worker','rehabilitation_specialist','skill_development_trainer') NOT NULL",
    'SELECT "category already exists — skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add profile_picture column
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_members' AND COLUMN_NAME = 'profile_picture');
SET @sql = IF(@col = 0,
    "ALTER TABLE team_members ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'default.png'",
    'SELECT "profile_picture already exists — skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add last_login column
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_members' AND COLUMN_NAME = 'last_login');
SET @sql = IF(@col = 0,
    'ALTER TABLE team_members ADD COLUMN last_login TIMESTAMP NULL',
    'SELECT "last_login already exists — skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- NOTE: unique_active_member_category constraint is intentionally
-- NOT added. It was a design flaw — it only allowed one active member
-- per category system-wide. max_cases handles workload limits properly.

-- ============================================================
-- 4. cases — add progress columns (conditional)
-- ============================================================

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cases' AND COLUMN_NAME = 'progress_percentage');
SET @sql = IF(@col = 0,
    'ALTER TABLE cases ADD COLUMN progress_percentage INT DEFAULT 0, ADD COLUMN progress_notes TEXT NULL, ADD CONSTRAINT check_progress_percentage CHECK (progress_percentage >= 0 AND progress_percentage <= 100)',
    'SELECT "progress columns already exist — skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 5. Create satisfaction_requests table
-- ============================================================
CREATE TABLE IF NOT EXISTS satisfaction_requests (
    id                          INT  AUTO_INCREMENT PRIMARY KEY,
    case_id                     INT  NOT NULL,
    requested_by_team_member_id INT  NOT NULL,
    user_response     ENUM('pending','satisfied','not_satisfied') DEFAULT 'pending',
    user_responded_at TIMESTAMP NULL,
    ngo_response      ENUM('pending','satisfied','not_satisfied') DEFAULT 'pending',
    ngo_responded_at  TIMESTAMP NULL,
    closure_request_sent TINYINT(1) DEFAULT 0,
    admin_decision    ENUM('pending','approved','rejected')       DEFAULT 'pending',
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. Create messages table
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id            INT  AUTO_INCREMENT PRIMARY KEY,
    sender_type   ENUM('team_member','user','ngo','admin') NOT NULL,
    sender_id     INT  NOT NULL,
    receiver_type ENUM('team_member','user','ngo','admin') NOT NULL,
    receiver_id   INT  NOT NULL,
    case_id       INT  NULL,
    message       TEXT NOT NULL,
    is_read       TINYINT(1) DEFAULT 0,
    created_at    TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 7. Add indexes (IF NOT EXISTS — MySQL 8.0+)
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_team_category     ON team_members(category);
CREATE INDEX IF NOT EXISTS idx_team_status       ON team_members(status);
CREATE INDEX IF NOT EXISTS idx_cases_progress    ON cases(progress_percentage);
CREATE INDEX IF NOT EXISTS idx_satisfaction_case ON satisfaction_requests(case_id);
CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_type, receiver_id);
CREATE INDEX IF NOT EXISTS idx_messages_case     ON messages(case_id);
CREATE INDEX IF NOT EXISTS idx_messages_unread   ON messages(receiver_type, receiver_id, is_read);