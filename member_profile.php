<?php
// member_profile.php - Member Profile View
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

// Get member details
$member_query = "SELECT m.*, u.username, u.email as user_email, u.created_at as account_created
                 FROM members m
                 JOIN users u ON u.email = m.email
                 WHERE m.member_id = ?";
$member_stmt = $db->prepare($member_query);
$member_stmt->bind_param("i", $member_id);
$member_stmt->execute();
$member = $member_stmt->get_result()->fetch_assoc();

// Handle profile update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $zip = sanitize($_POST['zip'] ?? '');
    $emergency_name = sanitize($_POST['emergency_name'] ?? '');
    $emergency_phone = sanitize($_POST['emergency_phone'] ?? '');
    
    $update_query = "UPDATE members SET 
                     phone = ?, 
                     address = ?, 
                     city = ?, 
                     state = ?, 
                     zip_code = ?,
                     emergency_contact_name = ?,
                     emergency_contact_phone = ?
                     WHERE member_id = ?";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bind_param("sssssssi", $phone, $address, $city, $state, $zip, $emergency_name, $emergency_phone, $member_id);
    
    if ($update_stmt->execute()) {
        $success = "Profile updated successfully!";
        // Refresh member data
        $member_stmt->execute();
        $member = $member_stmt->get_result()->fetch_assoc();
    } else {
        $error = "Error updating profile: " . $db->error;
    }
}

// Set page title
$page_title = "My Profile";

// Include member header
include 'member_header.php';
?>

<div class="row fade-in">
    <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="member-card mb-4">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="fas fa-user-circle me-2 text-primary"></i>Profile Photo</h5>
            </div>
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <?php if (!empty($member['profile_image'])): ?>
                        <img src="uploads/profiles/<?php echo $member['profile_image']; ?>" 
                             class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 150px; height: 150px;">
                            <i class="fas fa-user fa-4x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h4><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h4>
                <p class="text-muted mb-1">Member since <?php echo date('F Y', strtotime($member['membership_date'])); ?></p>
                <p class="text-muted small">
                    <i class="fas fa-calendar me-1"></i>Birthday: <?php echo $member['date_of_birth'] ? date('F j', strtotime($member['date_of_birth'])) : 'Not set'; ?>
                </p>
            </div>
        </div>

        <!-- Account Info -->
        <div class="member-card">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="fas fa-cog me-2 text-secondary"></i>Account Settings</h5>
            </div>
            <div class="card-body">
                <p><strong>Username:</strong> <?php echo htmlspecialchars($member['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                <p><strong>Account Created:</strong> <?php echo date('F j, Y', strtotime($member['account_created'])); ?></p>
                <hr>
                <a href="change_password.php" class="btn btn-member-outline w-100 mb-2">
                    <i class="fas fa-key me-2"></i>Change Password
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Edit Profile Form -->
        <div class="member-card">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="fas fa-edit me-2 text-success"></i>Edit Profile</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">First Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['first_name']); ?>" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Last Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['last_name']); ?>" readonly disabled>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($member['email']); ?>" readonly disabled>
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
                            <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($member['state'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ZIP Code</label>
                            <input type="text" class="form-control" name="zip" value="<?php echo htmlspecialchars($member['zip_code'] ?? ''); ?>">
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
                            <button type="submit" class="btn btn-member-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include 'member_footer.php';
?>