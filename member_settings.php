<?php
// member_settings.php - Member Account Settings
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php';

// Require member login
requireMember();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current member info
$member_id = getCurrentMemberId();
$user_id = getCurrentUserId();
$user_name = getCurrentUserName();

// Get member details
$member_query = "SELECT m.*, u.username, u.email, u.created_at as account_created
                 FROM members m
                 JOIN users u ON u.email = m.email
                 WHERE m.member_id = ?";
$member_stmt = $db->prepare($member_query);
$member_stmt->bind_param("i", $member_id);
$member_stmt->execute();
$member = $member_stmt->get_result()->fetch_assoc();

// Initialize variables
$success = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $zip = sanitize($_POST['zip'] ?? '');
    $emergency_name = sanitize($_POST['emergency_name'] ?? '');
    $emergency_phone = sanitize($_POST['emergency_phone'] ?? '');
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    
    $errors = [];
    
    // Validate phone if provided
    if (!empty($phone) && !validatePhone($phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (!empty($emergency_phone) && !validatePhone($emergency_phone)) {
        $errors[] = "Invalid emergency phone number format";
    }
    
    if (empty($errors)) {
        $update_query = "UPDATE members SET 
                         phone = ?, 
                         address = ?, 
                         city = ?, 
                         state = ?, 
                         zip_code = ?,
                         date_of_birth = ?,
                         emergency_contact_name = ?,
                         emergency_contact_phone = ?
                         WHERE member_id = ?";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param(
            "ssssssssi", 
            $phone, 
            $address, 
            $city, 
            $state, 
            $zip, 
            $birthday,
            $emergency_name, 
            $emergency_phone, 
            $member_id
        );
        
        if ($update_stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh member data
            $member_stmt->execute();
            $member = $member_stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating profile: " . $db->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $errors[] = "Password must contain at least one letter and one number";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        // Verify current password
        $user_query = "SELECT password FROM users WHERE user_id = ?";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_stmt = $db->prepare($update_pass);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Error changing password: " . $db->error;
            }
        } else {
            $error = "Current password is incorrect";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle notification preferences
if (isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $event_reminders = isset($_POST['event_reminders']) ? 1 : 0;
    $group_updates = isset($_POST['group_updates']) ? 1 : 0;
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    // Check if settings table exists
    $table_check = $db->query("SHOW TABLES LIKE 'member_settings'");
    if ($table_check->num_rows == 0) {
        // Create settings table
        $create_table = "CREATE TABLE IF NOT EXISTS member_settings (
            setting_id INT PRIMARY KEY AUTO_INCREMENT,
            member_id INT NOT NULL,
            email_notifications BOOLEAN DEFAULT TRUE,
            sms_notifications BOOLEAN DEFAULT FALSE,
            event_reminders BOOLEAN DEFAULT TRUE,
            group_updates BOOLEAN DEFAULT TRUE,
            newsletter BOOLEAN DEFAULT TRUE,
            notification_frequency ENUM('immediate', 'daily', 'weekly') DEFAULT 'immediate',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
            UNIQUE KEY unique_member (member_id)
        )";
        $db->query($create_table);
    }
    
    // Insert or update settings
    $settings_query = "INSERT INTO member_settings 
                       (member_id, email_notifications, sms_notifications, event_reminders, group_updates, newsletter)
                       VALUES (?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE
                       email_notifications = VALUES(email_notifications),
                       sms_notifications = VALUES(sms_notifications),
                       event_reminders = VALUES(event_reminders),
                       group_updates = VALUES(group_updates),
                       newsletter = VALUES(newsletter)";
    
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->bind_param(
        "iiiiii", 
        $member_id, 
        $email_notifications, 
        $sms_notifications, 
        $event_reminders, 
        $group_updates, 
        $newsletter
    );
    
    if ($settings_stmt->execute()) {
        $success = "Notification preferences updated!";
    } else {
        $error = "Error updating preferences: " . $db->error;
    }
}

// Handle privacy settings
if (isset($_POST['update_privacy'])) {
    $profile_visibility = $_POST['profile_visibility'] ?? 'members';
    $show_email = isset($_POST['show_email']) ? 1 : 0;
    $show_phone = isset($_POST['show_phone']) ? 1 : 0;
    $show_birthday = isset($_POST['show_birthday']) ? 1 : 0;
    
    // Check if privacy table exists
    $table_check = $db->query("SHOW TABLES LIKE 'member_privacy'");
    if ($table_check->num_rows == 0) {
        // Create privacy table
        $create_table = "CREATE TABLE IF NOT EXISTS member_privacy (
            privacy_id INT PRIMARY KEY AUTO_INCREMENT,
            member_id INT NOT NULL,
            profile_visibility ENUM('public', 'members', 'private') DEFAULT 'members',
            show_email BOOLEAN DEFAULT FALSE,
            show_phone BOOLEAN DEFAULT FALSE,
            show_birthday BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
            UNIQUE KEY unique_member (member_id)
        )";
        $db->query($create_table);
    }
    
    $privacy_query = "INSERT INTO member_privacy 
                      (member_id, profile_visibility, show_email, show_phone, show_birthday)
                      VALUES (?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                      profile_visibility = VALUES(profile_visibility),
                      show_email = VALUES(show_email),
                      show_phone = VALUES(show_phone),
                      show_birthday = VALUES(show_birthday)";
    
    $privacy_stmt = $db->prepare($privacy_query);
    $privacy_stmt->bind_param(
        "isiii", 
        $member_id, 
        $profile_visibility, 
        $show_email, 
        $show_phone, 
        $show_birthday
    );
    
    if ($privacy_stmt->execute()) {
        $success = "Privacy settings updated!";
    } else {
        $error = "Error updating privacy settings: " . $db->error;
    }
}

// Get current settings
$settings = [];
$settings_query = "SELECT * FROM member_settings WHERE member_id = ?";
$settings_stmt = $db->prepare($settings_query);
$settings_stmt->bind_param("i", $member_id);
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();
if ($settings_result->num_rows > 0) {
    $settings = $settings_result->fetch_assoc();
} else {
    // Default settings
    $settings = [
        'email_notifications' => 1,
        'sms_notifications' => 0,
        'event_reminders' => 1,
        'group_updates' => 1,
        'newsletter' => 1,
        'notification_frequency' => 'immediate'
    ];
}

// Get privacy settings
$privacy = [];
$privacy_query = "SELECT * FROM member_privacy WHERE member_id = ?";
$privacy_stmt = $db->prepare($privacy_query);
$privacy_stmt->bind_param("i", $member_id);
$privacy_stmt->execute();
$privacy_result = $privacy_stmt->get_result();
if ($privacy_result->num_rows > 0) {
    $privacy = $privacy_result->fetch_assoc();
} else {
    // Default privacy
    $privacy = [
        'profile_visibility' => 'members',
        'show_email' => 0,
        'show_phone' => 0,
        'show_birthday' => 0
    ];
}

// Set page title
$page_title = "Account Settings";

// Include member header
include 'member_header.php';
?>

<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-cog me-2 text-primary"></i>Account Settings</h2>
            <p class="text-muted mb-0">Manage your account preferences and security</p>
        </div>
    </div>

    <!-- Settings Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab == 'profile' ? 'active' : ''; ?>" 
                    id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" 
                    type="button" role="tab">
                <i class="fas fa-user me-2"></i>Profile
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab == 'security' ? 'active' : ''; ?>" 
                    id="security-tab" data-bs-toggle="tab" data-bs-target="#security" 
                    type="button" role="tab">
                <i class="fas fa-lock me-2"></i>Security
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>" 
                    id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" 
                    type="button" role="tab">
                <i class="fas fa-bell me-2"></i>Notifications
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab == 'privacy' ? 'active' : ''; ?>" 
                    id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" 
                    type="button" role="tab">
                <i class="fas fa-shield-alt me-2"></i>Privacy
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $active_tab == 'data' ? 'active' : ''; ?>" 
                    id="data-tab" data-bs-toggle="tab" data-bs-target="#data" 
                    type="button" role="tab">
                <i class="fas fa-database me-2"></i>Data
            </button>
        </li>
    </ul>

    <!-- Success/Error Messages -->
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

    <!-- Tab Content -->
    <div class="tab-content" id="settingsTabsContent">
        <!-- Profile Tab -->
        <div class="tab-pane fade <?php echo $active_tab == 'profile' ? 'show active' : ''; ?>" id="profile" role="tabpanel">
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile Information</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="?tab=profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">First Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['first_name']); ?>" readonly disabled>
                                <small class="text-muted">Contact admin to change name</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Last Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['last_name']); ?>" readonly disabled>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($member['email']); ?>" readonly disabled>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Date of Birth</label>
                                <input type="date" class="form-control" name="birthday" value="<?php echo $member['date_of_birth']; ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-bold">Address</label>
                                <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($member['address'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold">City</label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($member['city'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold">State</label>
                                <select class="form-select" name="state">
                                    <option value="">Select State</option>
                                    <?php
                                    $states = [
                                        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
                                        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
                                        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
                                        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
                                        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
                                        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
                                        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
                                        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
                                        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
                                        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
                                        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
                                        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
                                        'WI' => 'Wisconsin', 'WY' => 'Wyoming'
                                    ];
                                    foreach ($states as $code => $name):
                                    ?>
                                        <option value="<?php echo $code; ?>" <?php echo ($member['state'] ?? '') == $code ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold">ZIP Code</label>
                                <input type="text" class="form-control" name="zip" value="<?php echo htmlspecialchars($member['zip_code'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-12">
                                <hr>
                                <h6 class="fw-bold mb-3">Emergency Contact</h6>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_name" value="<?php echo htmlspecialchars($member['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_phone" value="<?php echo htmlspecialchars($member['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div class="tab-pane fade <?php echo $active_tab == 'security' ? 'show active' : ''; ?>" id="security" role="tabpanel">
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-lock me-2 text-success"></i>Change Password</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="?tab=security" id="passwordForm">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">New Password</label>
                                <input type="password" class="form-control" name="new_password" id="new_password" required>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <small class="text-muted">Minimum 6 characters with letters and numbers</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                <div class="invalid-feedback" id="passwordMatchError" style="display: none;">
                                    Passwords do not match
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" name="change_password" class="btn btn-primary" id="changePasswordBtn">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Two-Factor Authentication (Optional) -->
            <div class="member-card mt-4">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-mobile-alt me-2 text-info"></i>Two-Factor Authentication</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Enhance your account security with two-factor authentication.</p>
                    <button class="btn btn-outline-primary" disabled>
                        <i class="fas fa-qrcode me-2"></i>Setup 2FA (Coming Soon)
                    </button>
                </div>
            </div>
            
            <!-- Active Sessions -->
            <div class="member-card mt-4">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-desktop me-2 text-warning"></i>Active Sessions</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">You are currently logged in from this device.</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Last login: <?php echo date('F j, Y g:i A'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div class="tab-pane fade <?php echo $active_tab == 'notifications' ? 'show active' : ''; ?>" id="notifications" role="tabpanel">
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-bell me-2 text-warning"></i>Notification Preferences</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="?tab=notifications">
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Email Notifications</h6>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" 
                                       <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_notifications">
                                    Receive email notifications
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="event_reminders" id="event_reminders"
                                       <?php echo $settings['event_reminders'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="event_reminders">
                                    Event reminders
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="group_updates" id="group_updates"
                                       <?php echo $settings['group_updates'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="group_updates">
                                    Group updates and announcements
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="newsletter" id="newsletter"
                                       <?php echo $settings['newsletter'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="newsletter">
                                    Church newsletter
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">SMS Notifications</h6>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="sms_notifications" id="sms_notifications"
                                       <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sms_notifications">
                                    Receive SMS notifications
                                </label>
                            </div>
                            <small class="text-muted">Message and data rates may apply</small>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Notification Frequency</h6>
                            <select class="form-select" name="notification_frequency">
                                <option value="immediate" <?php echo ($settings['notification_frequency'] ?? 'immediate') == 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                <option value="daily" <?php echo ($settings['notification_frequency'] ?? '') == 'daily' ? 'selected' : ''; ?>>Daily Digest</option>
                                <option value="weekly" <?php echo ($settings['notification_frequency'] ?? '') == 'weekly' ? 'selected' : ''; ?>>Weekly Digest</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="update_notifications" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Preferences
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Privacy Tab -->
        <div class="tab-pane fade <?php echo $active_tab == 'privacy' ? 'show active' : ''; ?>" id="privacy" role="tabpanel">
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2 text-success"></i>Privacy Settings</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="?tab=privacy">
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Profile Visibility</h6>
                            <select class="form-select" name="profile_visibility">
                                <option value="public" <?php echo $privacy['profile_visibility'] == 'public' ? 'selected' : ''; ?>>Public - Anyone can see my profile</option>
                                <option value="members" <?php echo $privacy['profile_visibility'] == 'members' ? 'selected' : ''; ?>>Members Only - Only church members can see my profile</option>
                                <option value="private" <?php echo $privacy['profile_visibility'] == 'private' ? 'selected' : ''; ?>>Private - Only church staff can see my profile</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">What others can see</h6>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="show_email" id="show_email"
                                       <?php echo $privacy['show_email'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_email">
                                    Show my email address to other members
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="show_phone" id="show_phone"
                                       <?php echo $privacy['show_phone'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_phone">
                                    Show my phone number to other members
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="show_birthday" id="show_birthday"
                                       <?php echo $privacy['show_birthday'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_birthday">
                                    Show my birthday in the church directory
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_privacy" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Privacy Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Data Tab -->
        <div class="tab-pane fade <?php echo $active_tab == 'data' ? 'show active' : ''; ?>" id="data" role="tabpanel">
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-database me-2 text-info"></i>Data Management</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6 class="fw-bold mb-3"><i class="fas fa-download me-2 text-primary"></i>Export My Data</h6>
                                <p class="text-muted small">Download a copy of your personal data including profile, donations, and activity.</p>
                                <button class="btn btn-outline-primary" onclick="exportMyData()">
                                    <i class="fas fa-file-export me-2"></i>Export Data
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6 class="fw-bold mb-3"><i class="fas fa-print me-2 text-success"></i>Printable Directory</h6>
                                <p class="text-muted small">Generate a printable version of your profile information.</p>
                                <button class="btn btn-outline-success" onclick="window.open('member_print.php', '_blank')">
                                    <i class="fas fa-print me-2"></i>Print Profile
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6 class="fw-bold mb-3"><i class="fas fa-history me-2 text-warning"></i>Activity Log</h6>
                                <p class="text-muted small">View your recent activity and account changes.</p>
                                <button class="btn btn-outline-warning" onclick="viewActivityLog()">
                                    <i class="fas fa-clock me-2"></i>View Activity
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="border rounded p-3 border-danger">
                                <h6 class="fw-bold mb-3 text-danger"><i class="fas fa-trash me-2"></i>Delete Account</h6>
                                <p class="text-muted small">Permanently delete your account and all associated data.</p>
                                <button class="btn btn-outline-danger" onclick="confirmDeleteAccount()">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Request Deletion
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Confirmation Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Account Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold">Are you absolutely sure you want to delete your account?</p>
                <p>This action <span class="text-danger">cannot be undone</span>. This will permanently delete:</p>
                <ul>
                    <li>Your profile information</li>
                    <li>Your donation history</li>
                    <li>Your event registrations</li>
                    <li>Your group memberships</li>
                    <li>All associated data</li>
                </ul>
                <p>If you're sure, please type <strong class="text-danger">DELETE</strong> below to confirm:</p>
                <input type="text" class="form-control" id="confirmDelete" placeholder="Type DELETE">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled onclick="deleteAccount()">
                    <i class="fas fa-trash me-2"></i>Permanently Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Settings page specific styles */
.nav-tabs .nav-link {
    color: #64748b;
    border: none;
    padding: 12px 24px;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    border: none;
    color: #4361ee;
}

.nav-tabs .nav-link.active {
    color: #4361ee;
    background: transparent;
    border-bottom: 3px solid #4361ee;
}

.form-check-input:checked {
    background-color: #4361ee;
    border-color: #4361ee;
}

/* Password strength indicator */
.progress-bar.bg-danger { background-color: #dc3545 !important; }
.progress-bar.bg-warning { background-color: #ffc107 !important; }
.progress-bar.bg-success { background-color: #28a745 !important; }

/* Responsive */
@media (max-width: 768px) {
    .nav-tabs .nav-link {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .row.g-4 .col-md-6 {
        margin-bottom: 15px;
    }
}
</style>

<script>
// Password strength checker
document.getElementById('new_password')?.addEventListener('input', function() {
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
    const password = document.getElementById('new_password').value;
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
document.getElementById('passwordForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('changePasswordBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Changing...';
});

// Tab persistence
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function(e) {
        const tabId = e.target.getAttribute('data-bs-target').replace('#', '');
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
    });
});

// Phone number formatting
document.querySelectorAll('input[type="tel"]').forEach(input => {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 10) value = value.slice(0, 10);
        
        if (value.length >= 6) {
            value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6);
        } else if (value.length >= 3) {
            value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
        }
        
        e.target.value = value;
    });
});

// Account deletion confirmation
function confirmDeleteAccount() {
    new bootstrap.Modal(document.getElementById('deleteAccountModal')).show();
}

document.getElementById('confirmDelete')?.addEventListener('input', function() {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = this.value !== 'DELETE';
});

function deleteAccount() {
    alert('Account deletion request submitted. An administrator will process your request.');
    bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal')).hide();
}

// Export data function
function exportMyData() {
    window.location.href = 'export_member_data.php';
}

// View activity log
function viewActivityLog() {
    window.location.href = 'member_activity.php';
}

// Load active tab from URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    
    if (tab) {
        const tabEl = document.querySelector(`[data-bs-target="#${tab}"]`);
        if (tabEl) {
            new bootstrap.Tab(tabEl).show();
        }
    }
});
</script>

<?php
// Include member footer
include 'member_footer.php';
?>