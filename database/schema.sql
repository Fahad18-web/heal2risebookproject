-- Heal2Rise Book Database Schema
-- Run this file in phpMyAdmin or MySQL CLI to create the database

CREATE DATABASE IF NOT EXISTS heal2rise_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE heal2rise_db;

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Users Table (Individuals seeking help)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other') DEFAULT 'other',
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    issue_category ENUM('depression', 'hopelessness', 'family_issues', 'marital_issues', 'other') DEFAULT 'other',
    issue_description TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    is_verified TINYINT(1) DEFAULT 0,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    privacy_consent TINYINT(1) DEFAULT 1,
    status ENUM('active', 'inactive', 'recovered') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- NGOs Table
CREATE TABLE IF NOT EXISTS ngos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    organization_name VARCHAR(150) NOT NULL,
    registration_number VARCHAR(50) NOT NULL UNIQUE,
    contact_person VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    website VARCHAR(255),
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    specialization SET('depression', 'hopelessness', 'family_issues', 'marital_issues', 'skill_development', 'rehabilitation') NOT NULL,
    description TEXT,
    logo VARCHAR(255) DEFAULT 'default_ngo.png',
    certificate_doc VARCHAR(255),
    capacity INT DEFAULT 50,
    current_cases INT DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Team Members Table (NGO Staff)
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ngo_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20),
    role ENUM('counselor', 'psychiatrist', 'social_worker', 'coordinator', 'volunteer') NOT NULL,
    qualification VARCHAR(255),
    experience_years INT DEFAULT 0,
    specialization VARCHAR(255),
    is_available TINYINT(1) DEFAULT 1,
    cases_assigned INT DEFAULT 0,
    max_cases INT DEFAULT 10,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cases Table (User-NGO Connection)
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    ngo_id INT,
    team_member_id INT,
    counselor_id INT,
    issue_category ENUM('depression', 'hopelessness', 'family_issues', 'marital_issues', 'other') NOT NULL,
    severity_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    description TEXT,
    admin_notes TEXT,
    status ENUM('pending', 'assigned', 'in_progress', 'counseling', 'rehabilitation', 'skill_development', 'follow_up', 'closed', 'cancelled') DEFAULT 'pending',
    assignment_date TIMESTAMP NULL,
    start_date TIMESTAMP NULL,
    expected_end_date DATE,
    actual_end_date DATE,
    closure_remarks TEXT,
    closed_by ENUM('admin', 'system') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE SET NULL,
    FOREIGN KEY (team_member_id) REFERENCES team_members(id) ON DELETE SET NULL,
    FOREIGN KEY (counselor_id) REFERENCES team_members(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Case Progress Table
CREATE TABLE IF NOT EXISTS case_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES team_members(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('user', 'ngo', 'admin', 'team_member') NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Counseling Sessions Table
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

-- Programs Table (Rehabilitation / Skill Development)
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

-- Donations Table
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

-- Insert default admin
INSERT INTO admins (username, email, password, full_name) VALUES 
('admin', 'admin@heal2rise.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator');
-- Default password: password

-- Create indexes for better performance
CREATE INDEX idx_users_verification ON users(verification_status);
CREATE INDEX idx_users_issue ON users(issue_category);
CREATE INDEX idx_ngos_verification ON ngos(verification_status);
CREATE INDEX idx_ngos_city ON ngos(city);
CREATE INDEX idx_cases_status ON cases(status);
CREATE INDEX idx_cases_user ON cases(user_id);
CREATE INDEX idx_cases_ngo ON cases(ngo_id);
CREATE INDEX idx_team_ngo ON team_members(ngo_id);
CREATE INDEX idx_sessions_case ON counseling_sessions(case_id);
CREATE INDEX idx_sessions_counselor ON counseling_sessions(counselor_id);
CREATE INDEX idx_programs_case ON programs(case_id);
CREATE INDEX idx_donations_ngo ON donations(ngo_id);
CREATE INDEX idx_donations_case ON donations(case_id);
CREATE INDEX idx_donations_user ON donations(donor_user_id);
CREATE INDEX idx_donations_status ON donations(payment_status);
CREATE INDEX idx_donations_paid_at ON donations(paid_at);
