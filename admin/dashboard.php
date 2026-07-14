<?php
// admin/dashboard.php - Main Admin Dashboard
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login - redirect to login if not authenticated
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current user info
$user_id = getCurrentUserId();
$user_name = getCurrentUserName();
$user_role = getCurrentUserRole();

// Generate dashboard statistics
$stats = generateDashboardStats();

// Get recent activities with error handling
$recent_members = [];
$recent_donations = [];
$upcoming_events = [];
$recent_attendance = [];
$birthdays = [];

try {
    // Recent members
    $result = $db->query("
        SELECT member_id, first_name, last_name, membership_date 
        FROM members 
        WHERE membership_status = 'Active' 
        ORDER BY membership_date DESC 
        LIMIT 5
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_members[] = $row;
        }
    }

    // Recent donations
    $result = $db->query("
        SELECT d.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
        FROM donations d
        JOIN members m ON d.member_id = m.member_id
        ORDER BY d.donation_date DESC 
        LIMIT 5
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_donations[] = $row;
        }
    }

    // Upcoming events
    $result = $db->query("
        SELECT * FROM events 
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC 
        LIMIT 5
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $upcoming_events[] = $row;
        }
    }

    // Recent attendance
    $result = $db->query("
        SELECT a.*, CONCAT(m.first_name, ' ', m.last_name) as member_name,
               DAYNAME(a.service_date) as day_name
        FROM attendance a
        JOIN members m ON a.member_id = m.member_id
        WHERE a.service_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY a.service_date DESC, a.attendance_id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_attendance[] = $row;
        }
    }

    // Birthdays this week
    $result = $db->query("
        SELECT member_id, first_name, last_name, date_of_birth,
               DATE_FORMAT(date_of_birth, '%M %d') as birthday_formatted,
               TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
        FROM members 
        WHERE DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) 
              BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY MONTH(date_of_birth), DAY(date_of_birth)
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $birthdays[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
}

// Set page title
$page_title = "Dashboard";

// Include header (this contains all CSS, navbar, and opening HTML tags)
include '../header.php';
?>

<!-- Dashboard Content - NO HTML HEADERS, just the content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded-3 shadow-sm">
                <div>
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-church me-3 text-primary"></i>
                        Welcome, <?php echo htmlspecialchars($user_name ?? 'Administrator'); ?>!
                    </h1>
                    <div class="d-flex align-items-center text-muted">
                        <div class="me-4">
                            <i class="fas fa-calendar-alt me-2"></i><?php echo date('l, F j, Y'); ?>
                        </div>
                        <div>
                            <i class="fas fa-clock me-2"></i><?php echo date('g:i A'); ?>
                        </div>
                    </div>
                </div>
                <div class="d-none d-md-block">
                    <div class="text-end">
                        <span class="badge bg-primary p-3 fs-6 rounded-pill">
                            <i class="fas fa-user-shield me-2"></i><?php echo ucfirst($user_role ?? 'User'); ?>
                        </span>
                        <div class="mt-2">
                            <small class="text-muted">Last login: Today</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-users text-primary fa-2x"></i>
                        </div>
                        <div class="ms-3 flex-grow-1">
                            <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Members</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['total_members']; ?></h2>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>Active members
                            </small>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top">
                        <a href="members.php" class="text-decoration-none small fw-bold">
                            View all members <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-calendar-check text-success fa-2x"></i>
                        </div>
                        <div class="ms-3 flex-grow-1">
                            <h6 class="text-muted text-uppercase small fw-bold mb-1">Today's Attendance</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['today_attendance']; ?></h2>
                            <small class="text-muted">
                                <?php echo $stats['total_members'] > 0 ? round(($stats['today_attendance'] / $stats['total_members']) * 100) : 0; ?>% of members
                            </small>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top">
                        <a href="attendance.php" class="text-decoration-none small fw-bold">
                            Take attendance <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-hand-holding-heart text-warning fa-2x"></i>
                        </div>
                        <div class="ms-3 flex-grow-1">
                            <h6 class="text-muted text-uppercase small fw-bold mb-1">Monthly Donations</h6>
                            <h2 class="mb-0 fw-bold"><?php echo formatCurrency($stats['monthly_donations']); ?></h2>
                            <small class="text-success">
                                <i class="fas fa-chart-line me-1"></i>+12% from last month
                            </small>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top">
                        <a href="donations.php" class="text-decoration-none small fw-bold">
                            View donations <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-calendar-alt text-info fa-2x"></i>
                        </div>
                        <div class="ms-3 flex-grow-1">
                            <h6 class="text-muted text-uppercase small fw-bold mb-1">Upcoming Events</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['upcoming_events']; ?></h2>
                            <small class="text-muted">
                                Next 30 days
                            </small>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top">
                        <a href="events.php" class="text-decoration-none small fw-bold">
                            Manage events <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Row -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2 col-6">
                            <a href="member_add.php" class="quick-action-btn text-decoration-none">
                                <div class="text-center p-3 rounded-3 bg-primary bg-opacity-10">
                                    <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
                                    <span class="d-block small fw-bold">Add Member</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="attendance.php" class="quick-action-btn text-decoration-none">
                                <div class="text-center p-3 rounded-3 bg-success bg-opacity-10">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <span class="d-block small fw-bold">Take Attendance</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="donations.php" class="quick-action-btn text-decoration-none">
                                <div class="text-center p-3 rounded-3 bg-warning bg-opacity-10">
                                    <i class="fas fa-hand-holding-usd fa-2x text-warning mb-2"></i>
                                    <span class="d-block small fw-bold">Record Donation</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="event_add.php" class="quick-action-btn text-decoration-none">
                                <div class="text-center p-3 rounded-3 bg-info bg-opacity-10">
                                    <i class="fas fa-calendar-plus fa-2x text-info mb-2"></i>
                                    <span class="d-block small fw-bold">Create Event</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="group_add.php" class="quick-action-btn text-decoration-none">
                                <div class="text-center p-3 rounded-3 bg-danger bg-opacity-10">
                                    <i class="fas fa-users-cog fa-2x text-danger mb-2"></i>
                                    <span class="d-block small fw-bold">New Group</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="reports.php" class="quick-action-btn text-decoration-none">
                                <div class="text-center p-3 rounded-3 bg-secondary bg-opacity-10">
                                    <i class="fas fa-chart-bar fa-2x text-secondary mb-2"></i>
                                    <span class="d-block small fw-bold">Generate Report</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-line me-2 text-primary"></i>Attendance Overview
                    </h5>
                    <select class="form-select form-select-sm w-auto" id="attendancePeriod">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                </div>
                <div class="card-body">
                    <canvas id="attendanceChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-pie-chart me-2 text-success"></i>Donations
                    </h5>
                    <select class="form-select form-select-sm w-auto" id="donationPeriod">
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <div class="card-body">
                    <canvas id="donationChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Grid -->
    <div class="row g-4">
        <!-- Recent Members -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-user-plus me-2 text-primary"></i>Recent Members
                    </h5>
                    <a href="members.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($recent_members)): ?>
                            <?php foreach ($recent_members as $member): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle me-2 text-primary"></i>
                                        <a href="member_view.php?id=<?php echo $member['member_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        </a>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo timeAgo($member['membership_date']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="fas fa-users fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No members yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Donations -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-hand-holding-heart me-2 text-warning"></i>Recent Donations
                    </h5>
                    <a href="donations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($recent_donations)): ?>
                            <?php foreach ($recent_donations as $donation): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold"><?php echo htmlspecialchars($donation['member_name']); ?></span>
                                        <span class="badge bg-success"><?php echo formatCurrency($donation['amount']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small text-muted">
                                        <span><?php echo formatDate($donation['donation_date']); ?></span>
                                        <span><?php echo $donation['fund_type']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="fas fa-hand-holding-heart fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No donations yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Events & Birthdays -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-calendar-alt me-2 text-info"></i>Upcoming Events
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($upcoming_events)): ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="event-date-box me-3 text-center bg-light rounded-3 p-2" style="min-width: 60px;">
                                            <div class="small text-muted"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                            <div class="fw-bold fs-5"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                        </div>
                                        <div>
                                            <a href="event_view.php?id=<?php echo $event['event_id']; ?>" class="text-decoration-none fw-bold">
                                                <?php echo htmlspecialchars($event['event_name']); ?>
                                            </a>
                                            <div class="small text-muted">
                                                <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No upcoming events</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Birthdays Section -->
                <?php if (!empty($birthdays)): ?>
                <div class="card-footer bg-light">
                    <h6 class="mb-3 fw-bold">
                        <i class="fas fa-birthday-cake me-2 text-danger"></i>Birthdays This Week
                    </h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($birthdays as $birthday): ?>
                            <div class="list-group-item bg-transparent px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']); ?>
                                    </div>
                                    <span class="badge bg-danger rounded-pill">
                                        <?php echo $birthday['birthday_formatted']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Attendance Table -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-clipboard-list me-2 text-success"></i>Recent Attendance
                    </h5>
                    <a href="attendance.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Service</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_attendance)): ?>
                                    <?php foreach ($recent_attendance as $attendance): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold"><?php echo formatDate($attendance['service_date'], 'M d, Y'); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo $attendance['day_name']; ?></small>
                                            </td>
                                            <td>
                                                <a href="member_view.php?id=<?php echo $attendance['member_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($attendance['member_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $attendance['service_type']; ?></td>
                                            <td>
                                                <?php if ($attendance['check_in_time']): ?>
                                                    <?php echo date('g:i A', strtotime($attendance['check_in_time'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attendance['attended']): ?>
                                                    <span class="badge bg-success">Present</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Absent</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-clipboard-list fa-3x mb-3 opacity-50"></i>
                                            <p class="mb-0">No attendance records found</p>
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

<!-- Page-specific JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attendance Chart
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Attendance',
                data: [65, 72, 68, 75, 70, 85, 92],
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
            maintainAspectRatio: false,
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

    // Donation Chart
    const ctx2 = document.getElementById('donationChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Tithe', 'Offering', 'Building Fund', 'Missions'],
            datasets: [{
                data: [4500, 2800, 1500, 900],
                backgroundColor: [
                    '#4361ee',
                    '#f59e0b',
                    '#10b981',
                    '#ef4444'
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            cutout: '60%'
        }
    });
});

// Period select handlers
document.getElementById('attendancePeriod')?.addEventListener('change', function() {
    showNotification('Updating chart data...', 'info');
});

document.getElementById('donationPeriod')?.addEventListener('change', function() {
    showNotification('Updating donation data...', 'info');
});

// Notification function
function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

// Add loading state to quick actions
document.querySelectorAll('.quick-action-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.add('fa-spinner', 'fa-spin');
            setTimeout(() => {
                icon.classList.remove('fa-spinner', 'fa-spin');
            }, 1000);
        }
    });
});
</script>

<style>
/* Dashboard-specific styles - these won't conflict with header */
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
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-action-btn {
    display: block;
    transition: all 0.3s ease;
    color: inherit;
}

.quick-action-btn:hover {
    transform: translateY(-5px);
}

.quick-action-btn .rounded-3 {
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.quick-action-btn:hover .rounded-3 {
    background-color: var(--bs-primary) !important;
    color: white !important;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.quick-action-btn:hover .rounded-3 i,
.quick-action-btn:hover .rounded-3 span {
    color: white !important;
}

.event-date-box {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
}

.list-group-item {
    transition: background-color 0.2s ease;
    border-left: 3px solid transparent;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: var(--bs-primary);
}

.table tbody tr {
    transition: background-color 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 15px;
    }
    
    .quick-action-btn .rounded-3 {
        padding: 10px !important;
    }
    
    .quick-action-btn i {
        font-size: 1.5rem !important;
    }
    
    .quick-action-btn span {
        font-size: 0.7rem !important;
    }
    
    .display-6 {
        font-size: 1.5rem;
    }
}
</style>

<?php
// Include footer (this contains closing HTML tags and footer content)
include '../footer.php';
?>