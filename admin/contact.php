<?php
// admin/contact.php - Contact Management Page (FIXED VERSION)
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current user info
$user_id = getCurrentUserId();
$user_name = getCurrentUserName();
$user_email = $_SESSION['user_email'] ?? '';

// Handle contact form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $department = $_POST['department'] ?? 'general';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($errors)) {
        // Prepare email content
        $to = "support@" . $_SERVER['HTTP_HOST']; // Change to your support email
        $email_subject = "[$priority] $subject - $department";
        
        $email_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4361ee; color: white; padding: 20px; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 10px 10px; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #4361ee; }
                .priority-high { color: #dc3545; font-weight: bold; }
                .priority-normal { color: #28a745; }
                .priority-low { color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Contact Form Submission</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <div class='label'>From:</div>
                        <div>$name ($email)</div>
                    </div>
                    <div class='field'>
                        <div class='label'>User ID:</div>
                        <div>$user_id</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Department:</div>
                        <div>" . ucfirst($department) . "</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Priority:</div>
                        <div class='priority-$priority'>" . ucfirst($priority) . "</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Subject:</div>
                        <div>$subject</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Message:</div>
                        <div style='background: white; padding: 15px; border-radius: 5px;'>" . nl2br($message) . "</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Email headers
        $headers = "From: $name <$email>\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // For local development, log instead of sending
        if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
            // Development mode - show success message
            $success = "✅ DEVELOPMENT MODE: Message received. In production, this would be sent to support.";
            
            // Log the message
            error_log("Contact form submission from $name ($email): $subject");
        } else {
            // Production - send email
            if (mail($to, $email_subject, $email_message, $headers)) {
                $success = "Your message has been sent successfully! We'll get back to you soon.";
                
                // Optional: Save to database
                $save_query = "INSERT INTO contact_messages (user_id, name, email, subject, message, priority, department, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'new')";
                $save_stmt = $db->prepare($save_query);
                $save_stmt->bind_param("issssss", $user_id, $name, $email, $subject, $message, $priority, $department);
                $save_stmt->execute();
            } else {
                $error = "Failed to send message. Please try again later.";
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get contact information from database or config
$contact_info = [
    'address' => 'Bolga-Temale Road',
    'phone' => '(+233) 55 335 8568',
    'email' => 'info@desertpastures.org',
    'support_email' => 'support@church.org',
    'office_hours' => 'Monday - Friday: 9:00 AM - 5:00 PM',
    'emergency_contact' => '(+233) 55 335 8568'
];

// Get team members/staff for contact
$staff_query = "SELECT u.user_id, u.full_name, u.email, u.role 
                FROM users u 
                WHERE u.role IN ('admin', 'pastor', 'secretary')
                ORDER BY 
                    CASE u.role
                        WHEN 'admin' THEN 1
                        WHEN 'pastor' THEN 2
                        WHEN 'secretary' THEN 3
                        ELSE 4
                    END, u.full_name";
$staff_result = $db->query($staff_query);

// Get recent contact messages (if table exists)
$recent_messages = [];
$table_check = $db->query("SHOW TABLES LIKE 'contact_messages'");
if ($table_check && $table_check->num_rows > 0) {
    $messages_query = "SELECT * FROM contact_messages 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 5";
    $messages_stmt = $db->prepare($messages_query);
    $messages_stmt->bind_param("i", $user_id);
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();
    while ($row = $messages_result->fetch_assoc()) {
        $recent_messages[] = $row;
    }
}

// Set page title
$page_title = "Contact Support";

// Include header
include '../header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded-3 shadow-sm">
                <div>
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-headset me-3 text-primary"></i>
                        Contact Support
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-envelope me-2"></i>
                        Get help with any issues or questions you have
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Info
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Contact Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-3">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <h5>Phone</h5>
                    <p class="mb-1">
                        <a href="tel:<?php echo $contact_info['phone']; ?>" class="text-decoration-none">
                            <?php echo $contact_info['phone']; ?>
                        </a>
                    </p>
                    <small class="text-muted">Office</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-3">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5>Email</h5>
                    <p class="mb-1">
                        <a href="mailto:<?php echo $contact_info['support_email']; ?>" class="text-decoration-none">
                            <?php echo $contact_info['support_email']; ?>
                        </a>
                    </p>
                    <small class="text-muted">24/7 Support</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-info mb-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5>Office Hours</h5>
                    <p class="mb-1"><?php echo $contact_info['office_hours']; ?></p>
                    <small class="text-muted">Your Timezone</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-warning mb-3">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5>Emergency</h5>
                    <p class="mb-1">
                        <a href="tel:<?php echo $contact_info['emergency_contact']; ?>" class="text-decoration-none">
                            <?php echo $contact_info['emergency_contact']; ?>
                        </a>
                    </p>
                    <small class="text-muted">Urgent Issues</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
        <!-- Contact Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-paper-plane me-2 text-primary"></i>
                        Send a Message
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="contactForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-bold">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user_name ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-bold">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user_email ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="department" class="form-label fw-bold">Department</label>
                                <select class="form-select" id="department" name="department">
                                    <option value="general">General Inquiry</option>
                                    <option value="technical">Technical Support</option>
                                    <option value="billing">Billing & Donations</option>
                                    <option value="membership">Membership</option>
                                    <option value="events">Events</option>
                                    <option value="groups">Small Groups</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="priority" class="form-label fw-bold">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="subject" class="form-label fw-bold">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="col-12">
                                <label for="message" class="form-label fw-bold">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Please provide as much detail as possible to help us assist you better.
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary px-5" id="submitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-undo me-2"></i>Clear
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Messages (if any) -->
            <?php if (!empty($recent_messages)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-history me-2 text-info"></i>
                        Your Recent Messages
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_messages as $msg): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($msg['subject'] ?? ''); ?></h6>
                                    <span class="badge bg-<?php 
                                        $status = $msg['status'] ?? 'new';
                                        echo $status == 'new' ? 'primary' : 
                                            ($status == 'read' ? 'info' : 
                                            ($status == 'replied' ? 'success' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                                <p class="small text-muted mb-1">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo isset($msg['created_at']) ? timeAgo($msg['created_at']) : 'Recently'; ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo ucfirst($msg['department'] ?? 'general'); ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-flag me-1"></i>
                                    <span class="priority-<?php echo $msg['priority'] ?? 'normal'; ?>">
                                        <?php echo ucfirst($msg['priority'] ?? 'normal'); ?>
                                    </span>
                                </p>
                                <p class="small mb-0">
                                    <?php echo htmlspecialchars(substr($msg['message'] ?? '', 0, 100)); ?>...
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar Information -->
        <div class="col-lg-4">
            <!-- Staff Contacts -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-users me-2 text-success"></i>
                        Staff Contacts
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if ($staff_result && $staff_result->num_rows > 0): ?>
                            <?php while ($staff = $staff_result->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="staff-avatar bg-primary bg-opacity-10 rounded-circle me-3">
                                            <span class="initials">
                                                <?php 
                                                $full_name = $staff['full_name'] ?? '';
                                                $initials = '';
                                                $name_parts = explode(' ', $full_name);
                                                foreach ($name_parts as $part) {
                                                    if (!empty($part)) {
                                                        $initials .= strtoupper(substr($part, 0, 1));
                                                    }
                                                }
                                                echo substr($initials, 0, 2) ?: 'U';
                                                ?>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($staff['full_name'] ?? 'Unknown'); ?></h6>
                                            <small class="text-muted d-block"><?php echo ucfirst($staff['role'] ?? 'Staff'); ?></small>
                                            <small>
                                                <a href="mailto:<?php echo $staff['email'] ?? ''; ?>" class="text-decoration-none">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($staff['email'] ?? 'No email'); ?>
                                                </a>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No staff contacts available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Links -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-question-circle me-2 text-warning"></i>
                        Quick Help
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-plus me-2 text-primary"></i>
                            How to add a new member?
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-check me-2 text-success"></i>
                            Taking attendance guide
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-hand-holding-heart me-2 text-warning"></i>
                            Recording donations
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-alt me-2 text-info"></i>
                            Creating events
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-users-cog me-2 text-danger"></i>
                            Managing groups
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-alt me-2 text-secondary"></i>
                            Generating reports
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Office Location -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-map-marker-alt me-2 text-danger"></i>
                        Office Location
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-church fa-3x text-primary mb-2"></i>
                        <h6><?php echo $contact_info['address']; ?></h6>
                    </div>
                    <div class="ratio ratio-16x9 bg-light rounded-3 d-flex align-items-center justify-content-center">
                        <!-- Placeholder for map - you can embed Google Maps here -->
                        <div class="text-center p-4">
                            <i class="fas fa-map fa-3x text-muted mb-3"></i>
                            <p class="mb-0">Map integration coming soon</p>
                            <small class="text-muted">Google Maps API</small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="https://maps.google.com/?q=<?php echo urlencode($contact_info['address']); ?>" 
                           target="_blank" class="btn btn-outline-primary w-100">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create contact_messages table if needed -->
    <?php if ($table_check && $table_check->num_rows == 0): ?>
    <!-- Hidden message to create table -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-database me-2"></i>
                Tip: Create a <code>contact_messages</code> table to store message history.
                <button class="btn btn-sm btn-primary ms-3" onclick="createContactTable()">
                    Create Table
                </button>
            </div>
        </div>
    </div>
    
    <script>
    function createContactTable() {
        if (confirm('Create contact_messages table? This will store all contact form submissions.')) {
            // You would make an AJAX call here to create the table
            alert('Table creation feature coming soon!');
        }
    }
    </script>
    <?php endif; ?>
</div>

<style>
/* Contact page specific styles */
.staff-avatar {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.staff-avatar .initials {
    font-size: 14px;
    font-weight: 600;
    color: var(--bs-primary);
}

/* Priority indicators */
.priority-low {
    color: #6c757d;
}

.priority-normal {
    color: #28a745;
}

.priority-high {
    color: #fd7e14;
}

.priority-urgent {
    color: #dc3545;
    font-weight: bold;
}

/* Form styling */
.form-control, .form-select {
    border: 2px solid #e9ecef;
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

/* Card hover effects */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}

/* List group items */
.list-group-item {
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: var(--bs-primary);
}

/* Responsive */
@media (max-width: 768px) {
    .display-6 {
        font-size: 1.5rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

/* Print styles */
@media print {
    .btn, .navbar, .footer, .card-header .btn, form {
        display: none !important;
    }
    
    body {
        background: white;
        padding: 20px;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
    }
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert {
    animation: slideIn 0.5s ease-out;
}
</style>

<script>
// Form validation and submission
document.getElementById('contactForm')?.addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const subject = document.getElementById('subject').value.trim();
    const message = document.getElementById('message').value.trim();
    const submitBtn = document.getElementById('submitBtn');
    
    let isValid = true;
    let errorMessages = [];
    
    if (!name) {
        errorMessages.push('Name is required');
        document.getElementById('name').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('name').classList.remove('is-invalid');
    }
    
    if (!email) {
        errorMessages.push('Email is required');
        document.getElementById('email').classList.add('is-invalid');
        isValid = false;
    } else {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            errorMessages.push('Please enter a valid email address');
            document.getElementById('email').classList.add('is-invalid');
            isValid = false;
        } else {
            document.getElementById('email').classList.remove('is-invalid');
        }
    }
    
    if (!subject) {
        errorMessages.push('Subject is required');
        document.getElementById('subject').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('subject').classList.remove('is-invalid');
    }
    
    if (!message) {
        errorMessages.push('Message is required');
        document.getElementById('message').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('message').classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fix the following errors:\n- ' + errorMessages.join('\n- '));
    } else {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    }
});

// Priority color preview
document.getElementById('priority')?.addEventListener('change', function() {
    const priority = this.value;
    const preview = document.getElementById('priorityPreview');
    if (preview) {
        preview.className = `priority-${priority}`;
    }
});

// Character counter for message
document.getElementById('message')?.addEventListener('input', function() {
    const maxLength = 2000;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    let counter = document.getElementById('charCounter');
    if (!counter) {
        counter = document.createElement('small');
        counter.id = 'charCounter';
        counter.className = 'text-muted float-end';
        this.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/${maxLength} characters`;
    
    if (remaining < 200) {
        counter.classList.add('text-warning');
    } else {
        counter.classList.remove('text-warning');
    }
    
    if (remaining < 0) {
        counter.classList.add('text-danger');
    }
});

// Quick fill demo data (for testing)
function fillDemoData() {
    document.getElementById('subject').value = 'Help with member management';
    document.getElementById('message').value = "I'm having trouble adding new members to the system. The form doesn't seem to save properly. Can you help?";
}

// Add demo button if in development
if (window.location.hostname === 'localhost') {
    const demoBtn = document.createElement('button');
    demoBtn.type = 'button';
    demoBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
    demoBtn.innerHTML = '<i class="fas fa-flask me-1"></i>Fill Demo Data';
    demoBtn.onclick = fillDemoData;
    document.querySelector('.col-12.mt-4').appendChild(demoBtn);
}

// Auto-resize textarea
document.getElementById('message')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});

// Prevent multiple submissions
let submitted = false;
document.getElementById('contactForm')?.addEventListener('submit', function() {
    if (submitted) {
        e.preventDefault();
    }
    submitted = true;
});

// Tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php
// Include footer
include '../footer.php';
?>