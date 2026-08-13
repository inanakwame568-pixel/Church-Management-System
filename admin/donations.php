<?php
// admin/donations.php - Donations Management Page
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Handle donation deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $donation_id = (int)$_GET['delete'];
    
    $check_stmt = $db->prepare("SELECT donation_id, amount, member_id FROM donations WHERE donation_id = ?");
    $check_stmt->bind_param("i", $donation_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $delete_stmt = $db->prepare("DELETE FROM donations WHERE donation_id = ?");
        $delete_stmt->bind_param("i", $donation_id);
        
        if ($delete_stmt->execute()) {
            setFlashMessage("Donation record deleted successfully!", "success");
        } else {
            setFlashMessage("Error deleting donation record!", "danger");
        }
    } else {
        setFlashMessage("Donation record not found!", "warning");
    }
    
    header('Location: donations.php');
    exit();
}

// Handle donation recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_donation'])) {
    $member_id = (int)$_POST['member_id'];
    $amount = (float)$_POST['amount'];
    $donation_date = $_POST['donation_date'];
    $payment_method = $_POST['payment_method'];
    $fund_type = $_POST['fund_type'];
    $notes = sanitize($_POST['notes'] ?? '');
    $transaction_id = sanitize($_POST['transaction_id'] ?? '');
    
    $errors = [];
    
    if ($member_id <= 0) {
        $errors[] = "Please select a member.";
    }
    
    if ($amount <= 0) {
        $errors[] = "Please enter a valid donation amount.";
    }
    
    if (empty($donation_date)) {
        $errors[] = "Please select a donation date.";
    }
    
    if (empty($payment_method)) {
        $errors[] = "Please select a payment method.";
    }
    
    if (empty($fund_type)) {
        $errors[] = "Please select a fund type.";
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO donations (member_id, amount, donation_date, payment_method, fund_type, notes, transaction_id, recorded_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $user_id = getCurrentUserId();
        $stmt->bind_param("idsssssi", $member_id, $amount, $donation_date, $payment_method, $fund_type, $notes, $transaction_id, $user_id);
        
        if ($stmt->execute()) {
            setFlashMessage("Donation recorded successfully!", "success");
            header('Location: donations.php');
            exit();
        } else {
            $error = "Failed to record donation: " . $db->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$fund_filter = isset($_GET['fund']) ? trim($_GET['fund']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Build the query
$query = "SELECT d.*, 
                 CONCAT(m.first_name, ' ', m.last_name) as member_name,
                 u.full_name as recorded_by_name
          FROM donations d
          JOIN members m ON d.member_id = m.member_id
          LEFT JOIN users u ON d.recorded_by = u.user_id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM donations d JOIN members m ON d.member_id = m.member_id WHERE 1=1";
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $query .= " AND (CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR m.email LIKE ? OR d.transaction_id LIKE ?)";
    $count_query .= " AND (CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR m.email LIKE ? OR d.transaction_id LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

// Add fund filter
if (!empty($fund_filter)) {
    $query .= " AND d.fund_type = ?";
    $count_query .= " AND d.fund_type = ?";
    $params[] = $fund_filter;
    $types .= "s";
}

// Add date range filters
if (!empty($date_from)) {
    $query .= " AND d.donation_date >= ?";
    $count_query .= " AND d.donation_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND d.donation_date <= ?";
    $count_query .= " AND d.donation_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY d.donation_date ASC, d.donation_id ASC";
        break;
    case 'highest':
        $query .= " ORDER BY d.amount DESC";
        break;
    case 'lowest':
        $query .= " ORDER BY d.amount ASC";
        break;
    default: // latest
        $query .= " ORDER BY d.donation_date DESC, d.donation_id DESC";
}

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute
$stmt = $db->prepare($query);
if (!$stmt) {
    die("Error preparing query: " . $db->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$donations = [];
while ($row = $result->fetch_assoc()) {
    $donations[] = $row;
}

// Get total records
$count_stmt = $db->prepare($count_query);
if (!$count_stmt) {
    die("Error preparing count query: " . $db->error);
}

if (!empty($params)) {
    // Remove limit and offset parameters for count
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}

$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get members for dropdown
$members = $db->query("SELECT member_id, first_name, last_name FROM members WHERE membership_status = 'Active' ORDER BY last_name, first_name");

// Get fund types for filter
$funds = $db->query("SELECT DISTINCT fund_type FROM donations WHERE fund_type IS NOT NULL ORDER BY fund_type");

// Get summary statistics
$summary = [];

// Total donations
$summary['total_amount'] = $db->query("SELECT SUM(amount) as total FROM donations")->fetch_assoc()['total'] ?? 0;

// Total donations this month
$summary['monthly_total'] = $db->query("SELECT SUM(amount) as total FROM donations WHERE MONTH(donation_date) = MONTH(CURDATE()) AND YEAR(donation_date) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;

// Total donations this year
$summary['yearly_total'] = $db->query("SELECT SUM(amount) as total FROM donations WHERE YEAR(donation_date) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;

// Total number of donations
$summary['total_count'] = $db->query("SELECT COUNT(*) as count FROM donations")->fetch_assoc()['count'];

// Donations by fund type
$fund_summary = $db->query("SELECT fund_type, SUM(amount) as total, COUNT(*) as count FROM donations GROUP BY fund_type ORDER BY total DESC");

// Set page title
$page_title = "Donations Management";

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
                        <i class="fas fa-hand-holding-heart me-3 text-warning"></i>
                        Donations Management
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-coins me-2"></i>
                        Total Donations: <?php echo $summary['total_count']; ?> | 
                        Total Amount: <?php echo formatCurrency($summary['total_amount']); ?>
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDonationModal">
                        <i class="fas fa-plus-circle me-2"></i>Record Donation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-coins text-success fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total Donations</h6>
                            <h3 class="mb-0 fw-bold text-success"><?php echo formatCurrency($summary['total_amount']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-calendar-alt text-primary fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">This Month</h6>
                            <h3 class="mb-0 fw-bold"><?php echo formatCurrency($summary['monthly_total']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-chart-line text-info fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">This Year</h6>
                            <h3 class="mb-0 fw-bold"><?php echo formatCurrency($summary['yearly_total']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-users text-warning fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total Gifts</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($summary['total_count']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control border-start-0 ps-0" 
                                       name="search" 
                                       placeholder="Search member, email, transaction..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="fund" class="form-select">
                                <option value="">All Funds</option>
                                <?php while ($fund = $funds->fetch_assoc()): ?>
                                    <option value="<?php echo $fund['fund_type']; ?>" <?php echo $fund_filter == $fund['fund_type'] ? 'selected' : ''; ?>>
                                        <?php echo $fund['fund_type']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control" placeholder="Date From" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control" placeholder="Date To" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <select name="sort" class="form-select">
                                <option value="latest" <?php echo $sort == 'latest' ? 'selected' : ''; ?>>Latest First</option>
                                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="highest" <?php echo $sort == 'highest' ? 'selected' : ''; ?>>Highest Amount</option>
                                <option value="lowest" <?php echo $sort == 'lowest' ? 'selected' : ''; ?>>Lowest Amount</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Donations Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($donations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-hand-holding-heart fa-4x text-muted mb-3"></i>
                            <h5>No donations found</h5>
                            <p class="text-muted">Try adjusting your filters or record a new donation</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addDonationModal">
                                <i class="fas fa-plus-circle me-2"></i>Record Your First Donation
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Fund</th>
                                        <th>Payment Method</th>
                                        <th>Transaction ID</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                            <td>
                                                <a href="member_view.php?id=<?php echo $donation['member_id']; ?>" class="text-decoration-none fw-bold">
                                                    <?php echo htmlspecialchars($donation['member_name']); ?>
                                                </a>
                                            </td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency($donation['amount']); ?></td>
                                            <td>
                                                <span class="badge bg-info bg-opacity-10 text-info">
                                                    <?php echo htmlspecialchars($donation['fund_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['payment_method']); ?></td>
                                            <td>
                                                <?php if (!empty($donation['transaction_id'])): ?>
                                                    <code><?php echo htmlspecialchars($donation['transaction_id']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['recorded_by_name'] ?? 'System'); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="donation_receipt.php?id=<?php echo $donation['donation_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="View Receipt"
                                                       target="_blank">
                                                        <i class="fas fa-receipt"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $donation['donation_id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       title="Delete"
                                                       onclick="return confirm('Delete this donation record?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-total">
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Totals:</strong></td>
                                        <td class="fw-bold text-success">
                                            <?php 
                                            $total_amount = 0;
                                            foreach ($donations as $d) {
                                                $total_amount += $d['amount'];
                                            }
                                            echo formatCurrency($total_amount);
                                            ?>
                                        </td>
                                        <td colspan="5"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white border-0 py-3">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&fund=<?php echo urlencode($fund_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&fund=<?php echo urlencode($fund_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&fund=<?php echo urlencode($fund_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Fund Summary -->
    <?php if ($fund_summary->num_rows > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>
                        Donations by Fund Type
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Fund Type</th>
                                    <th>Number of Gifts</th>
                                    <th>Total Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($fund = $fund_summary->fetch_assoc()): 
                                    $percentage = $summary['total_amount'] > 0 ? round(($fund['total'] / $summary['total_amount']) * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($fund['fund_type']); ?></strong></td>
                                    <td><?php echo number_format($fund['count']); ?></td>
                                    <td class="fw-bold text-success"><?php echo formatCurrency($fund['total']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar bg-<?php 
                                                    echo $percentage >= 50 ? 'success' : ($percentage >= 25 ? 'warning' : 'info'); 
                                                ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <span class="ms-2 small"><?php echo $percentage; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Donation Modal -->
<div class="modal fade" id="addDonationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-hand-holding-heart me-2"></i>
                    Record New Donation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="donationForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Member *</label>
                            <select name="member_id" class="form-select" required>
                                <option value="">Select Member</option>
                                <?php 
                                $members->data_seek(0);
                                while ($member = $members->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo htmlspecialchars($member['last_name'] . ', ' . $member['first_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">$</span>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Donation Date *</label>
                            <input type="date" name="donation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Payment Method *</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="">Select Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Online">Online</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Fund Type *</label>
                            <select name="fund_type" class="form-select" required>
                                <option value="">Select Fund</option>
                                <option value="Tithe">Tithe</option>
                                <option value="Offering">Offering</option>
                                <option value="Building Fund">Building Fund</option>
                                <option value="Missions">Missions</option>
                                <option value="Benevolence">Benevolence</option>
                                <option value="Youth Ministry">Youth Ministry</option>
                                <option value="Children's Ministry">Children's Ministry</option>
                                <option value="Worship & Music">Worship & Music</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Transaction ID</label>
                            <input type="text" name="transaction_id" class="form-control" placeholder="Optional reference number">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any additional information..."></textarea>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" name="add_donation" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Record Donation
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.stats-card {
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15) !important;
}

.stats-icon {
    width: 55px;
    height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.table td {
    vertical-align: middle;
}

.table-total td {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 12px 15px;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.btn-group .btn i {
    font-size: 0.875rem;
}

/* Progress bar */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

/* Responsive */
@media (max-width: 768px) {
    .table {
        font-size: 0.875rem;
    }
    
    .btn-group .btn {
        padding: 0.2rem 0.4rem;
    }
}
</style>

<script>
// Form validation
document.getElementById('donationForm').addEventListener('submit', function(e) {
    const amount = document.querySelector('input[name="amount"]').value;
    const member = document.querySelector('select[name="member_id"]').value;
    const method = document.querySelector('select[name="payment_method"]').value;
    const fund = document.querySelector('select[name="fund_type"]').value;
    
    if (!member) {
        e.preventDefault();
        alert('Please select a member.');
        return;
    }
    
    if (!amount || parseFloat(amount) <= 0) {
        e.preventDefault();
        alert('Please enter a valid donation amount.');
        return;
    }
    
    if (!method) {
        e.preventDefault();
        alert('Please select a payment method.');
        return;
    }
    
    if (!fund) {
        e.preventDefault();
        alert('Please select a fund type.');
        return;
    }
});

// Auto-submit on filter change
document.querySelector('select[name="fund"]').addEventListener('change', function() {
    this.form.submit();
});

document.querySelector('select[name="sort"]').addEventListener('change', function() {
    this.form.submit();
});

// Live search with debounce
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});

// Format currency in input
document.querySelector('input[name="amount"]').addEventListener('blur', function() {
    const value = parseFloat(this.value);
    if (!isNaN(value) && value > 0) {
        this.value = value.toFixed(2);
    }
});

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php include '../footer.php'; ?>