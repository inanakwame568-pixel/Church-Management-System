<?php
// admin/member_view.php - View Member Details
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

// Get member data with additional statistics
$query = "SELECT m.*,
                 (SELECT COUNT(*) FROM attendance WHERE member_id = m.member_id AND attended = 1) as total_attendance,
                 (SELECT COUNT(*) FROM attendance WHERE member_id = m.member_id AND attended = 1 AND service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_30d,
                 (SELECT SUM(amount) FROM donations WHERE member_id = m.member_id) as total_donations,
                 (SELECT COUNT(*) FROM donations WHERE member_id = m.member_id) as donation_count,
                 (SELECT MAX(donation_date) FROM donations WHERE member_id = m.member_id) as last_donation,
                 (SELECT COUNT(*) FROM group_members gm JOIN `groups` g ON gm.group_id = g.group_id WHERE gm.member_id = m.member_id AND gm.status = 'Active') as group_count,
                 (SELECT GROUP_CONCAT(g.group_name SEPARATOR ', ') FROM group_members gm JOIN `groups` g ON gm.group_id = g.group_id WHERE gm.member_id = m.member_id AND gm.status = 'Active') as group_names,
                 (SELECT COUNT(*) FROM event_registrations WHERE member_id = m.member_id) as event_count
          FROM members m
          WHERE m.member_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage("Member not found!", "danger");
    header('Location: members.php');
    exit();
}

$member = $result->fetch_assoc();

// Get recent attendance
$attendance_query = "SELECT a.*, DAYNAME(a.service_date) as day_name
                     FROM attendance a
                     WHERE a.member_id = ?
                     ORDER BY a.service_date DESC
                     LIMIT 10";
$attendance_stmt = $db->prepare($attendance_query);
$attendance_stmt->bind_param("i", $member_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();

// Get recent donations
$donations_query = "SELECT * FROM donations 
                    WHERE member_id = ? 
                    ORDER BY donation_date DESC 
                    LIMIT 10";
$donations_stmt = $db->prepare($donations_query);
$donations_stmt->bind_param("i", $member_id);
$donations_stmt->execute();
$donations_result = $donations_stmt->get_result();

// Get family members
$family_query = "SELECT m2.*, mf.relationship 
                 FROM member_family mf
                 JOIN members m2 ON mf.member_id = m2.member_id
                 WHERE mf.family_id = (SELECT family_id FROM member_family WHERE member_id = ? LIMIT 1)
                 AND m2.member_id != ?";
$family_stmt = $db->prepare($family_query);
$family_stmt->bind_param("ii", $member_id, $member_id);
$family_stmt->execute();
$family_result = $family_stmt->get_result();

// Set page title
$page_title = "Member Profile - " . $member['first_name'] . ' ' . $member['last_name'];

// Include header
include '../header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header with Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded-3 shadow-sm">
                <div>
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-user-circle me-3 text-primary"></i>
                        Member Profile
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-id-card me-2"></i>
                        Member ID: <strong>#<?php echo $member['member_id']; ?></strong> | 
                        Joined: <strong><?php echo $member['membership_date'] ? date('F d, Y', strtotime($member['membership_date'])) : 'N/A'; ?></strong>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="member_edit.php?id=<?php echo $member_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Member
                    </a>
                    <a href="members.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Members
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Profile -->
    <div class="row g-4">
        <!-- Left Column - Profile Photo & Basic Info -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center p-4">
                    <!-- Profile Photo -->
                    <div class="mb-4">
                        <?php if (!empty($member['profile_image']) && file_exists(UPLOAD_PATH . 'profiles/' . $member['profile_image'])): ?>
                            <img src="../uploads/profiles/<?php echo $member['profile_image']; ?>" 
                                 alt="Profile" class="rounded-circle img-fluid border border-3 border-primary" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto border border-3 border-primary" 
                                 style="width: 150px; height: 150px;">
                                <i class="fas fa-user fa-4x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Member Name & Status -->
                    <h2 class="fw-bold mb-1">
                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                    </h2>
                    <?php if (!empty($member['middle_name'])): ?>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($member['middle_name']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Status Badge -->
                    <div class="mb-3">
                        <?php
                        $status_class = 'success';
                        if ($member['membership_status'] == 'Inactive') $status_class = 'warning';
                        if ($member['membership_status'] == 'Visitor') $status_class = 'info';
                        if ($member['membership_status'] == 'Transfer') $status_class = 'primary';
                        if ($member['membership_status'] == 'Deleted') $status_class = 'danger';
                        ?>
                        <span class="badge bg-<?php echo $status_class; ?> p-2 px-3">
                            <i class="fas fa-circle me-1 small"></i>
                            <?php echo $member['membership_status']; ?>
                        </span>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="bg-light rounded-3 p-2">
                                <div class="small text-muted">Age</div>
                                <div class="fw-bold"><?php echo $member['date_of_birth'] ? calculateAge($member['date_of_birth']) : '—'; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded-3 p-2">
                                <div class="small text-muted">Gender</div>
                                <div class="fw-bold"><?php echo $member['gender'] ?: '—'; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded-3 p-2">
                                <div class="small text-muted">Groups</div>
                                <div class="fw-bold"><?php echo $member['group_count']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Info Quick View -->
                    <div class="text-start">
                        <?php if (!empty($member['email'])): ?>
                            <p class="mb-2">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <a href="mailto:<?php echo $member['email']; ?>" class="text-decoration-none">
                                    <?php echo $member['email']; ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($member['phone'])): ?>
                            <p class="mb-2">
                                <i class="fas fa-phone text-success me-2"></i>
                                <a href="tel:<?php echo $member['phone']; ?>" class="text-decoration-none">
                                    <?php echo formatPhone($member['phone']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($member['address'])): ?>
                            <p class="mb-2">
                                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                <?php 
                                echo htmlspecialchars($member['address']);
                                if (!empty($member['city']) || !empty($member['state'])) {
                                    echo '<br>' . htmlspecialchars($member['city'] ?? '');
                                    if (!empty($member['city']) && !empty($member['state'])) echo ', ';
                                    echo htmlspecialchars($member['state'] ?? '');
                                    if (!empty($member['zip_code'])) echo ' ' . $member['zip_code'];
                                }
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact Card -->
            <?php if (!empty($member['emergency_contact_name']) || !empty($member['emergency_contact_phone'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-ambulance me-2 text-danger"></i>
                        Emergency Contact
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($member['emergency_contact_name'])): ?>
                        <p class="mb-2">
                            <i class="fas fa-user text-muted me-2"></i>
                            <strong><?php echo htmlspecialchars($member['emergency_contact_name']); ?></strong>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($member['emergency_contact_phone'])): ?>
                        <p class="mb-0">
                            <i class="fas fa-phone text-muted me-2"></i>
                            <a href="tel:<?php echo $member['emergency_contact_phone']; ?>" class="text-decoration-none">
                                <?php echo formatPhone($member['emergency_contact_phone']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column - Detailed Information -->
        <div class="col-lg-8">
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card bg-primary bg-opacity-10 border-0">
                        <div class="card-body text-center p-3">
                            <h3 class="fw-bold text-primary mb-1"><?php echo $member['total_attendance']; ?></h3>
                            <small class="text-muted">Total Attendance</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-success bg-opacity-10 border-0">
                        <div class="card-body text-center p-3">
                            <h3 class="fw-bold text-success mb-1"><?php echo $member['attendance_30d']; ?></h3>
                            <small class="text-muted">Last 30 Days</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-warning bg-opacity-10 border-0">
                        <div class="card-body text-center p-3">
                            <h3 class="fw-bold text-warning mb-1"><?php echo $member['donation_count']; ?></h3>
                            <small class="text-muted">Donations</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-info bg-opacity-10 border-0">
                        <div class="card-body text-center p-3">
                            <h3 class="fw-bold text-info mb-1"><?php echo $member['event_count']; ?></h3>
                            <small class="text-muted">Events</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-user me-2 text-primary"></i>
                        Personal Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-muted small">Full Name</label>
                            <p class="fw-bold mb-0">
                                <?php 
                                $full_name = $member['first_name'];
                                if (!empty($member['middle_name'])) $full_name .= ' ' . $member['middle_name'];
                                $full_name .= ' ' . $member['last_name'];
                                echo htmlspecialchars($full_name);
                                ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Date of Birth</label>
                            <p class="fw-bold mb-0">
                                <?php echo $member['date_of_birth'] ? date('F d, Y', strtotime($member['date_of_birth'])) : '—'; ?>
                                <?php if ($member['date_of_birth']): ?>
                                    <span class="text-muted">(<?php echo calculateAge($member['date_of_birth']); ?> years)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Gender</label>
                            <p class="fw-bold mb-0"><?php echo $member['gender'] ?: '—'; ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Marital Status</label>
                            <p class="fw-bold mb-0"><?php echo $member['marital_status'] ?: '—'; ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Occupation</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($member['occupation'] ?: '—'); ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Baptism Date</label>
                            <p class="fw-bold mb-0"><?php echo $member['baptism_date'] ? date('F d, Y', strtotime($member['baptism_date'])) : '—'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Church Groups -->
            <?php if ($member['group_count'] > 0): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-users me-2 text-success"></i>
                        Church Groups (<?php echo $member['group_count']; ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo htmlspecialchars($member['group_names']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Family Members -->
            <?php if ($family_result->num_rows > 0): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-family me-2 text-info"></i>
                        Family Members
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php while ($family = $family_result->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-circle fa-2x text-muted me-3"></i>
                                <div>
                                    <a href="member_view.php?id=<?php echo $family['member_id']; ?>" class="fw-bold text-decoration-none">
                                        <?php echo htmlspecialchars($family['first_name'] . ' ' . $family['last_name']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo $family['relationship'] ?: 'Family Member'; ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Attendance -->
            <?php if ($attendance_result->num_rows > 0): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-calendar-check me-2 text-success"></i>
                        Recent Attendance
                    </h5>
                    <a href="attendance.php?member=<?php echo $member_id; ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Service</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($attendance = $attendance_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($attendance['service_date'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo $attendance['day_name']; ?></small>
                                    </td>
                                    <td><?php echo $attendance['service_type']; ?></td>
                                    <td>
                                        <?php echo $attendance['check_in_time'] ? date('g:i A', strtotime($attendance['check_in_time'])) : '—'; ?>
                                    </td>
                                    <td>
                                        <?php if ($attendance['attended']): ?>
                                            <span class="badge bg-success">Present</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Absent</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Donations -->
            <?php if ($donations_result->num_rows > 0): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-hand-holding-heart me-2 text-warning"></i>
                        Recent Donations
                    </h5>
                    <a href="donations.php?member=<?php echo $member_id; ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Fund</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($donation = $donations_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                    <td class="fw-bold text-success"><?php echo formatCurrency($donation['amount']); ?></td>
                                    <td><?php echo $donation['fund_type']; ?></td>
                                    <td><?php echo $donation['payment_method']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="2" class="text-end"><strong>Total Donations:</strong></td>
                                    <td colspan="2"><strong><?php echo formatCurrency($member['total_donations']); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($member['notes'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-sticky-note me-2 text-secondary"></i>
                        Notes
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['notes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Profile page styling */
.card {
    border-radius: 12px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
}

.table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .display-6 {
        font-size: 1.5rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
        width: 100%;
    }
}

/* Custom animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}

/* Stats cards */
.bg-opacity-10 {
    transition: transform 0.3s ease;
}

.bg-opacity-10:hover {
    transform: translateY(-3px);
}
</style>

<script>
// Print member profile
function printProfile() {
    window.print();
}

// Export as PDF (you can implement with jsPDF)
function exportToPDF() {
    alert('PDF export feature coming soon!');
}

// Send email to member
function sendEmail() {
    const email = '<?php echo $member['email']; ?>';
    if (email) {
        window.location.href = 'mailto:' + email;
    } else {
        alert('No email address available for this member.');
    }
}

// Call member
function callMember() {
    const phone = '<?php echo $member['phone']; ?>';
    if (phone) {
        window.location.href = 'tel:' + phone;
    } else {
        alert('No phone number available for this member.');
    }
}

// Quick actions dropdown
document.addEventListener('DOMContentLoaded', function() {
    // Add quick actions button if needed
    const header = document.querySelector('.display-6');
    if (header) {
        // You can add additional JavaScript functionality here
    }
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<!-- Floating Action Button for Quick Actions -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 100;">
    <div class="dropdown">
        <button class="btn btn-primary rounded-circle shadow-lg" type="button" data-bs-toggle="dropdown" style="width: 60px; height: 60px;">
            <i class="fas fa-bolt"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="member_edit.php?id=<?php echo $member_id; ?>">
                <i class="fas fa-edit me-2 text-primary"></i>Edit Member
            </a></li>
            <li><a class="dropdown-item" href="attendance.php?member=<?php echo $member_id; ?>">
                <i class="fas fa-calendar-check me-2 text-success"></i>View Attendance
            </a></li>
            <li><a class="dropdown-item" href="donations.php?member=<?php echo $member_id; ?>">
                <i class="fas fa-hand-holding-heart me-2 text-warning"></i>View Donations
            </a></li>
            <?php if (!empty($member['email'])): ?>
            <li><a class="dropdown-item" href="mailto:<?php echo $member['email']; ?>">
                <i class="fas fa-envelope me-2 text-info"></i>Send Email
            </a></li>
            <?php endif; ?>
            <?php if (!empty($member['phone'])): ?>
            <li><a class="dropdown-item" href="tel:<?php echo $member['phone']; ?>">
                <i class="fas fa-phone me-2 text-success"></i>Call Member
            </a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" onclick="printProfile()">
                <i class="fas fa-print me-2 text-secondary"></i>Print Profile
            </a></li>
        </ul>
    </div>
</div>

<?php
// Include footer
include '../footer.php';
?>