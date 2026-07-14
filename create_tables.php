<?php
// create_tables.php - Run this to create all missing tables
require_once 'includes/config.php';
require_once 'includes/db_connection.php';

$db = Database::getInstance()->getConnection();

// Array of SQL statements to create tables
$tables = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        full_name VARCHAR(100),
        role ENUM('admin', 'pastor', 'secretary', 'viewer') DEFAULT 'viewer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    )",
    
    // Members table
    "CREATE TABLE IF NOT EXISTS members (
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
    )",
    
    // Events table (this is the one you're missing)
    "CREATE TABLE IF NOT EXISTS events (
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
    )",
    
    // Event registrations table
    "CREATE TABLE IF NOT EXISTS event_registrations (
        registration_id INT PRIMARY KEY AUTO_INCREMENT,
        event_id INT,
        member_id INT,
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        attended BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (event_id) REFERENCES events(event_id),
        FOREIGN KEY (member_id) REFERENCES members(member_id)
    )",
    
    // Attendance table
    "CREATE TABLE IF NOT EXISTS attendance (
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
    )",
    
    // Donations table
    "CREATE TABLE IF NOT EXISTS donations (
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
    )",
    
    // Families table
    "CREATE TABLE IF NOT EXISTS families (
        family_id INT PRIMARY KEY AUTO_INCREMENT,
        family_name VARCHAR(100),
        address TEXT,
        city VARCHAR(50),
        state VARCHAR(50),
        zip_code VARCHAR(20),
        home_phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Member-Family relationship
    "CREATE TABLE IF NOT EXISTS member_family (
        id INT PRIMARY KEY AUTO_INCREMENT,
        member_id INT,
        family_id INT,
        relationship VARCHAR(50),
        is_head BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (member_id) REFERENCES members(member_id),
        FOREIGN KEY (family_id) REFERENCES families(family_id)
    )",
    
    // Groups table
    "CREATE TABLE IF NOT EXISTS groups (
        group_id INT PRIMARY KEY AUTO_INCREMENT,
        group_name VARCHAR(100) NOT NULL,
        group_type ENUM('Small Group', 'Choir', 'Ministry', 'Committee', 'Class', 'Prayer Team', 'Youth', 'Children', 'Men', 'Women', 'Seniors') NOT NULL,
        description TEXT,
        leader_id INT,
        co_leader_id INT,
        meeting_day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        meeting_time TIME,
        meeting_location VARCHAR(255),
        meeting_frequency ENUM('Weekly', 'Bi-weekly', 'Monthly', 'Quarterly') DEFAULT 'Weekly',
        max_capacity INT DEFAULT 0,
        current_members INT DEFAULT 0,
        status ENUM('Active', 'Inactive', 'Forming') DEFAULT 'Active',
        visibility ENUM('Public', 'Private', 'By Invitation') DEFAULT 'Public',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (leader_id) REFERENCES members(member_id),
        FOREIGN KEY (co_leader_id) REFERENCES members(member_id),
        FOREIGN KEY (created_by) REFERENCES users(user_id)
    )",
    
    // Group members table
    "CREATE TABLE IF NOT EXISTS group_members (
        group_member_id INT PRIMARY KEY AUTO_INCREMENT,
        group_id INT NOT NULL,
        member_id INT NOT NULL,
        role ENUM('Member', 'Leader', 'Co-Leader', 'Assistant', 'Secretary') DEFAULT 'Member',
        joined_date DATE,
        status ENUM('Active', 'Inactive', 'Pending') DEFAULT 'Active',
        notes TEXT,
        added_by INT,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(user_id),
        UNIQUE KEY unique_group_member (group_id, member_id)
    )",
    
    // Group meetings table
    "CREATE TABLE IF NOT EXISTS group_meetings (
        meeting_id INT PRIMARY KEY AUTO_INCREMENT,
        group_id INT NOT NULL,
        meeting_date DATE,
        topic VARCHAR(255),
        notes TEXT,
        attendance_count INT DEFAULT 0,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(user_id)
    )",
    
    // Group meeting attendance
    "CREATE TABLE IF NOT EXISTS group_meeting_attendance (
        attendance_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT NOT NULL,
        group_member_id INT NOT NULL,
        attended BOOLEAN DEFAULT TRUE,
        check_in_time TIME,
        notes TEXT,
        FOREIGN KEY (meeting_id) REFERENCES group_meetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (group_member_id) REFERENCES group_members(group_member_id) ON DELETE CASCADE
    )"
];

echo "<h2>Creating Database Tables</h2>";

$success_count = 0;
$error_count = 0;

foreach ($tables as $sql) {
    try {
        if ($db->query($sql)) {
            echo "<p style='color: green;'>✓ Table created successfully</p>";
            $success_count++;
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ " . $e->getMessage() . "</p>";
        $error_count++;
    }
}

// Insert sample event data
$sample_events = [
    "INSERT INTO events (event_name, event_description, event_date, event_time, location, organizer, max_participants) VALUES 
    ('Sunday Worship Service', 'Weekly worship service', DATE_ADD(CURDATE(), INTERVAL (7 - WEEKDAY(CURDATE())) DAY), '10:00:00', 'Main Sanctuary', 'Pastor John', 500)",
    
    "INSERT INTO events (event_name, event_description, event_date, event_time, location, organizer, max_participants) VALUES 
    ('Bible Study', 'Weekly Bible study group', DATE_ADD(CURDATE(), INTERVAL (9 - WEEKDAY(CURDATE())) DAY), '19:00:00', 'Fellowship Hall', 'Pastor Sarah', 100)",
    
    "INSERT INTO events (event_name, event_description, event_date, event_time, location, organizer, max_participants) VALUES 
    ('Youth Night', 'Monthly youth gathering', DATE_ADD(CURDATE(), INTERVAL (14 - WEEKDAY(CURDATE())) DAY), '18:00:00', 'Youth Center', 'Youth Pastor Mike', 150)"
];

echo "<h3>Adding Sample Events</h3>";

foreach ($sample_events as $sql) {
    try {
        $db->query($sql);
    } catch (Exception $e) {
        // Events might already exist
    }
}

echo "<h3>Summary</h3>";
echo "<p>✅ Tables created: $success_count</p>";
echo "<p>⚠ Errors: $error_count (may be normal if tables already exist)</p>";

// Check if admin user exists
$check_admin = $db->query("SELECT * FROM users WHERE username = 'admin'");
if ($check_admin->num_rows == 0) {
    // Create admin user
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $email = 'admin@church.org';
    $full_name = 'System Administrator';
    $role = 'admin';
    
    $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $email, $full_name, $role);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ Admin user created (admin/admin123)</p>";
    }
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li><a href='index.php'>Go to Homepage</a></li>";
echo "<li><a href='login.php'>Go to Login</a> (username: admin, password: admin123)</li>";
echo "</ul>";
?>