<?php
// admin/member_edit.php - Edit Member Information (FIXED VERSION)
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Check if member ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage("Invalid member ID!", "danger");
    header('Location: members.php');
    exit();
}

$member_id = (int)$_GET['id'];

// Get member data
$stmt = $db->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage("Member not found!", "danger");
    header('Location: members.php');
    exit();
}

$member = $result->fetch_assoc();

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $middle_name = sanitize($_POST['middle_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $alternate_phone = sanitize($_POST['alternate_phone'] ?? '');
    
    // FIX: Handle empty date fields - convert to NULL
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $membership_date = !empty($_POST['membership_date']) ? $_POST['membership_date'] : null;
    $baptism_date = !empty($_POST['baptism_date']) ? $_POST['baptism_date'] : null;
    
    $gender = $_POST['gender'] ?? '';
    $marital_status = $_POST['marital_status'] ?? '';
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = $_POST['state'] ?? '';
    $zip_code = sanitize($_POST['zip_code'] ?? '');
    $country = sanitize($_POST['country'] ?? 'USA');
    $occupation = sanitize($_POST['occupation'] ?? '');
    $membership_status = $_POST['membership_status'] ?? 'Active';
    $emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = sanitize($_POST['emergency_contact_phone'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    // Validation
    $errors = [];

    if (empty($first_name)) {
        $errors[] = "First name is required!";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required!";
    }

    if (!empty($email) && !validateEmail($email)) {
        $errors[] = "Invalid email format!";
    }

    // Check if email already exists for another member
    if (!empty($email)) {
        $check_stmt = $db->prepare("SELECT member_id FROM members WHERE email = ? AND member_id != ?");
        $check_stmt->bind_param("si", $email, $member_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $errors[] = "Email already exists for another member!";
        }
    }

    // Handle profile image upload
    $profile_image = $member['profile_image']; // Keep existing by default
    
    // Check if remove photo checkbox is checked
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == 'on') {
        if (!empty($member['profile_image']) && file_exists(UPLOAD_PATH . 'profiles/' . $member['profile_image'])) {
            unlink(UPLOAD_PATH . 'profiles/' . $member['profile_image']);
        }
        $profile_image = null;
    }
    
    // Handle new image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_result = uploadFile($_FILES['profile_image'], UPLOAD_PATH . 'profiles/', ['jpg', 'jpeg', 'png', 'gif']);
        if ($upload_result['success']) {
            // Delete old image if exists
            if (!empty($member['profile_image']) && file_exists(UPLOAD_PATH . 'profiles/' . $member['profile_image'])) {
                unlink(UPLOAD_PATH . 'profiles/' . $member['profile_image']);
            }
            $profile_image = $upload_result['filename'];
        } else {
            $errors[] = "Profile image upload failed: " . $upload_result['message'];
        }
    }

    // If no errors, update database
    if (empty($errors)) {
        $update_query = "UPDATE members SET 
                        first_name = ?, 
                        last_name = ?, 
                        middle_name = ?, 
                        email = ?, 
                        phone = ?, 
                        alternate_phone = ?, 
                        date_of_birth = ?, 
                        gender = ?, 
                        marital_status = ?, 
                        address = ?, 
                        city = ?, 
                        state = ?, 
                        zip_code = ?, 
                        country = ?, 
                        occupation = ?, 
                        membership_status = ?, 
                        membership_date = ?, 
                        baptism_date = ?, 
                        emergency_contact_name = ?, 
                        emergency_contact_phone = ?, 
                        notes = ?,
                        profile_image = ?
                        WHERE member_id = ?";

        $stmt = $db->prepare($update_query);
        
        // FIX: Use NULL for empty dates instead of empty strings
        $stmt->bind_param(
            "ssssssssssssssssssssssi",
            $first_name,
            $last_name,
            $middle_name,
            $email,
            $phone,
            $alternate_phone,
            $date_of_birth,  // Now NULL if empty, not empty string
            $gender,
            $marital_status,
            $address,
            $city,
            $state,
            $zip_code,
            $country,
            $occupation,
            $membership_status,
            $membership_date, // Now NULL if empty, not empty string
            $baptism_date,    // Now NULL if empty, not empty string
            $emergency_contact_name,
            $emergency_contact_phone,
            $notes,
            $profile_image,
            $member_id
        );

        if ($stmt->execute()) {
            setFlashMessage("Member information updated successfully!", "success");
            
            // Log the activity
            if (function_exists('logActivity')) {
                logActivity(getCurrentUserId(), "Updated member", "Member ID: $member_id - $first_name $last_name");
            }
            
            header('Location: member_view.php?id=' . $member_id);
            exit();
        } else {
            $errors[] = "Failed to update member: " . $db->error;
        }
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Set page title
$page_title = "Edit Member - " . $member['first_name'] . ' ' . $member['last_name'];

// Include header
include '../header.php';
?>

<!-- Rest of your HTML remains exactly the same -->
<!-- ... -->

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded-3 shadow-sm">
                <div>
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-user-edit me-3 text-primary"></i>
                        Edit Member
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-user me-2"></i>
                        Editing: <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                        (ID: <?php echo $member['member_id']; ?>)
                    </p>
                </div>
                <div>
                    <a href="member_view.php?id=<?php echo $member_id; ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-eye me-2"></i>View Member
                    </a>
                    <a href="members.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Members
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="memberTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                                <i class="fas fa-user me-2"></i>Personal Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                <i class="fas fa-address-book me-2"></i>Contact Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="church-tab" data-bs-toggle="tab" data-bs-target="#church" type="button" role="tab">
                                <i class="fas fa-church me-2"></i>Church Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="emergency-tab" data-bs-toggle="tab" data-bs-target="#emergency" type="button" role="tab">
                                <i class="fas fa-ambulance me-2"></i>Emergency Contact
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="photo-tab" data-bs-toggle="tab" data-bs-target="#photo" type="button" role="tab">
                                <i class="fas fa-camera me-2"></i>Profile Photo
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <form method="POST" action="" enctype="multipart/form-data" id="editMemberForm">
                        <div class="tab-content" id="memberTabsContent">
                            <!-- Personal Information Tab -->
                            <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                <h5 class="mb-3">Personal Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="first_name" class="form-label fw-bold">
                                            First Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="middle_name" class="form-label fw-bold">Middle Name</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                               value="<?php echo htmlspecialchars($member['middle_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="last_name" class="form-label fw-bold">
                                            Last Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="date_of_birth" class="form-label fw-bold">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo $member['date_of_birth']; ?>">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="gender" class="form-label fw-bold">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo $member['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $member['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo $member['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="marital_status" class="form-label fw-bold">Marital Status</label>
                                        <select class="form-select" id="marital_status" name="marital_status">
                                            <option value="">Select Status</option>
                                            <option value="Single" <?php echo $member['marital_status'] == 'Single' ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo $member['marital_status'] == 'Married' ? 'selected' : ''; ?>>Married</option>
                                            <option value="Divorced" <?php echo $member['marital_status'] == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="Widowed" <?php echo $member['marital_status'] == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="occupation" class="form-label fw-bold">Occupation</label>
                                        <input type="text" class="form-control" id="occupation" name="occupation" 
                                               value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Details Tab -->
                            <div class="tab-pane fade" id="contact" role="tabpanel">
                                <h5 class="mb-3">Contact Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label fw-bold">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label fw-bold">Primary Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="alternate_phone" class="form-label fw-bold">Alternate Phone</label>
                                        <input type="tel" class="form-control" id="alternate_phone" name="alternate_phone" 
                                               value="<?php echo htmlspecialchars($member['alternate_phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="address" class="form-label fw-bold">Street Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($member['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="city" class="form-label fw-bold">City</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($member['city'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="state" class="form-label fw-bold">State</label>
                                        <select class="form-select" id="state" name="state">
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
                                        <label for="zip_code" class="form-label fw-bold">ZIP Code</label>
                                        <input type="text" class="form-control" id="zip_code" name="zip_code" 
                                               value="<?php echo htmlspecialchars($member['zip_code'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="country" class="form-label fw-bold">Country</label>
                                        <input type="text" class="form-control" id="country" name="country" 
                                               value="<?php echo htmlspecialchars($member['country'] ?? 'USA'); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Church Information Tab -->
                            <div class="tab-pane fade" id="church" role="tabpanel">
                                <h5 class="mb-3">Church Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="membership_status" class="form-label fw-bold">Membership Status</label>
                                        <select class="form-select" id="membership_status" name="membership_status">
                                            <option value="Active" <?php echo $member['membership_status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="Inactive" <?php echo $member['membership_status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="Visitor" <?php echo $member['membership_status'] == 'Visitor' ? 'selected' : ''; ?>>Visitor</option>
                                            <option value="Transfer" <?php echo $member['membership_status'] == 'Transfer' ? 'selected' : ''; ?>>Transfer</option>
                                            <option value="Deleted" <?php echo $member['membership_status'] == 'Deleted' ? 'selected' : ''; ?>>Deleted</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="membership_date" class="form-label fw-bold">Membership Date</label>
                                        <input type="date" class="form-control" id="membership_date" name="membership_date" 
                                               value="<?php echo $member['membership_date']; ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="baptism_date" class="form-label fw-bold">Baptism Date</label>
                                        <input type="date" class="form-control" id="baptism_date" name="baptism_date" 
                                               value="<?php echo $member['baptism_date']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Emergency Contact Tab -->
                            <div class="tab-pane fade" id="emergency" role="tabpanel">
                                <h5 class="mb-3">Emergency Contact</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="emergency_contact_name" class="form-label fw-bold">Contact Name</label>
                                        <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                               value="<?php echo htmlspecialchars($member['emergency_contact_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="emergency_contact_phone" class="form-label fw-bold">Contact Phone</label>
                                        <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                               value="<?php echo htmlspecialchars($member['emergency_contact_phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="notes" class="form-label fw-bold">Additional Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($member['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Profile Photo Tab -->
                            <div class="tab-pane fade" id="photo" role="tabpanel">
                                <h5 class="mb-3">Profile Photo</h5>
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <?php if (!empty($member['profile_image']) && file_exists(UPLOAD_PATH . 'profiles/' . $member['profile_image'])): ?>
                                            <img src="../uploads/profiles/<?php echo $member['profile_image']; ?>" 
                                                 alt="Profile" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                                                 style="width: 150px; height: 150px;">
                                                <i class="fas fa-user fa-4x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="profile_image" class="form-label fw-bold">Upload New Photo</label>
                                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                        <small class="text-muted">
                                            Allowed formats: JPG, JPEG, PNG, GIF (Max size: 5MB)
                                        </small>
                                        <?php if (!empty($member['profile_image'])): ?>
                                            <div class="mt-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo">
                                                    <label class="form-check-label text-danger" for="remove_photo">
                                                        Remove current photo
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <a href="member_view.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary px-5" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tab styling */
.nav-tabs .nav-link {
    color: #495057;
    border: none;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    border: none;
    color: var(--bs-primary);
}

.nav-tabs .nav-link.active {
    color: var(--bs-primary);
    background: transparent;
    border-bottom: 3px solid var(--bs-primary);
}

/* Form styling */
.form-label {
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .nav-tabs .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .d-flex {
        flex-direction: column;
    }
}
</style>

<script>
// Form validation
document.getElementById('editMemberForm')?.addEventListener('submit', function(e) {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const submitBtn = document.getElementById('submitBtn');
    
    let isValid = true;
    let errorMessages = [];
    
    if (!firstName) {
        errorMessages.push('First name is required');
        document.getElementById('first_name').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('first_name').classList.remove('is-invalid');
    }
    
    if (!lastName) {
        errorMessages.push('Last name is required');
        document.getElementById('last_name').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('last_name').classList.remove('is-invalid');
    }
    
    if (email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            errorMessages.push('Please enter a valid email address');
            document.getElementById('email').classList.add('is-invalid');
            isValid = false;
        } else {
            document.getElementById('email').classList.remove('is-invalid');
        }
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fix the following errors:\n- ' + errorMessages.join('\n- '));
    } else {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    }
});

// Phone number formatting
document.getElementById('phone')?.addEventListener('input', formatPhone);
document.getElementById('alternate_phone')?.addEventListener('input', formatPhone);
document.getElementById('emergency_contact_phone')?.addEventListener('input', formatPhone);

function formatPhone(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) value = value.slice(0, 10);
    
    if (value.length >= 6) {
        value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6);
    } else if (value.length >= 3) {
        value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
    }
    
    e.target.value = value;
}

// Preview image before upload
document.getElementById('profile_image')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.img-fluid.rounded-circle');
            if (preview) {
                preview.src = e.target.result;
            } else {
                // Create preview if it doesn't exist
                const container = document.querySelector('.col-md-4.text-center');
                if (container) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-fluid rounded-circle mb-3';
                    img.style.width = '150px';
                    img.style.height = '150px';
                    img.style.objectFit = 'cover';
                    
                    // Remove existing content and add new image
                    container.innerHTML = '';
                    container.appendChild(img);
                }
            }
        };
        reader.readAsDataURL(file);
    }
});

// Auto-calculate age when date of birth changes
document.getElementById('date_of_birth')?.addEventListener('change', function() {
    const dob = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    
    // You could display age somewhere if desired
    console.log('Age:', age);
});

// Warn before leaving with unsaved changes
let formChanged = false;
document.querySelectorAll('#editMemberForm input, #editMemberForm select, #editMemberForm textarea').forEach(element => {
    element.addEventListener('change', function() {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php
// Include footer
include '../footer.php';
?>