-- Create database
CREATE DATABASE IF NOT EXISTS church_management;
USE church_management;

-- Users table (for system access)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'pastor', 'secretary', 'viewer') DEFAULT 'viewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Members table
CREATE TABLE members (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    email VARCHAR(100),
    phone VARCHAR(20),
    alternate_phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed'),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(50) DEFAULT 'USA',
    occupation VARCHAR(100),
    membership_date DATE,
    baptism_date DATE,
    membership_status ENUM('Active', 'Inactive', 'Visitor', 'Transfer', 'Deleted') DEFAULT 'Active',
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    profile_image VARCHAR(255),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Families table
CREATE TABLE families (
    family_id INT PRIMARY KEY AUTO_INCREMENT,
    family_name VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    home_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Member-Family relationship
CREATE TABLE member_family (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT,
    family_id INT,
    relationship VARCHAR(50),
    is_head BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (family_id) REFERENCES families(family_id)
);

-- Attendance table
CREATE TABLE attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT,
    service_date DATE,
    service_type ENUM('Sunday Service', 'Wednesday Service', 'Bible Study', 'Prayer Meeting', 'Special Event'),
    attended BOOLEAN DEFAULT TRUE,
    check_in_time TIME,
    notes TEXT,
    recorded_by INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- Donations table
CREATE TABLE donations (
    donation_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT,
    donation_date DATE,
    amount DECIMAL(10,2),
    payment_method ENUM('Cash', 'Check', 'Credit Card', 'Debit Card', 'Bank Transfer', 'Online'),
    check_number VARCHAR(50),
    fund_type ENUM('Tithe', 'Offering', 'Building Fund', 'Missions', 'Benevolence', 'Other'),
    notes TEXT,
    receipt_sent BOOLEAN DEFAULT FALSE,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- Groups/Small Groups table
CREATE TABLE groups (
    group_id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL,
    group_type ENUM('Small Group', 'Choir', 'Ministry', 'Committee', 'Class'),
    description TEXT,
    leader_id INT,
    meeting_day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    meeting_time TIME,
    meeting_location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES members(member_id)
);

-- Group members
CREATE TABLE group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT,
    member_id INT,
    joined_date DATE,
    role VARCHAR(50) DEFAULT 'Member',
    FOREIGN KEY (group_id) REFERENCES groups(group_id),
    FOREIGN KEY (member_id) REFERENCES members(member_id)
);

-- Events table
CREATE TABLE events (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(200) NOT NULL,
    event_description TEXT,
    event_date DATE,
    event_time TIME,
    end_date DATE,
    end_time TIME,
    location VARCHAR(255),
    organizer VARCHAR(100),
    max_participants INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Event registration
CREATE TABLE event_registrations (
    registration_id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT,
    member_id INT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attended BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (event_id) REFERENCES events(event_id),
    FOREIGN KEY (member_id) REFERENCES members(member_id)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, email, full_name, role) VALUES 
('admin', '$2y$10$YourHashedPasswordHere', 'admin@church.org', 'System Administrator', 'admin');