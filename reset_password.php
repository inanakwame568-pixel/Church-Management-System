<?php
// reset_password.php - Complete password reset page
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Start session
startSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: admin/dashboard.php');
    exit();
}

$error = '';
$success = '';
$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';

// Verify token exists
if (empty($token)) {
    header('Location: forgot_password.php?error=invalid_token');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    
    // Validate password
    $errors = [];
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one letter and one number";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verify token is valid and not expired
            $stmt = $db->prepare("
                SELECT pr.*, u.user_id, u.username 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.user_id
                WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = FALSE
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $reset = $result->fetch_assoc();
                
                // Hash new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update user's password
                $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update_stmt->bind_param("si", $hashed_password, $reset['user_id']);
                
                if ($update_stmt->execute()) {
                    // Mark token as used
                    $use_stmt = $db->prepare("UPDATE password_resets SET used = TRUE WHERE reset_id = ?");
                    $use_stmt->bind_param("i", $reset['reset_id']);
                    $use_stmt->execute();
                    
                    $success = "Password reset successful! You can now login with your new password.";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
            } else {
                $error = "Invalid or expired reset token. Please request a new password reset.";
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Set page title
$page_title = "Reset Password";

// Include header
include 'header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-gradient-primary text-white text-center py-4 border-0">
                    <i class="fas fa-key fa-3x mb-3"></i>
                    <h3 class="mb-0 fw-bold">Reset Password</h3>
                    <p class="mb-0 mt-2 small opacity-75">Enter your new password</p>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary px-5">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" id="resetForm">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-2"></i>New Password
                                </label>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Enter new password"
                                       required>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <small class="text-muted">Minimum 6 characters with letters and numbers</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-2"></i>Confirm Password
                                </label>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Confirm new password"
                                       required>
                                <div class="invalid-feedback" id="passwordMatchError" style="display: none;">
                                    Passwords do not match
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-3" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Reset Password
                            </button>
                            
                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength checker
document.getElementById('password')?.addEventListener('input', function() {
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
document.getElementById('confirm_password')?.addEventListener('input', function() {
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
document.getElementById('resetForm')?.addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Resetting...';
});
</script>

<?php
include 'footer.php';
?>