<?php
// install.php - Run this first to set up the system
require_once 'includes/config.php';

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

// Create connection without database
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "✅ Database created successfully<br>";
} else {
    echo "❌ Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db($dbname);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'pastor', 'secretary', 'viewer') DEFAULT 'viewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Users table created<br>";
} else {
    echo "❌ Error creating users table: " . $conn->error . "<br>";
}

// Create members table
$sql = "CREATE TABLE IF NOT EXISTS members (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    date_of_birth DATE,
    membership_status ENUM('Active', 'Inactive', 'Visitor') DEFAULT 'Visitor',
    membership_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Members table created<br>";
} else {
    echo "❌ Error creating members table: " . $conn->error . "<br>";
}

// Check if admin exists
$check = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($check->num_rows == 0) {
    // Create admin user
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $email = 'admin@church.org';
    $full_name = 'System Administrator';
    $role = 'admin';
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $email, $full_name, $role);
    
    if ($stmt->execute()) {
        echo "✅ Admin user created<br>";
    } else {
        echo "❌ Error creating admin user: " . $conn->error . "<br>";
    }
} else {
    echo "✅ Admin user already exists<br>";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Installation Complete</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 600px; margin: 0 auto; }
        .success { color: green; }
        .info { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>✅ Installation Complete!</h1>
    
    <div class="info">
        <h3>Login Credentials:</h3>
        <p><strong>Username:</strong> admin</p>
        <p><strong>Password:</strong> admin123</p>
    </div>
    
    <p>
        <a href="login.php" style="background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Login</a>
        <a href="index.php" style="background: #666; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">Go to Homepage</a>
    </p>
</body>
</html>