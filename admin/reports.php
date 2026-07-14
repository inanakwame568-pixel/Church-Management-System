<?php
// admin/reports.php - Comprehensive Reports Page
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current user info
$user_id = getCurrentUserId();
$user_role = getCurrentUserRole();

// Check permissions (optional - restrict certain reports)
$can_view_financial = isAdmin() || hasRole('pastor') || hasRole('secretary');

// Get report type from URL
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'members';
$date_range = isset($_GET['date_range']) ? sanitize($_GET['date_range']) : 'month';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-t');
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'html';

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    exportReportCSV($report_type, $start_date, $end_date);
    exit();
}

// Set page title
$page_title = "Reports - " . ucfirst($report_type);

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
                        <i class="fas fa-chart-bar me-3 text-primary"></i>
                        Reports
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-file-alt me-2"></i>Generate and export church reports
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <a href="?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" 
                       class="btn btn-success">
                        <i class="fas fa-file-csv me-2"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Navigation Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <ul class="nav nav-pills nav-fill">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'members' ? 'active' : ''; ?>" 
                               href="?type=members&date_range=<?php echo $date_range; ?>">
                                <i class="fas fa-users me-2"></i>Membership Report
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'attendance' ? 'active' : ''; ?>" 
                               href="?type=attendance&date_range=<?php echo $date_range; ?>">
                                <i class="fas fa-calendar-check me-2"></i>Attendance Report
                            </a>
                        </li>
                        <?php if ($can_view_financial): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'donations' ? 'active' : ''; ?>" 
                               href="?type=donations&date_range=<?php echo $date_range; ?>">
                                <i class="fas fa-hand-holding-heart me-2"></i>Financial Report
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'events' ? 'active' : ''; ?>" 
                               href="?type=events&date_range=<?php echo $date_range; ?>">
                                <i class="fas fa-calendar-alt me-2"></i>Events Report
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'groups' ? 'active' : ''; ?>" 
                               href="?type=groups&date_range=<?php echo $date_range; ?>">
                                <i class="fas fa-users-cog me-2"></i>Groups Report
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'birthdays' ? 'active' : ''; ?>" 
                               href="?type=birthdays&date_range=<?php echo $date_range; ?>">
                                <i class="fas fa-birthday-cake me-2"></i>Birthdays
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Quick Date Range</label>
                            <select name="date_range" class="form-select" onchange="this.form.submit()">
                                <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $date_range == 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $date_range == 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="quarter" <?php echo $date_range == 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                <option value="year" <?php echo $date_range == 'year' ? 'selected' : ''; ?>>This Year</option>
                                <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo $start_date; ?>" 
                                   <?php echo $date_range != 'custom' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo $end_date; ?>"
                                   <?php echo $date_range != 'custom' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-file-alt me-2 text-primary"></i>
                        <?php echo ucfirst($report_type); ?> Report
                        <small class="text-muted ms-2"><?php echo date('F d, Y', strtotime($start_date)); ?> - <?php echo date('F d, Y', strtotime($end_date)); ?></small>
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Load the appropriate report based on type
                    switch ($report_type) {
                        case 'members':
                            include 'reports/members_report.php';
                            break;
                        case 'attendance':
                            include 'reports/attendance_report.php';
                            break;
                        case 'donations':
                            if ($can_view_financial) {
                                include 'reports/donations_report.php';
                            } else {
                                echo '<div class="alert alert-danger">You do not have permission to view financial reports.</div>';
                            }
                            break;
                        case 'events':
                            include 'reports/events_report.php';
                            break;
                        case 'groups':
                            include 'reports/groups_report.php';
                            break;
                        case 'birthdays':
                            include 'reports/birthdays_report.php';
                            break;
                        default:
                            include 'reports/members_report.php';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Report specific styles */
@media print {
    .navbar-custom, .footer, .btn, .nav-pills, .card-header .btn, form {
        display: none !important;
    }
    
    body {
        padding-top: 0;
        background: white;
    }
    
    .main-container {
        max-width: 100%;
        padding: 0;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .table {
        font-size: 12pt;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: transparent !important;
    }
}

/* Chart containers */
.chart-container {
    position: relative;
    height: 300px;
    margin-bottom: 30px;
}

/* Summary cards */
.summary-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.summary-card .number {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--bs-primary);
    line-height: 1.2;
}

.summary-card .label {
    font-size: 0.9rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Report tables */
.report-table {
    font-size: 0.9rem;
}

.report-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    white-space: nowrap;
}

.report-table td {
    vertical-align: middle;
}

/* Totals row */
.table-total {
    background-color: #e9ecef;
    font-weight: bold;
}

/* Responsive */
@media (max-width: 768px) {
    .summary-card .number {
        font-size: 1.8rem;
    }
    
    .nav-pills {
        flex-wrap: wrap;
    }
    
    .nav-pills .nav-item {
        width: 50%;
        margin-bottom: 5px;
    }
}
</style>

<script>
// Auto-submit when date range changes
document.querySelector('select[name="date_range"]').addEventListener('change', function() {
    if (this.value === 'custom') {
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.disabled = false;
        });
    } else {
        this.form.submit();
    }
});

// Print functionality
function printReport() {
    window.print();
}

// Export to PDF (optional - requires jsPDF)
function exportToPDF() {
    // You can implement PDF export here using jsPDF or similar library
    alert('PDF export feature coming soon!');
}

// Chart initialization for different report types
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts based on report type
    <?php if ($report_type == 'attendance'): ?>
    // Attendance trend chart
    const ctx = document.getElementById('attendanceTrendChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels ?? []); ?>,
                datasets: [{
                    label: 'Attendance',
                    data: <?php echo json_encode($chart_data ?? []); ?>,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if ($report_type == 'donations'): ?>
    // Donation pie chart
    const ctx2 = document.getElementById('donationPieChart');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($pie_labels ?? ['Tithe', 'Offering', 'Building', 'Missions']); ?>,
                datasets: [{
                    data: <?php echo json_encode($pie_data ?? [0,0,0,0]); ?>,
                    backgroundColor: ['#4361ee', '#f59e0b', '#10b981', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    <?php endif; ?>
});

// Show/hide columns in table
function toggleColumn(columnIndex) {
    const table = document.querySelector('.report-table');
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cells = row.children;
        if (cells[columnIndex]) {
            if (cells[columnIndex].style.display === 'none') {
                cells[columnIndex].style.display = '';
            } else {
                cells[columnIndex].style.display = 'none';
            }
        }
    });
}

// Search in table
function searchTable() {
    const input = document.getElementById('tableSearch');
    const filter = input.value.toUpperCase();
    const table = document.querySelector('.report-table');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toUpperCase();
        if (text.indexOf(filter) > -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php
// Include footer
include '../footer.php';

/**
 * Export report as CSV
 */
function exportReportCSV($type, $start_date, $end_date) {
    global $db;
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($type) {
        case 'members':
            // Members report CSV
            fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Status', 'Joined Date', 'Birthday', 'Address']);
            
            $query = "SELECT member_id, first_name, last_name, email, phone, membership_status, 
                             membership_date, date_of_birth, address 
                      FROM members 
                      ORDER BY membership_date DESC";
            $result = $db->query($query);
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['member_id'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['phone'],
                    $row['membership_status'],
                    $row['membership_date'],
                    $row['date_of_birth'],
                    $row['address']
                ]);
            }
            break;
            
        case 'attendance':
            // Attendance report CSV
            fputcsv($output, ['Date', 'Member', 'Service Type', 'Time', 'Status']);
            
            $query = "SELECT a.service_date, CONCAT(m.first_name, ' ', m.last_name) as member_name,
                             a.service_type, a.check_in_time, a.attended
                      FROM attendance a
                      JOIN members m ON a.member_id = m.member_id
                      WHERE a.service_date BETWEEN ? AND ?
                      ORDER BY a.service_date DESC, a.service_type";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['service_date'],
                    $row['member_name'],
                    $row['service_type'],
                    $row['check_in_time'],
                    $row['attended'] ? 'Present' : 'Absent'
                ]);
            }
            break;
            
        case 'donations':
            // Donations report CSV
            fputcsv($output, ['Date', 'Member', 'Amount', 'Method', 'Fund', 'Notes']);
            
            $query = "SELECT d.donation_date, CONCAT(m.first_name, ' ', m.last_name) as member_name,
                             d.amount, d.payment_method, d.fund_type, d.notes
                      FROM donations d
                      JOIN members m ON d.member_id = m.member_id
                      WHERE d.donation_date BETWEEN ? AND ?
                      ORDER BY d.donation_date DESC";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['donation_date'],
                    $row['member_name'],
                    $row['amount'],
                    $row['payment_method'],
                    $row['fund_type'],
                    $row['notes']
                ]);
            }
            break;
            
        case 'events':
            // Events report CSV
            fputcsv($output, ['Event', 'Date', 'Time', 'Location', 'Organizer', 'Registrations']);
            
            $query = "SELECT event_name, event_date, event_time, location, organizer,
                             (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrations
                      FROM events e
                      WHERE e.event_date BETWEEN ? AND ?
                      ORDER BY e.event_date";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['event_name'],
                    $row['event_date'],
                    $row['event_time'],
                    $row['location'],
                    $row['organizer'],
                    $row['registrations']
                ]);
            }
            break;
            
        case 'birthdays':
            // Birthdays report CSV
            fputcsv($output, ['Name', 'Birthday', 'Age', 'Phone', 'Email']);
            
            $query = "SELECT first_name, last_name, date_of_birth, phone, email,
                             TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
                      FROM members 
                      WHERE MONTH(date_of_birth) BETWEEN ? AND ?
                      ORDER BY MONTH(date_of_birth), DAY(date_of_birth)";
            
            // Convert dates to months for birthday filtering
            $start_month = date('m', strtotime($start_date));
            $end_month = date('m', strtotime($end_date));
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ii", $start_month, $end_month);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['first_name'] . ' ' . $row['last_name'],
                    date('F j', strtotime($row['date_of_birth'])),
                    $row['age'],
                    $row['phone'],
                    $row['email']
                ]);
            }
            break;
    }
    
    fclose($output);
    exit();
}
?>