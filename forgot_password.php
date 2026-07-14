<?php
// forgot_password.php - Password Reset Request Page
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
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!validateEmail($email)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if email exists in users table
            $stmt = $db->prepare("SELECT user_id, username, full_name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                // First, check if password_resets table exists, if not create it
                $create_table = "CREATE TABLE IF NOT EXISTS password_resets (
                    reset_id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    token VARCHAR(100) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_token (token),
                    INDEX idx_expires (expires_at)
                )";
                $db->query($create_table);
                
                // Delete any existing tokens for this user
                $delete_stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $delete_stmt->bind_param("i", $user['user_id']);
                $delete_stmt->execute();
                
                // Insert new token
                $insert_stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("iss", $user['user_id'], $token, $expires);
                
                if ($insert_stmt->execute()) {
                    // Send reset email
                    $reset_link = APP_URL . "/reset_password.php?token=" . $token;
                    
                    $to = $email;
                    $subject = "Password Reset Request - " . APP_NAME;
                    
                    // HTML email template
                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                            .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 50px; margin: 20px 0; font-weight: bold; }
                            .button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
                            .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
                            .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Password Reset Request</h2>
                            </div>
                            <div class='content'>
                                <p>Hello " . htmlspecialchars($user['full_name'] ?: $user['username']) . ",</p>
                                
                                <p>We received a request to reset the password for your account at <strong>" . APP_NAME . "</strong>.</p>
                                
                                <p>Click the button below to reset your password:</p>
                                
                                <div style='text-align: center;'>
                                    <a href='" . $reset_link . "' class='button'>Reset Password</a>
                                </div>
                                
                                <p>Or copy and paste this link into your browser:</p>
                                <p style='word-break: break-all; background: #eee; padding: 10px; border-radius: 5px;'>" . $reset_link . "</p>
                                
                                <div class='warning'>
                                    <strong>⚠ Important:</strong> This link will expire in 1 hour. If you didn't request a password reset, please ignore this email or contact support.
                                </div>
                                
                                <p>For security reasons, never share this link with anyone.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                                <p>This is an automated message, please do not reply.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Plain text version for email clients that don't support HTML
                    $plain_message = "Hello " . ($user['full_name'] ?: $user['username']) . ",\n\n";
                    $plain_message .= "We received a request to reset your password for " . APP_NAME . ".\n\n";
                    $plain_message .= "To reset your password, click this link: " . $reset_link . "\n\n";
                    $plain_message .= "This link will expire in 1 hour.\n\n";
                    $plain_message .= "If you didn't request this, please ignore this email.\n\n";
                    $plain_message .= "Thank you,\n" . APP_NAME;
                    
                    // Email headers
                    $headers = "From: " . APP_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion();
                    
                    // For local development, just log the reset link instead of sending email
                    if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
                        // Development mode - show link directly
                        $success = "✅ DEVELOPMENT MODE: <a href='$reset_link' class='alert-link'>Click here to reset your password</a>";
                        error_log("Password reset link for $email: $reset_link");
                    } else {
                        // Production - send email
                        if (mail($to, $subject, $message, $headers)) {
                            $success = "Password reset instructions have been sent to your email address.";
                        } else {
                            // If mail fails, log and show error
                            error_log("Failed to send password reset email to $email");
                            $error = "Failed to send email. Please try again later or contact support.";
                        }
                    }
                } else {
                    $error = "Failed to generate reset token. Please try again.";
                }
            } else {
                // Don't reveal if email exists or not for security
                $success = "If the email address exists in our system, you will receive password reset instructions.";
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}

// Set page title
$page_title = "Forgot Password";

// Include header
include 'header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-gradient-primary text-white text-center py-4 border-0">
                    <i class="fas fa-lock fa-3x mb-3"></i>
                    <h3 class="mb-0 fw-bold">Forgot Password?</h3>
                    <p class="mb-0 mt-2 small opacity-75">Enter your email to reset your password</p>
                </div>
                
                <div class="card-body p-4">
                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary px-5">
                                <i class="fas fa-sign-in-alt me-2"></i>Return to Login
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reset Form -->
                    <?php if (!$success): ?>
                        <form method="POST" action="" id="resetForm" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="email" class="form-label fw-bold">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email" 
                                       class="form-control form-control-lg <?php echo $error ? 'is-invalid' : ''; ?>" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" 
                                       placeholder="Enter your registered email"
                                       required
                                       autofocus>
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
                                <small class="text-muted">
                                    We'll send password reset instructions to this email.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-3" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                            </button>
                            
                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                                </a>
                                <span class="mx-2 text-muted">|</span>
                                <a href="register.php" class="text-decoration-none">
                                    Create New Account
                                </a>
                            </div>
                        </form>
                        
                        <!-- Security Notice -->
                        <div class="mt-4 p-3 bg-light rounded-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-shield-alt fa-2x text-primary opacity-50"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 fw-bold">Security Notice</h6>
                                    <p class="small text-muted mb-0">
                                        For your security, reset links expire after 1 hour. Never share this link with anyone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Help Footer -->
                <div class="card-footer bg-light text-center py-3 border-0">
                    <small class="text-muted">
                        <i class="fas fa-question-circle me-1"></i>
                        Need help? <a href="contact.php" class="text-decoration-none">Contact Support</a>
                    </small>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb me-2 text-warning"></i>Quick Tips</h6>
                            <ul class="list-unstyled small mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Check your spam/junk folder if you don't see the email
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Make sure you're using the email you registered with
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Reset links expire after 1 hour for security
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Gradient background for header */
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Custom card styling */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15) !important;
}

/* Form input styling */
.form-control-lg {
    border: 2px solid #e9ecef;
    padding: 0.8rem 1.2rem;
    font-size: 1rem;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.form-control-lg:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    outline: none;
}

.form-control-lg.is-invalid {
    border-color: #dc3545;
    background-image: none;
}

/* Button styling */
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.btn-primary:active {
    transform: translateY(0);
}

/* Alert styling */
.alert {
    border-radius: 10px;
    border-left: 4px solid;
}

.alert-success {
    border-left-color: #28a745;
}

.alert-danger {
    border-left-color: #dc3545;
}

.alert-link {
    font-weight: 600;
    color: inherit;
    text-decoration: underline;
}

/* Loading spinner */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.2em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }
    
    .card-header h3 {
        font-size: 1.5rem;
    }
    
    .btn-primary {
        padding: 0.8rem !important;
    }
}

/* Animation for success message */
@keyframes slideInDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.alert-success {
    animation: slideInDown 0.5s ease-out;
}

/* Tooltip styling */
[data-tooltip] {
    position: relative;
    cursor: help;
}

[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 5px 10px;
    background: #333;
    color: white;
    font-size: 12px;
    border-radius: 5px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

[data-tooltip]:hover:before {
    opacity: 1;
    visibility: visible;
    bottom: 120%;
}
</style>

<script>
// Form validation and submission handling
document.getElementById('resetForm')?.addEventListener('submit', function(e) {
    const email = document.getElementById('email').value.trim();
    const submitBtn = document.getElementById('submitBtn');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!email) {
        e.preventDefault();
        showValidationError('email', 'Please enter your email address');
    } else if (!emailRegex.test(email)) {
        e.preventDefault();
        showValidationError('email', 'Please enter a valid email address');
    } else {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    }
});

// Show validation error
function showValidationError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('is-invalid');
    
    // Create or update error message
    let errorDiv = field.nextElementSibling;
    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        field.parentNode.insertBefore(errorDiv, field.nextSibling);
    }
    errorDiv.textContent = message;
    
    // Remove error on input
    field.addEventListener('input', function() {
        this.classList.remove('is-invalid');
    }, { once: true });
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 500);
    });
}, 5000);

// Password reset request tracking (optional)
let requestCount = 0;
document.getElementById('resetForm')?.addEventListener('submit', function() {
    requestCount++;
    
    // Prevent multiple rapid requests
    if (requestCount > 3) {
        alert('Too many reset attempts. Please wait a few minutes and try again.');
        e.preventDefault();
    }
});

// Email validation on input
document.getElementById('email')?.addEventListener('input', function() {
    const email = this.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Show/Hide password requirements tooltip (optional)
const emailField = document.getElementById('email');
if (emailField) {
    emailField.setAttribute('data-tooltip', 'Enter the email address you used to register');
}

// Prevent double-click on submit button
document.getElementById('submitBtn')?.addEventListener('dblclick', function(e) {
    e.preventDefault();
});

// Add keyboard shortcut (Enter submits form - already does)
// Add ESC to clear form
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.activeElement === document.getElementById('email')) {
        document.getElementById('email').value = '';
    }
});
</script>

<?php
// Include footer
include 'footer.php';
?>