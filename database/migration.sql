-- Heal2Rise Book - Migration Script
-- Run this ONLY if you already have the database and need to add new tables/columns
-- For fresh installs, use schema.sql instead

USE heal2rise_db;

-- Add counselor_id and closed_by to cases table
ALTER TABLE cases 
    ADD COLUMN counselor_id INT NULL AFTER team_member_id,
    ADD COLUMN closed_by ENUM('admin', 'system') NULL AFTER closure_remarks,
    ADD FOREIGN KEY (counselor_id) REFERENCES team_members(id) ON DELETE SET NULL;

-- Update cases status ENUM to include rehabilitation and skill_development
ALTER TABLE cases 
    MODIFY COLUMN status ENUM('pending', 'assigned', 'in_progress', 'counseling', 'rehabilitation', 'skill_development', 'follow_up', 'closed', 'cancelled') DEFAULT 'pending';

-- Create Counseling Sessions Table
CREATE TABLE IF NOT EXISTS counseling_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    counselor_id INT NOT NULL,
    session_date DATE NOT NULL,
    session_time TIME,
    duration_minutes INT DEFAULT 60,
    session_type ENUM('initial_assessment', 'regular', 'follow_up', 'emergency', 'group') DEFAULT 'regular',
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    recommendations TEXT,
    mood_rating INT DEFAULT NULL COMMENT '1-10 scale',
    next_session_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES team_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create Programs Table (Rehabilitation / Skill Development)
CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    program_type ENUM('rehabilitation', 'skill_development') NOT NULL,
    program_name VARCHAR(255) NOT NULL,
    description TEXT,
    recommended_by INT,
    start_date DATE,
    end_date DATE,
    status ENUM('recommended', 'enrolled', 'in_progress', 'completed', 'dropped') DEFAULT 'recommended',
    progress_notes TEXT,
    completion_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (recommended_by) REFERENCES team_members(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Add indexes
CREATE INDEX idx_sessions_case ON counseling_sessions(case_id);
CREATE INDEX idx_sessions_counselor ON counseling_sessions(counselor_id);
CREATE INDEX idx_programs_case ON programs(case_id);

-- Create Donations Table
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_number VARCHAR(30) NOT NULL UNIQUE,
    ngo_id INT NOT NULL,
    case_id INT NULL,
    donor_user_id INT NULL,
    donor_name VARCHAR(120) NOT NULL,
    donor_email VARCHAR(120) NOT NULL,
    donor_phone VARCHAR(20),
    amount DECIMAL(10,2) NOT NULL,
    purpose VARCHAR(255),
    payment_method ENUM('card', 'bank_transfer', 'mobile_wallet', 'cash', 'other') DEFAULT 'other',
    transaction_reference VARCHAR(120),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    notes TEXT,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (donor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_donations_ngo ON donations(ngo_id);
CREATE INDEX idx_donations_case ON donations(case_id);
CREATE INDEX idx_donations_user ON donations(donor_user_id);
CREATE INDEX idx_donations_status ON donations(payment_status);
CREATE INDEX idx_donations_paid_at ON donations(paid_at);
