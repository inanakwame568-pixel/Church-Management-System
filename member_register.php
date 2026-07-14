<?php
// member_register.php - Public Member Registration
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Start session
startSession();

// Redirect if already logged in
redirectIfLoggedIn();

$error = '';
$success = '';
$form_data = [
    'username' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'state' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $form_data['username'] = sanitize($_POST['username'] ?? '');
    $form_data['email'] = sanitize($_POST['email'] ?? '');
    $form_data['first_name'] = sanitize($_POST['first_name'] ?? '');
    $form_data['last_name'] = sanitize($_POST['last_name'] ?? '');
    $form_data['phone'] = sanitize($_POST['phone'] ?? '');
    $form_data['address'] = sanitize($_POST['address'] ?? '');
    $form_data['city'] = sanitize($_POST['city'] ?? '');
    $form_data['state'] = sanitize($_POST['state'] ?? '');
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']) ? true : false;
    
    // Validation
    $errors = [];
    
    if (empty($form_data['username'])) {
        $errors[] = "Username is required!";
    } elseif (strlen($form_data['username']) < 3) {
        $errors[] = "Username must be at least 3 characters!";
    } elseif (!validateUsername($form_data['username'])) {
        $errors[] = "Username can only contain letters, numbers, and underscores!";
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
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters!";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    if (!$agree_terms) {
        $errors[] = "You must agree to the Terms and Conditions!";
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
                
                // Generate verification token
                $verification_token = bin2hex(random_bytes(32));
                
                // Insert into users table with MEMBER role (NOT admin!)
                $full_name = $form_data['first_name'] . ' ' . $form_data['last_name'];
                $query = "INSERT INTO users (username, password, email, full_name, role, status, verification_token, created_at) 
                         VALUES (?, ?, ?, ?, 'member', 'pending', ?, NOW())";
                $stmt = $db->prepare($query);
                $stmt->bind_param("ssssss", 
                    $form_data['username'],
                    $password_hash,
                    $form_data['email'],
                    $full_name,
                    $verification_token
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create user account: " . $db->error);
                }
                
                $user_id = $db->insert_id;
                
                // Insert into members table
                $query = "INSERT INTO members (user_id, first_name, last_name, phone, email, address, city, state, membership_date, membership_status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Pending')";
                $stmt = $db->prepare($query);
                $stmt->bind_param("isssssss", 
                    $user_id,
                    $form_data['first_name'],
                    $form_data['last_name'],
                    $form_data['phone'],
                    $form_data['email'],
                    $form_data['address'],
                    $form_data['city'],
                    $form_data['state']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create member profile");
                }
                
                $db->commit();
                
                // Send verification email
                $verify_link = APP_URL . "/verify_email.php?token=" . $verification_token;
                $email_sent = sendVerificationEmail($form_data['email'], $full_name, $verify_link);
                
                if ($_SERVER['HTTP_HOST'] == 'localhost') {
                    $success = "Registration successful! Please verify your email using this link: <a href='$verify_link' target='_blank'>Click here to verify</a>";
                } else {
                    $success = "Registration successful! Please check your email to verify your account before logging in.";
                }
                
                // Redirect to login after 3 seconds
                header("Refresh: 3; url=login.php");
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

// Set page title
$page_title = "Member Registration";

// Include header
include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-gradient-primary text-white text-center py-4">
                    <i class="fas fa-user-plus fa-3x mb-2"></i>
                    <h3 class="mb-0">Become a Member</h3>
                    <p class="mb-0 mt-2">Join our church community</p>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!$success): ?>
                    <form method="POST" action="" id="registrationForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">First Name *</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Username *</label>
                                <input type="text" class="form-control" name="username" 
                                       value="<?php echo htmlspecialchars($form_data['username']); ?>" required>
                                <small class="text-muted">3-20 characters, letters, numbers, underscores</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-bold">Address</label>
                                <input type="text" class="form-control" name="address" 
                                       value="<?php echo htmlspecialchars($form_data['address']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold">City</label>
                                <input type="text" class="form-control" name="city" 
                                       value="<?php echo htmlspecialchars($form_data['city']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">State</label>
                                <input type="text" class="form-control" name="state" 
                                       value="<?php echo htmlspecialchars($form_data['state']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">ZIP Code</label>
                                <input type="text" class="form-control" name="zip" 
                                       value="<?php echo htmlspecialchars($form_data['zip'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Password *</label>
                                <input type="password" class="form-control" name="password" required>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar" id="passwordStrength" style="width: 0%;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Confirm Password *</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="agree_terms" id="agree_terms" required>
                                    <label class="form-check-label" for="agree_terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-user-plus me-2"></i>Register as Member
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength meter
document.querySelector('input[name="password"]').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    let strength = 0;
    
    if (password.length >= 6) strength += 33.33;
    if (/[A-Za-z]/.test(password)) strength += 33.33;
    if (/[0-9]/.test(password)) strength += 33.34;
    
    strengthBar.style.width = strength + '%';
    
    if (strength <= 33.33) strengthBar.className = 'progress-bar bg-danger';
    else if (strength <= 66.66) strengthBar.className = 'progress-bar bg-warning';
    else strengthBar.className = 'progress-bar bg-success';
});
</script>

<?php include 'footer.php'; ?>