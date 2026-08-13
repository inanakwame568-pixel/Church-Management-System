<?php
// register.php - Complete unified version
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Start session
startSession();

// Redirect if already logged in
redirectIfLoggedIn();

// Set page title
$page_title = "Register";

$error = '';
$success = '';
$form_data = [
    'username' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $form_data['username'] = sanitize($_POST['username'] ?? '');
    $form_data['email'] = sanitize($_POST['email'] ?? '');
    $form_data['first_name'] = sanitize($_POST['first_name'] ?? '');
    $form_data['last_name'] = sanitize($_POST['last_name'] ?? '');
    $form_data['phone'] = sanitize($_POST['phone'] ?? '');
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($form_data['username'])) {
        $errors[] = "Username is required!";
    } elseif (!validateUsername($form_data['username'])) {
        $errors[] = "Username must be 3-20 characters and can only contain letters, numbers, and underscores!";
    }
    
    if (empty($form_data['first_name'])) {
        $errors[] = "First name is required!";
    }
    
    if (empty($form_data['last_name'])) {
        $errors[] = "Last name is required!";
    }
    
    if (empty($form_data['email'])) {
        $errors[] = "Email is required!";
    } elseif (!validateEmail($form_data['email'])) {
        $errors[] = "Invalid email format!";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required!";
    } elseif (!validatePassword($password)) {
        $errors[] = "Password must be at least 6 characters with at least one letter and one number!";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    // If no validation errors, proceed with registration
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $form_data['username'], $form_data['email']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    if ($row['username'] === $form_data['username']) {
                        $errors[] = "Username already exists!";
                    }
                    if ($row['email'] === $form_data['email']) {
                        $errors[] = "Email already exists!";
                    }
                }
            } else {
                // Begin transaction
                $db->begin_transaction();
                
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert into users table
                $query = "INSERT INTO users (username, password, email, role, created_at) 
                         VALUES (?, ?, ?, 'viewer', NOW())";
                $stmt = $db->prepare($query);
                $stmt->bind_param("sss", 
                    $form_data['username'],
                    $password_hash,
                    $form_data['email']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create user account");
                }
                
                $user_id = $db->insert_id;
                
                // Insert into members table
                $query = "INSERT INTO members (first_name, last_name, phone, email, membership_date, membership_status) 
                         VALUES (?, ?, ?, ?, CURDATE(), 'Active')";
                $stmt = $db->prepare($query);
                $stmt->bind_param("ssss", 
                    $form_data['first_name'],
                    $form_data['last_name'],
                    $form_data['phone'],
                    $form_data['email']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create member profile");
                }
                
                $db->commit();
                
                // Set success message
                setFlashMessage("Registration successful! You can now login.", "success");
                
                header("Location: login.php?registered=1");
                exit();
            }
            
        } catch (Exception $e) {
            if (isset($db) && $db->connect_error === null) {
                $db->rollback();
            }
            $errors[] = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Include header (this will start the HTML document)
include 'header.php';
?>

<!-- Registration Form Container -->
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="custom-card">
                <div class="card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create an Account</h4>
                    <p class="mb-0 mt-2 small opacity-75">Join our church community</p>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-error-custom">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success-custom">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="registrationForm">
                        <!-- Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">
                                <i class="fas fa-user me-2"></i>Username <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control form-control-custom <?php echo (isset($errors) && in_array('Username already exists!', $errors)) ? 'is-invalid' : ''; ?>" 
                                   id="username" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                                   placeholder="Choose a username"
                                   pattern="[a-zA-Z0-9_]{3,20}"
                                   title="Username must be 3-20 characters and can only contain letters, numbers, and underscores"
                                   required>
                            <small class="text-muted">3-20 characters, letters, numbers, and underscores only</small>
                        </div>
                        
                        <!-- Name Row -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label fw-bold">
                                    <i class="fas fa-signature me-2"></i>First Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-custom" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($form_data['first_name']); ?>" 
                                       placeholder="Enter first name"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label fw-bold">
                                    <i class="fas fa-signature me-2"></i>Last Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-custom" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($form_data['last_name']); ?>" 
                                       placeholder="Enter last name"
                                       required>
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">
                                <i class="fas fa-envelope me-2"></i>Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   class="form-control form-control-custom <?php echo (isset($errors) && in_array('Email already exists!', $errors)) ? 'is-invalid' : ''; ?>" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                   placeholder="Enter your email"
                                   required>
                        </div>
                        
                        <!-- Phone -->
                        <div class="mb-3">
                            <label for="phone" class="form-label fw-bold">
                                <i class="fas fa-phone me-2"></i>Phone Number
                            </label>
                            <input type="tel" class="form-control form-control-custom" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                                   placeholder="(123) 456-7890">
                        </div>
                        
                        <!-- Password Row -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-2"></i>Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control form-control-custom" id="password" name="password" 
                                       placeholder="Create password"
                                       required>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <small class="text-muted">Minimum 6 characters with at least one letter and one number</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-2"></i>Confirm Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control form-control-custom" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm password"
                                       required>
                                <div class="invalid-feedback" id="passwordMatchError" style="display: none;">
                                    Passwords do not match
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-primary">Terms of Service</a> and 
                                <a href="#" class="text-primary">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary-custom w-100" id="submitBtn">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="login.php" class="text-primary fw-bold">Login here</a>
                        </p>
                        <p class="mt-2">
                            <a href="index.php" class="text-muted">
                                <i class="fas fa-arrow-left me-1"></i>Back to Homepage
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Strength and Validation Script -->
<script>
// Password strength checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    let strength = 0;
    
    // Check length
    if (password.length >= 6) strength += 33.33;
    
    // Check for letters
    if (/[A-Za-z]/.test(password)) strength += 33.33;
    
    // Check for numbers
    if (/[0-9]/.test(password)) strength += 33.34;
    
    strengthBar.style.width = strength + '%';
    
    if (strength <= 33.33) {
        strengthBar.className = 'progress-bar bg-danger';
    } else if (strength <= 66.66) {
        strengthBar.className = 'progress-bar bg-warning';
    } else {
        strengthBar.className = 'progress-bar bg-success';
    }
});

// Password match checker
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    const errorDiv = document.getElementById('passwordMatchError');
    
    if (password !== confirm) {
        this.classList.add('is-invalid');
        errorDiv.style.display = 'block';
    } else {
        this.classList.remove('is-invalid');
        errorDiv.style.display = 'none';
    }
});

// Form submission loading state
document.getElementById('registrationForm').addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<span class="spinner-custom me-2"></span>Creating Account...';
    submitBtn.disabled = true;
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

// Real-time username validation
document.getElementById('username').addEventListener('input', function() {
    const username = this.value;
    const pattern = /^[a-zA-Z0-9_]{3,20}$/;
    
    if (username.length > 0 && !pattern.test(username)) {
        this.classList.add('is-invalid');
        this.setCustomValidity('Invalid username format');
    } else {
        this.classList.remove('is-invalid');
        this.setCustomValidity('');
    }
});

// Real-time email validation
document.getElementById('email').addEventListener('input', function() {
    const email = this.value;
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email.length > 0 && !pattern.test(email)) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php
// Include footer
include 'footer.php';
?>