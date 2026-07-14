<?php
// login.php - Complete working login page
session_start();

// Include configuration
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: admin/dashboard.php');
    exit();
}

$error = '';
$success = '';

// Check if this is first time setup
$db = Database::getInstance()->getConnection();
$check_users = $db->query("SELECT COUNT(*) as count FROM users");
$user_count = $check_users->fetch_assoc()['count'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $db->prepare("SELECT user_id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Verify password
            if (password_verify($password, $row['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_role'] = $row['role'];
                
                // Update last login
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->bind_param("i", $row['user_id']);
                $updateStmt->execute();
                
                // Redirect to dashboard
                header('Location: admin/dashboard.php');
                exit();
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'Username not found';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .btn-login:hover {
            opacity: 0.9;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #666;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
        }
        
        .setup-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .setup-info h4 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .setup-info ul {
            list-style: none;
            padding-left: 0;
        }
        
        .setup-info li {
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .setup-info code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .back-home {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-home a {
            color: white;
            text-decoration: none;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .back-home a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Enter your credentials to access the system</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success">
                        Registration successful! Please login with your credentials.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['reset'])): ?>
                    <div class="alert alert-success">
                        Password reset successful! Please login with your new password.
                    </div>
                <?php endif; ?>
                
                <?php if ($user_count == 0): ?>
                    <div class="alert alert-info">
                        <strong>First Time Setup:</strong> No users found. Please run install.php to create an admin account.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               required autofocus placeholder="Enter your username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" 
                               required placeholder="Enter your password">
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginButton">
                        <span id="buttonText">Login</span>
                        <span id="loadingSpinner" style="display: none;" class="loading"></span>
                    </button>
                    
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </form>
                
                <?php if ($user_count == 0): ?>
                <div class="setup-info">
                    <h4>🔧 First Time Setup Instructions:</h4>
                    <ul>
                        <li>1. Run <code><a href="install.php">install.php</a></code> to set up the database</li>
                        <li>2. Or create an admin user manually in phpMyAdmin</li>
                        <li>3. Default login after setup: admin / admin123</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="back-home">
            <a href="index.php">← Back to Homepage</a>
        </div>
    </div>
    
    <script>
        // Form validation and loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password');
                return;
            }
            
            // Show loading state
            document.getElementById('buttonText').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'inline-block';
            document.getElementById('loginButton').disabled = true;
        });
        
        // Password show/hide toggle (optional)
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.innerHTML = '👁️';
        toggleBtn.style.position = 'absolute';
        toggleBtn.style.right = '10px';
        toggleBtn.style.top = '35px';
        toggleBtn.style.background = 'none';
        toggleBtn.style.border = 'none';
        toggleBtn.style.cursor = 'pointer';
        
        const passwordGroup = document.querySelector('.form-group:has(#password)');
        if (passwordGroup) {
            passwordGroup.style.position = 'relative';
            passwordGroup.appendChild(toggleBtn);
            
            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                toggleBtn.innerHTML = type === 'password' ? '👁️' : '👁️‍🗨️';
            });
        }
        
        // Auto-focus username field
        window.onload = function() {
            document.getElementById('username').focus();
        };
        
        // Remember username (optional)
        const savedUsername = localStorage.getItem('rememberedUsername');
        if (savedUsername) {
            document.getElementById('username').value = savedUsername;
        }
        
        // Add remember me checkbox (optional)
        const rememberDiv = document.createElement('div');
        rememberDiv.style.marginTop = '10px';
        rememberDiv.innerHTML = `
            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                <input type="checkbox" id="rememberMe"> 
                <span style="font-size: 13px; color: #666;">Remember me</span>
            </label>
        `;
        document.querySelector('.forgot-password').before(rememberDiv);
        
        document.getElementById('loginForm').addEventListener('submit', function() {
            if (document.getElementById('rememberMe').checked) {
                localStorage.setItem('rememberedUsername', document.getElementById('username').value);
            } else {
                localStorage.removeItem('rememberedUsername');
            }
        });
    </script>
</body>
</html>