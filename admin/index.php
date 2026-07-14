<?php
// admin/index.php - Admin Dashboard Landing Page
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';


// admin/index.php - Redirect to dashboard
header('Location: dashboard.php');
exit();

// Require login - redirect to login if not authenticated
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Generate dashboard statistics
$stats = generateDashboardStats();

// Get recent activities
$recent_members = $db->query("
    SELECT member_id, first_name, last_name, membership_date 
    FROM members 
    WHERE membership_status = 'Active' 
    ORDER BY membership_date DESC 
    LIMIT 5
");

$recent_donations = $db->query("
    SELECT d.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
    FROM donations d
    JOIN members m ON d.member_id = m.member_id
    ORDER BY d.donation_date DESC 
    LIMIT 5
");

$upcoming_events = $db->query("
    SELECT * FROM events 
    WHERE event_date >= CURDATE()
    ORDER BY event_date ASC 
    LIMIT 5
");

$recent_attendance = $db->query("
    SELECT a.*, CONCAT(m.first_name, ' ', m.last_name) as member_name,
           DAYNAME(a.service_date) as day_name
    FROM attendance a
    JOIN members m ON a.member_id = m.member_id
    WHERE a.service_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.service_date DESC, a.attendance_id DESC
    LIMIT 10
");

// Get birthday this week
$birthdays = $db->query("
    SELECT member_id, first_name, last_name, date_of_birth,
           DATE_FORMAT(date_of_birth, '%M %d') as birthday_formatted,
           TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
    FROM members 
    WHERE DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) 
          BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY MONTH(date_of_birth), DAY(date_of_birth)
");

// Set page title
$page_title = "Admin Dashboard";

// Include header
include '../header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold">Welcome, <?php echo htmlspecialchars(getCurrentUserName() ?? 'Administrator'); ?>!</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i><?php echo date('l, F j, Y'); ?>
                        <span class="mx-2">|</span>
                        <i class="fas fa-clock me-2"></i><?php echo date('g:i A'); ?>
                    </p>
                </div>
                <div class="d-none d-md-block">
                    <span class="badge bg-primary p-3">
                        <i class="fas fa-user-shield me-2"></i><?php echo ucfirst(getCurrentUserRole() ?? 'User'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="custom-card stats-card" data-aos="fade-up">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-users text-primary fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total Members</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['total_members']; ?></h2>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>+12 this month
                            </small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="members.php" class="text-decoration-none small">
                            View all members <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="custom-card stats-card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-calendar-check text-success fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Today's Attendance</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['today_attendance']; ?></h2>
                            <small class="text-muted">
                                <?php echo $stats['total_members'] > 0 ? round(($stats['today_attendance'] / $stats['total_members']) * 100) : 0; ?>% of members
                            </small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="attendance.php" class="text-decoration-none small">
                            Take attendance <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="custom-card stats-card" data-aos="fade-up" data-aos-delay="200">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-hand-holding-heart text-warning fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Monthly Donations</h6>
                            <h2 class="mb-0 fw-bold"><?php echo formatCurrency($stats['monthly_donations']); ?></h2>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>+8.5% from last month
                            </small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="donations.php" class="text-decoration-none small">
                            View donations <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="custom-card stats-card" data-aos="fade-up" data-aos-delay="300">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-calendar-alt text-info fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Upcoming Events</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['upcoming_events']; ?></h2>
                            <small class="text-muted">
                                Next 30 days
                            </small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="events.php" class="text-decoration-none small">
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
            <div class="custom-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2 col-6">
                            <a href="member_add.php" class="quick-action-btn">
                                <div class="text-center p-3 rounded-3 bg-primary bg-opacity-10">
                                    <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
                                    <span class="d-block small fw-bold">Add Member</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="attendance.php" class="quick-action-btn">
                                <div class="text-center p-3 rounded-3 bg-success bg-opacity-10">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <span class="d-block small fw-bold">Take Attendance</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="donations.php" class="quick-action-btn">
                                <div class="text-center p-3 rounded-3 bg-warning bg-opacity-10">
                                    <i class="fas fa-hand-holding-usd fa-2x text-warning mb-2"></i>
                                    <span class="d-block small fw-bold">Record Donation</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="event_add.php" class="quick-action-btn">
                                <div class="text-center p-3 rounded-3 bg-info bg-opacity-10">
                                    <i class="fas fa-calendar-plus fa-2x text-info mb-2"></i>
                                    <span class="d-block small fw-bold">Create Event</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="group_add.php" class="quick-action-btn">
                                <div class="text-center p-3 rounded-3 bg-danger bg-opacity-10">
                                    <i class="fas fa-users-cog fa-2x text-danger mb-2"></i>
                                    <span class="d-block small fw-bold">New Group</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="reports.php" class="quick-action-btn">
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
        <div class="col-xl-6">
            <div class="custom-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Attendance Trend</h5>
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
        <div class="col-xl-6">
            <div class="custom-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-pie-chart me-2"></i>Donation Distribution</h5>
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
            <div class="custom-card h-100">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Recent Members</h5>
                    <a href="members.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if ($recent_members && $recent_members->num_rows > 0): ?>
                            <?php while ($member = $recent_members->fetch_assoc()): ?>
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
                            <?php endwhile; ?>
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
            <div class="custom-card h-100">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-hand-holding-heart me-2"></i>Recent Donations</h5>
                    <a href="donations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if ($recent_donations && $recent_donations->num_rows > 0): ?>
                            <?php while ($donation = $recent_donations->fetch_assoc()): ?>
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
                            <?php endwhile; ?>
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
            <div class="custom-card h-100">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Events</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if ($upcoming_events && $upcoming_events->num_rows > 0): ?>
                            <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="event-date-box me-3 text-center">
                                            <div class="small text-muted"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                            <div class="fw-bold fs-5"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                        </div>
                                        <div>
                                            <a href="event_view.php?id=<?php echo $event['event_id']; ?>" class="text-decoration-none fw-bold">
                                                <?php echo htmlspecialchars($event['event_name']); ?>
                                            </a>
                                            <div class="small text-muted">
                                                <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                                <?php if (!empty($event['location'])): ?>
                                                    <span class="mx-1">|</span>
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['location']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No upcoming events</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Birthdays Section -->
                <?php if ($birthdays && $birthdays->num_rows > 0): ?>
                <div class="card-footer bg-light">
                    <h6 class="mb-3"><i class="fas fa-birthday-cake me-2 text-danger"></i>Birthdays This Week</h6>
                    <div class="list-group list-group-flush">
                        <?php while ($birthday = $birthdays->fetch_assoc()): ?>
                            <div class="list-group-item bg-transparent px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']); ?>
                                    </div>
                                    <div>
                                        <span class="badge bg-danger rounded-pill">
                                            <?php echo $birthday['birthday_formatted']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Attendance Table -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="custom-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Recent Attendance</h5>
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
                                <?php if ($recent_attendance && $recent_attendance->num_rows > 0): ?>
                                    <?php while ($attendance = $recent_attendance->fetch_assoc()): ?>
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
                                    <?php endwhile; ?>
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

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Chart
document.addEventListener('DOMContentLoaded', function() {
    // Sample data - replace with actual data from your database
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Attendance',
                data: [65, 72, 68, 75, 70, 85, 92],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
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
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
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
                    '#667eea',
                    '#f59e0b',
                    '#10b981',
                    '#ef4444'
                ],
                borderWidth: 0
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
});

// Period select handlers
document.getElementById('attendancePeriod')?.addEventListener('change', function() {
    // Here you would make an AJAX call to update the chart data
    console.log('Change attendance period to:', this.value);
});

document.getElementById('donationPeriod')?.addEventListener('change', function() {
    // Here you would make an AJAX call to update the chart data
    console.log('Change donation period to:', this.value);
});

// Auto-refresh data every 5 minutes (optional)
setTimeout(function() {
    location.reload();
}, 300000); // 5 minutes
</script>

<style>
/* Dashboard specific styles */
.stats-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.stats-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-action-btn {
    text-decoration: none;
    color: inherit;
    display: block;
    transition: transform 0.3s ease;
}

.quick-action-btn:hover {
    transform: translateY(-5px);
}

.quick-action-btn .rounded-3 {
    transition: all 0.3s ease;
}

.quick-action-btn:hover .rounded-3 {
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.event-date-box {
    min-width: 50px;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 8px;
    padding: 5px;
}

.list-group-item {
    transition: background-color 0.2s ease;
}

.list-group-item:hover {
    background-color: rgba(102, 126, 234, 0.05);
}

/* Responsive adjustments */
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
}

/* Loading animation */
.loading {
    position: relative;
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 30px;
    height: 30px;
    border: 3px solid #f3f3f3;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<?php
// Include footer
include '../footer.php';
?>