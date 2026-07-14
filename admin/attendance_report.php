<?php
// admin/attendance_report.php - Attendance Report Generator
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current user role
$user_role = getCurrentUserRole();

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';
$service_type = isset($_GET['service_type']) ? $_GET['service_type'] : 'all';
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Handle CSV export
if ($format == 'csv') {
    exportAttendanceCSV($start_date, $end_date, $service_type, $member_id);
    exit();
}

// Get attendance statistics
$stats = getAttendanceStats($start_date, $end_date, $service_type);

// Get attendance data
$attendance_data = getAttendanceData($start_date, $end_date, $service_type, $member_id);

// Get service types for filter
$service_types_result = $db->query("SELECT DISTINCT service_type FROM attendance WHERE service_type IS NOT NULL ORDER BY service_type");
$service_types = [];
while ($row = $service_types_result->fetch_assoc()) {
    $service_types[] = $row['service_type'];
}

// Get members for filter
$members_result = $db->query("SELECT member_id, first_name, last_name FROM members WHERE membership_status = 'Active' ORDER BY last_name, first_name");

// Get monthly trends
$monthly_trends = getMonthlyAttendanceTrends($start_date, $end_date);

// Get top attendees
$top_attendees = getTopAttendees($start_date, $end_date);

// Get service type distribution
$service_distribution = getServiceDistribution($start_date, $end_date);

// Set page title
$page_title = "Attendance Report";

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
                        <i class="fas fa-chart-line me-3 text-primary"></i>
                        Attendance Report
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Report Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&service_type=<?php echo $service_type; ?>&member_id=<?php echo $member_id; ?>&format=csv" 
                       class="btn btn-success">
                        <i class="fas fa-file-csv me-2"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Service Type</label>
                            <select name="service_type" class="form-select">
                                <option value="all">All Services</option>
                                <?php foreach ($service_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $service_type == $type ? 'selected' : ''; ?>>
                                        <?php echo $type; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Member</label>
                            <select name="member_id" class="form-select">
                                <option value="0">All Members</option>
                                <?php while ($member = $members_result->fetch_assoc()): ?>
                                    <option value="<?php echo $member['member_id']; ?>" <?php echo $member_id == $member['member_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['last_name'] . ', ' . $member['first_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-chart-line me-2"></i>Generate Report
                            </button>
                        </div>
                    </form>
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
                        <div class="stats-icon bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-users text-primary fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total Attendance</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['total_attendance']); ?></h3>
                            <small class="text-muted">Total records</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-calendar-week text-success fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total Services</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['total_services']); ?></h3>
                            <small class="text-muted">Services held</small>
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
                            <i class="fas fa-chart-line text-warning fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Average Attendance</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['avg_attendance']); ?></h3>
                            <small class="text-muted">Per service</small>
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
                            <i class="fas fa-user-check text-info fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Unique Attendees</h6>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($stats['unique_attendees']); ?></h3>
                            <small class="text-muted">Distinct members</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-line me-2 text-primary"></i>
                        Daily Attendance Trend
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="attendanceTrendChart" style="height: 350px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-pie me-2 text-success"></i>
                        Service Type Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="serviceTypeChart" style="height: 250px;"></canvas>
                    <div class="mt-3">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr><th>Service Type</th><th>Attendance</th><th>%</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($service_distribution as $dist): ?>
                                <tr>
                                    <td><?php echo $dist['service_type']; ?></td>
                                    <td class="text-end"><?php echo number_format($dist['count']); ?></td>
                                    <td class="text-end"><?php echo round($dist['percentage']); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Attendees Section -->
    <?php if (!empty($top_attendees)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-trophy me-2 text-warning"></i>
                        Top Attendees
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Member Name</th>
                                    <th>Attendance Count</th>
                                    <th>Attendance Rate</th>
                                    <th>Last Attended</th>
                                 </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($top_attendees as $attendee): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <a href="member_view.php?id=<?php echo $attendee['member_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($attendee['member_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $attendee['attendance_count']; ?> / <?php echo $stats['total_services']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $attendee['percentage']; ?>%"></div>
                                            </div>
                                            <span class="ms-2 small"><?php echo round($attendee['percentage']); ?>%</span>
                                        </div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($attendee['last_attended'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Monthly Trends Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-table me-2 text-info"></i>
                        Monthly Attendance Summary
                    </h5>
                    <div>
                        <input type="text" id="searchTable" class="form-control form-control-sm" placeholder="Search..." style="width: 200px;">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="attendanceTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Service Type</th>
                                    <th>Member Name</th>
                                    <th>Check-in Time</th>
                                    <th>Status</th>
                                    <th>Recorded By</th>
                                 </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendance_data)): ?>
                                    <?php foreach ($attendance_data as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['service_date'])); ?>
                                            <br><small class="text-muted"><?php echo date('l', strtotime($record['service_date'])); ?></small>
                                        </td>
                                        <td><?php echo $record['service_type']; ?></td>
                                        <td>
                                            <a href="member_view.php?id=<?php echo $record['member_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($record['member_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '—'; ?></td>
                                        <td>
                                            <?php if ($record['attended']): ?>
                                                <span class="badge bg-success">Present</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Absent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['recorded_by_name'] ?? 'System'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                            <p class="mb-0">No attendance records found for the selected period.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Trend Chart
const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($monthly_trends['labels']); ?>,
        datasets: [{
            label: 'Attendance',
            data: <?php echo json_encode($monthly_trends['data']); ?>,
            borderColor: '#4361ee',
            backgroundColor: 'rgba(67, 97, 238, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#4361ee',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Service Type Distribution Chart
const ctx2 = document.getElementById('serviceTypeChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($service_distribution, 'service_type')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($service_distribution, 'count')); ?>,
            backgroundColor: ['#4361ee', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Table search functionality
document.getElementById('searchTable').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#attendanceTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<style>
.stats-card {
    transition: transform 0.2s ease;
}
.stats-card:hover {
    transform: translateY(-3px);
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
@media (max-width: 768px) {
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}
</style>

<?php
include '../footer.php';

// ============= Helper Functions =============

/**
 * Get attendance statistics
 */
function getAttendanceStats($start_date, $end_date, $service_type) {
    global $db;
    
    $service_filter = $service_type != 'all' ? " AND service_type = '$service_type'" : "";
    
    // Total attendance
    $result = $db->query("SELECT COUNT(*) as count FROM attendance WHERE service_date BETWEEN '$start_date' AND '$end_date' AND attended = 1 $service_filter");
    $total_attendance = $result->fetch_assoc()['count'];
    
    // Total services
    $result = $db->query("SELECT COUNT(DISTINCT CONCAT(service_date, service_type)) as count FROM attendance WHERE service_date BETWEEN '$start_date' AND '$end_date' $service_filter");
    $total_services = $result->fetch_assoc()['count'];
    
    // Average attendance
    $avg_attendance = $total_services > 0 ? round($total_attendance / $total_services) : 0;
    
    // Unique attendees
    $result = $db->query("SELECT COUNT(DISTINCT member_id) as count FROM attendance WHERE service_date BETWEEN '$start_date' AND '$end_date' AND attended = 1 $service_filter");
    $unique_attendees = $result->fetch_assoc()['count'];
    
    return [
        'total_attendance' => $total_attendance,
        'total_services' => $total_services,
        'avg_attendance' => $avg_attendance,
        'unique_attendees' => $unique_attendees
    ];
}

/**
 * Get attendance data
 */
function getAttendanceData($start_date, $end_date, $service_type, $member_id) {
    global $db;
    
    $member_filter = $member_id > 0 ? " AND a.member_id = $member_id" : "";
    $service_filter = $service_type != 'all' ? " AND a.service_type = '$service_type'" : "";
    
    $query = "SELECT a.*, 
                     CONCAT(m.first_name, ' ', m.last_name) as member_name,
                     u.full_name as recorded_by_name
              FROM attendance a
              JOIN members m ON a.member_id = m.member_id
              LEFT JOIN users u ON a.recorded_by = u.user_id
              WHERE a.service_date BETWEEN '$start_date' AND '$end_date' 
              $member_filter $service_filter
              ORDER BY a.service_date DESC, a.service_type";
    
    $result = $db->query($query);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

/**
 * Get monthly attendance trends
 */
function getMonthlyAttendanceTrends($start_date, $end_date) {
    global $db;
    
    $query = "SELECT DATE_FORMAT(service_date, '%Y-%m') as month, 
                     COUNT(*) as attendance
              FROM attendance 
              WHERE service_date BETWEEN '$start_date' AND '$end_date' AND attended = 1
              GROUP BY DATE_FORMAT(service_date, '%Y-%m')
              ORDER BY month";
    
    $result = $db->query($query);
    $labels = [];
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $data[] = $row['attendance'];
    }
    
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Get top attendees
 */
function getTopAttendees($start_date, $end_date) {
    global $db;
    
    $total_services = $db->query("SELECT COUNT(DISTINCT CONCAT(service_date, service_type)) as count FROM attendance WHERE service_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    if ($total_services == 0) return [];
    
    $query = "SELECT a.member_id, 
                     CONCAT(m.first_name, ' ', m.last_name) as member_name,
                     COUNT(*) as attendance_count,
                     MAX(a.service_date) as last_attended
              FROM attendance a
              JOIN members m ON a.member_id = m.member_id
              WHERE a.service_date BETWEEN '$start_date' AND '$end_date' AND a.attended = 1
              GROUP BY a.member_id
              ORDER BY attendance_count DESC
              LIMIT 10";
    
    $result = $db->query($query);
    $attendees = [];
    while ($row = $result->fetch_assoc()) {
        $row['percentage'] = ($row['attendance_count'] / $total_services) * 100;
        $attendees[] = $row;
    }
    return $attendees;
}

/**
 * Get service type distribution
 */
function getServiceDistribution($start_date, $end_date) {
    global $db;
    
    $query = "SELECT service_type, COUNT(*) as count 
              FROM attendance 
              WHERE service_date BETWEEN '$start_date' AND '$end_date' AND attended = 1
              GROUP BY service_type";
    
    $result = $db->query($query);
    $total = 0;
    $distribution = [];
    while ($row = $result->fetch_assoc()) {
        $total += $row['count'];
        $distribution[] = $row;
    }
    
    foreach ($distribution as &$dist) {
        $dist['percentage'] = $total > 0 ? ($dist['count'] / $total) * 100 : 0;
    }
    
    return $distribution;
}

/**
 * Export attendance report to CSV
 */
function exportAttendanceCSV($start_date, $end_date, $service_type, $member_id) {
    global $db;
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // Headers
    fputcsv($output, ['Date', 'Service Type', 'Member Name', 'Check-in Time', 'Status', 'Recorded By']);
    
    // Data
    $member_filter = $member_id > 0 ? " AND a.member_id = $member_id" : "";
    $service_filter = $service_type != 'all' ? " AND a.service_type = '$service_type'" : "";
    
    $query = "SELECT a.service_date, a.service_type, a.check_in_time, a.attended,
                     CONCAT(m.first_name, ' ', m.last_name) as member_name,
                     u.full_name as recorded_by_name
              FROM attendance a
              JOIN members m ON a.member_id = m.member_id
              LEFT JOIN users u ON a.recorded_by = u.user_id
              WHERE a.service_date BETWEEN '$start_date' AND '$end_date' 
              $member_filter $service_filter
              ORDER BY a.service_date DESC";
    
    $result = $db->query($query);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['service_date'],
            $row['service_type'],
            $row['member_name'],
            $row['check_in_time'],
            $row['attended'] ? 'Present' : 'Absent',
            $row['recorded_by_name'] ?? 'System'
        ]);
    }
    
    fclose($output);
    exit();
}
?>