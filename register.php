<?php
// register.php - Complete fixed version
ob_start(); // Start output buffering

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: admin/dashboard.php');
    exit();
}

// Include required files
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'state' => '',
    'zip_code' => '',
    'date_of_birth' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data['first_name'] = trim($_POST['first_name'] ?? '');
    $form_data['last_name'] = trim($_POST['last_name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['address'] = trim($_POST['address'] ?? '');
    $form_data['city'] = trim($_POST['city'] ?? '');
    $form_data['state'] = trim($_POST['state'] ?? '');
    $form_data['zip_code'] = trim($_POST['zip_code'] ?? '');
    $form_data['date_of_birth'] = $_POST['date_of_birth'] ?? '';
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($form_data['first_name'])) {
        $errors[] = "First name is required";
    }
    
    if (empty($form_data['last_name'])) {
        $errors[] = "Last name is required";
    }
    
    if (empty($form_data['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no validation errors, proceed with registration
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if email already exists
            $check_stmt = $db->prepare("SELECT member_id FROM members WHERE email = ?");
            $check_stmt->bind_param("s", $form_data['email']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $errors[] = "Email already registered";
            } else {
                // Start transaction
                $db->begin_transaction();
                
                try {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Generate username from email
                    $username = strtolower(explode('@', $form_data['email'])[0]);
                    
                    // Ensure username is unique
                    $base_username = $username;
                    $counter = 1;
                    while (true) {
                        $check_username = $db->prepare("SELECT user_id FROM users WHERE username = ?");
                        $check_username->bind_param("s", $username);
                        $check_username->execute();
                        $username_result = $check_username->get_result();
                        
                        if ($username_result->num_rows == 0) {
                            break;
                        }
                        $username = $base_username . $counter;
                        $counter++;
                    }
                    
                    // Insert into members table
                    $member_sql = "INSERT INTO members (
                        first_name, last_name, email, phone, address, city, 
                        state, zip_code, date_of_birth, membership_status, membership_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', CURDATE())";
                    
                    $member_stmt = $db->prepare($member_sql);
                    $member_stmt->bind_param(
                        "sssssssss",  // 9 string parameters
                        $form_data['first_name'],
                        $form_data['last_name'],
                        $form_data['email'],
                        $form_data['phone'],
                        $form_data['address'],
                        $form_data['city'],
                        $form_data['state'],
                        $form_data['zip_code'],
                        $form_data['date_of_birth']
                    );
                    
                    if (!$member_stmt->execute()) {
                        throw new Exception("Error creating member record: " . $member_stmt->error);
                    }
                    
                    $member_id = $db->insert_id;
                    
                    // Insert into users table
                    $user_sql = "INSERT INTO users (
                        username, password, email, full_name, role, created_at
                    ) VALUES (?, ?, ?, ?, 'member', NOW())";
                    
                    $full_name = $form_data['first_name'] . ' ' . $form_data['last_name'];
                    $user_stmt = $db->prepare($user_sql);
                    $user_stmt->bind_param(
                        "ssss",  // 4 string parameters
                        $username,
                        $hashed_password,
                        $form_data['email'],
                        $full_name
                    );
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception("Error creating user account: " . $user_stmt->error);
                    }
                    
                    // Commit transaction
                    $db->commit();
                    
                    // Set success message
                    $success = "Registration successful! You can now login with your username: <strong>$username</strong>";
                    
                    // Clear form data
                    $form_data = array_fill_keys(array_keys($form_data), '');
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollback();
                    $errors[] = "Registration failed: " . $e->getMessage();
                    error_log("Registration error: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Database error in registration: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
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
            padding: 40px 20px;
        }
        
        .register-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .register-box {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .register-body {
            padding: 40px 30px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input.error {
            border-color: #f44336;
        }
        
        .form-group .error-message {
            color: #f44336;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .form-group input.error + .error-message {
            display: block;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background 0.3s;
        }
        
        .strength-weak { background: #f44336; width: 33.33%; }
        .strength-medium { background: #ff9800; width: 66.66%; }
        .strength-strong { background: #4caf50; width: 100%; }
        
        .requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .requirements ul {
            list-style: none;
            padding-left: 0;
            margin-top: 5px;
        }
        
        .requirements li {
            margin-bottom: 3px;
        }
        
        .requirements li.met {
            color: #4caf50;
        }
        
        .requirements li:before {
            content: "✗ ";
            color: #f44336;
        }
        
        .requirements li.met:before {
            content: "✓ ";
            color: #4caf50;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .register-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="register-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Create a new account</p>
            </div>
            
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <div style="margin-top: 10px;">
                            <a href="login.php" style="color: #2e7d32; font-weight: bold;">Click here to login</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registrationForm" <?php echo $success ? 'style="display:none;"' : ''; ?>>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($form_data['first_name']); ?>" 
                                   required>
                            <div class="error-message" id="firstNameError">Please enter your first name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($form_data['last_name']); ?>" 
                                   required>
                            <div class="error-message" id="lastNameError">Please enter your last name</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                   required>
                            <div class="error-message" id="emailError">Please enter a valid email address</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                   placeholder="(123) 456-7890">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Street Address</label>
                        <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($form_data['city']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="state">State</label>
                            <select id="state" name="state">
                                <option value="">Select State</option>
                                <option value="AL" <?php echo $form_data['state'] == 'AL' ? 'selected' : ''; ?>>Alabama</option>
                                <option value="AK" <?php echo $form_data['state'] == 'AK' ? 'selected' : ''; ?>>Alaska</option>
                                <option value="AZ" <?php echo $form_data['state'] == 'AZ' ? 'selected' : ''; ?>>Arizona</option>
                                <option value="AR" <?php echo $form_data['state'] == 'AR' ? 'selected' : ''; ?>>Arkansas</option>
                                <option value="CA" <?php echo $form_data['state'] == 'CA' ? 'selected' : ''; ?>>California</option>
                                <option value="CO" <?php echo $form_data['state'] == 'CO' ? 'selected' : ''; ?>>Colorado</option>
                                <option value="CT" <?php echo $form_data['state'] == 'CT' ? 'selected' : ''; ?>>Connecticut</option>
                                <option value="DE" <?php echo $form_data['state'] == 'DE' ? 'selected' : ''; ?>>Delaware</option>
                                <option value="FL" <?php echo $form_data['state'] == 'FL' ? 'selected' : ''; ?>>Florida</option>
                                <option value="GA" <?php echo $form_data['state'] == 'GA' ? 'selected' : ''; ?>>Georgia</option>
                                <option value="GH" <?php echo $form_data['state'] == 'GH' ? 'selected' : ''; ?>>Ghana</option>
                                <option value="HI" <?php echo $form_data['state'] == 'HI' ? 'selected' : ''; ?>>Hawaii</option>
                                <option value="ID" <?php echo $form_data['state'] == 'ID' ? 'selected' : ''; ?>>Idaho</option>
                                <option value="IL" <?php echo $form_data['state'] == 'IL' ? 'selected' : ''; ?>>Illinois</option>
                                <option value="IN" <?php echo $form_data['state'] == 'IN' ? 'selected' : ''; ?>>Indiana</option>
                                <option value="IA" <?php echo $form_data['state'] == 'IA' ? 'selected' : ''; ?>>Iowa</option>
                                <option value="KS" <?php echo $form_data['state'] == 'KS' ? 'selected' : ''; ?>>Kansas</option>
                                <option value="KY" <?php echo $form_data['state'] == 'KY' ? 'selected' : ''; ?>>Kentucky</option>
                                <option value="LA" <?php echo $form_data['state'] == 'LA' ? 'selected' : ''; ?>>Louisiana</option>
                                <option value="ME" <?php echo $form_data['state'] == 'ME' ? 'selected' : ''; ?>>Maine</option>
                                <option value="MD" <?php echo $form_data['state'] == 'MD' ? 'selected' : ''; ?>>Maryland</option>
                                <option value="MA" <?php echo $form_data['state'] == 'MA' ? 'selected' : ''; ?>>Massachusetts</option>
                                <option value="MI" <?php echo $form_data['state'] == 'MI' ? 'selected' : ''; ?>>Michigan</option>
                                <option value="MN" <?php echo $form_data['state'] == 'MN' ? 'selected' : ''; ?>>Minnesota</option>
                                <option value="MS" <?php echo $form_data['state'] == 'MS' ? 'selected' : ''; ?>>Mississippi</option>
                                <option value="MO" <?php echo $form_data['state'] == 'MO' ? 'selected' : ''; ?>>Missouri</option>
                                <option value="MT" <?php echo $form_data['state'] == 'MT' ? 'selected' : ''; ?>>Montana</option>
                                <option value="NE" <?php echo $form_data['state'] == 'NE' ? 'selected' : ''; ?>>Nebraska</option>
                                <option value="NV" <?php echo $form_data['state'] == 'NV' ? 'selected' : ''; ?>>Nevada</option>
                                <option value="NH" <?php echo $form_data['state'] == 'NH' ? 'selected' : ''; ?>>New Hampshire</option>
                                <option value="NJ" <?php echo $form_data['state'] == 'NJ' ? 'selected' : ''; ?>>New Jersey</option>
                                <option value="NM" <?php echo $form_data['state'] == 'NM' ? 'selected' : ''; ?>>New Mexico</option>
                                <option value="NY" <?php echo $form_data['state'] == 'NY' ? 'selected' : ''; ?>>New York</option>
                                <option value="NC" <?php echo $form_data['state'] == 'NC' ? 'selected' : ''; ?>>North Carolina</option>
                                <option value="ND" <?php echo $form_data['state'] == 'ND' ? 'selected' : ''; ?>>North Dakota</option>
                                <option value="OH" <?php echo $form_data['state'] == 'OH' ? 'selected' : ''; ?>>Ohio</option>
                                <option value="OK" <?php echo $form_data['state'] == 'OK' ? 'selected' : ''; ?>>Oklahoma</option>
                                <option value="OR" <?php echo $form_data['state'] == 'OR' ? 'selected' : ''; ?>>Oregon</option>
                                <option value="PA" <?php echo $form_data['state'] == 'PA' ? 'selected' : ''; ?>>Pennsylvania</option>
                                <option value="RI" <?php echo $form_data['state'] == 'RI' ? 'selected' : ''; ?>>Rhode Island</option>
                                <option value="SC" <?php echo $form_data['state'] == 'SC' ? 'selected' : ''; ?>>South Carolina</option>
                                <option value="SD" <?php echo $form_data['state'] == 'SD' ? 'selected' : ''; ?>>South Dakota</option>
                                <option value="TN" <?php echo $form_data['state'] == 'TN' ? 'selected' : ''; ?>>Tennessee</option>
                                <option value="TX" <?php echo $form_data['state'] == 'TX' ? 'selected' : ''; ?>>Texas</option>
                                <option value="UT" <?php echo $form_data['state'] == 'UT' ? 'selected' : ''; ?>>Utah</option>
                                <option value="VT" <?php echo $form_data['state'] == 'VT' ? 'selected' : ''; ?>>Vermont</option>
                                <option value="VA" <?php echo $form_data['state'] == 'VA' ? 'selected' : ''; ?>>Virginia</option>
                                <option value="WA" <?php echo $form_data['state'] == 'WA' ? 'selected' : ''; ?>>Washington</option>
                                <option value="WV" <?php echo $form_data['state'] == 'WV' ? 'selected' : ''; ?>>West Virginia</option>
                                <option value="WI" <?php echo $form_data['state'] == 'WI' ? 'selected' : ''; ?>>Wisconsin</option>
                                <option value="WY" <?php echo $form_data['state'] == 'WY' ? 'selected' : ''; ?>>Wyoming</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="zip_code">ZIP Code</label>
                            <input type="text" id="zip_code" name="zip_code" 
                                   value="<?php echo htmlspecialchars($form_data['zip_code']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrength"></div>
                            </div>
                            <div class="requirements">
                                <div>Password must:</div>
                                <ul id="passwordRequirements">
                                    <li id="lengthReq">Be at least 6 characters</li>
                                    <li id="numberReq">Contain at least one number</li>
                                    <li id="letterReq">Contain at least one letter</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <div class="error-message" id="passwordMatchError">Passwords do not match</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-register" id="registerBtn">Create Account</button>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
                
                <div class="login-link" style="margin-top: 10px;">
                    <a href="index.php">← Back to Homepage</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            let isValid = true;
            
            // Reset error states
            document.querySelectorAll('.form-group input').forEach(input => {
                input.classList.remove('error');
            });
            
            // Validate first name
            if (!firstName) {
                document.getElementById('first_name').classList.add('error');
                document.getElementById('firstNameError').style.display = 'block';
                isValid = false;
            }
            
            // Validate last name
            if (!lastName) {
                document.getElementById('last_name').classList.add('error');
                document.getElementById('lastNameError').style.display = 'block';
                isValid = false;
            }
            
            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                document.getElementById('email').classList.add('error');
                document.getElementById('emailError').style.display = 'block';
                isValid = false;
            }
            
            // Validate password
            if (password.length < 6) {
                document.getElementById('password').classList.add('error');
                isValid = false;
            }
            
            // Validate password match
            if (password !== confirmPassword) {
                document.getElementById('confirm_password').classList.add('error');
                document.getElementById('passwordMatchError').style.display = 'block';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            } else {
                document.getElementById('registerBtn').disabled = true;
                document.getElementById('registerBtn').textContent = 'Creating Account...';
            }
        });
        
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            // Check requirements
            const hasLength = password.length >= 6;
            const hasNumber = /\d/.test(password);
            const hasLetter = /[a-zA-Z]/.test(password);
            
            // Update requirement indicators
            document.getElementById('lengthReq').className = hasLength ? 'met' : '';
            document.getElementById('numberReq').className = hasNumber ? 'met' : '';
            document.getElementById('letterReq').className = hasLetter ? 'met' : '';
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength++;
            if (hasNumber) strength++;
            if (hasLetter) strength++;
            
            // Update strength bar
            strengthBar.className = 'password-strength-bar';
            if (strength <= 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength == 2) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.classList.add('error');
                document.getElementById('passwordMatchError').style.display = 'block';
            } else {
                this.classList.remove('error');
                document.getElementById('passwordMatchError').style.display = 'none';
            }
        });
        
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            
            if (value.length >= 6) {
                value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6);
            } else if (value.length >= 3) {
                value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
            }
            
            e.target.value = value;
        });
        
        // Real-time validation removal
        document.querySelectorAll('.form-group input').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
                if (this.id === 'first_name' || this.id === 'last_name' || this.id === 'email') {
                    const errorId = this.id + 'Error';
                    document.getElementById(errorId).style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>