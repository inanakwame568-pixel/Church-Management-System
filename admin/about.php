<?php
// admin/about.php - About Page for Admin Section
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get system information
$system_info = [];

// PHP Version
$system_info['php_version'] = phpversion();

// MySQL Version
$mysql_version = $db->query("SELECT VERSION() as version")->fetch_assoc();
$system_info['mysql_version'] = $mysql_version['version'];

// Server Software
$system_info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';

// Server OS
$system_info['server_os'] = php_uname('s') . ' ' . php_uname('r');

// Document Root
$system_info['document_root'] = $_SERVER['DOCUMENT_ROOT'];

// Current Time
$system_info['current_time'] = date('Y-m-d H:i:s');

// Timezone
$system_info['timezone'] = date_default_timezone_get();

// Maximum Upload Size
$system_info['upload_max_filesize'] = ini_get('upload_max_filesize');
$system_info['post_max_size'] = ini_get('post_max_size');
$system_info['max_execution_time'] = ini_get('max_execution_time');

// Database Statistics
$stats = [];

// Count total members
$result = $db->query("SELECT COUNT(*) as count FROM members");
$stats['total_members'] = $result->fetch_assoc()['count'];

// Count active members
$result = $db->query("SELECT COUNT(*) as count FROM members WHERE membership_status = 'Active'");
$stats['active_members'] = $result->fetch_assoc()['count'];

// Count users
$result = $db->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Count groups
$result = $db->query("SELECT COUNT(*) as count FROM `groups`");
$stats['total_groups'] = $result->fetch_assoc()['count'] ?? 0;

// Count events
$result = $db->query("SELECT COUNT(*) as count FROM events");
$stats['total_events'] = $result->fetch_assoc()['count'] ?? 0;

// Count donations
$result = $db->query("SELECT COUNT(*) as count FROM donations");
$stats['total_donations'] = $result->fetch_assoc()['count'] ?? 0;

// Database size
$db_name = DB_NAME;
$db_size = $db->query("
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
    FROM information_schema.tables 
    WHERE table_schema = '$db_name'
")->fetch_assoc();
$stats['database_size'] = $db_size['size_mb'] ?? 0;

// Get latest version info (you can replace with actual version check)
$current_version = '1.0.0';
$latest_version = '1.0.0'; // This could be fetched from a remote server

// Get system health checks
$health_checks = [];

// Check database connection
$health_checks['database'] = [
    'status' => 'good',
    'message' => 'Database connection is working'
];

// Check upload directory
$upload_dir = UPLOAD_PATH;
if (is_writable($upload_dir)) {
    $health_checks['upload_dir'] = [
        'status' => 'good',
        'message' => 'Upload directory is writable'
    ];
} else {
    $health_checks['upload_dir'] = [
        'status' => 'warning',
        'message' => 'Upload directory is not writable'
    ];
}

// Check session
if (session_status() === PHP_SESSION_ACTIVE) {
    $health_checks['session'] = [
        'status' => 'good',
        'message' => 'Sessions are working'
    ];
} else {
    $health_checks['session'] = [
        'status' => 'warning',
        'message' => 'Sessions are not active'
    ];
}

// Check required extensions
$required_extensions = ['mysqli', 'gd', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        $health_checks["ext_$ext"] = [
            'status' => 'good',
            'message' => "$ext extension is loaded"
        ];
    } else {
        $health_checks["ext_$ext"] = [
            'status' => 'error',
            'message' => "$ext extension is not loaded"
        ];
    }
}

// Get team members (developers/contributors)
$team_members = [
    [
        'name' => 'System Administrator',
        'role' => 'Lead Developer',
        'email' => 'admin@church.org',
        'avatar' => null
    ],
    // Add more team members as needed
];

// Set page title
$page_title = "About System";

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
                        <i class="fas fa-info-circle me-3 text-primary"></i>
                        About the System
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-code-branch me-2"></i>
                        Version <?php echo $current_version; ?> | 
                        <i class="fas fa-calendar-alt ms-3 me-2"></i>
                        Last updated: <?php echo date('F j, Y'); ?>
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

    <!-- System Overview Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-3">
                        <i class="fas fa-database"></i>
                    </div>
                    <h5>Database</h5>
                    <p class="h3 fw-bold"><?php echo $stats['database_size']; ?> MB</p>
                    <small class="text-muted">Total Size</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5>Members</h5>
                    <p class="h3 fw-bold"><?php echo $stats['total_members']; ?></p>
                    <small class="text-muted"><?php echo $stats['active_members']; ?> Active</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-info mb-3">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h5>Events</h5>
                    <p class="h3 fw-bold"><?php echo $stats['total_events']; ?></p>
                    <small class="text-muted">Planned & Past</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-warning mb-3">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h5>Donations</h5>
                    <p class="h3 fw-bold"><?php echo $stats['total_donations']; ?></p>
                    <small class="text-muted">Total Transactions</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
        <!-- Left Column - System Information -->
        <div class="col-lg-6">
            <!-- System Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-server me-2 text-primary"></i>
                        System Information
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 200px;">PHP Version:</th>
                            <td>
                                <span class="badge bg-<?php echo version_compare($system_info['php_version'], '7.4', '>=') ? 'success' : 'warning'; ?>">
                                    <?php echo $system_info['php_version']; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>MySQL Version:</th>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo $system_info['mysql_version']; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Server Software:</th>
                            <td><?php echo $system_info['server_software']; ?></td>
                        </tr>
                        <tr>
                            <th>Server OS:</th>
                            <td><?php echo $system_info['server_os']; ?></td>
                        </tr>
                        <tr>
                            <th>Document Root:</th>
                            <td><code><?php echo $system_info['document_root']; ?></code></td>
                        </tr>
                        <tr>
                            <th>Current Time:</th>
                            <td><?php echo $system_info['current_time']; ?></td>
                        </tr>
                        <tr>
                            <th>Timezone:</th>
                            <td><?php echo $system_info['timezone']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- PHP Configuration -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-cog me-2 text-secondary"></i>
                        PHP Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 200px;">Upload Max Filesize:</th>
                            <td><?php echo $system_info['upload_max_filesize']; ?></td>
                        </tr>
                        <tr>
                            <th>Post Max Size:</th>
                            <td><?php echo $system_info['post_max_size']; ?></td>
                        </tr>
                        <tr>
                            <th>Max Execution Time:</th>
                            <td><?php echo $system_info['max_execution_time']; ?> seconds</td>
                        </tr>
                        <tr>
                            <th>Memory Limit:</th>
                            <td><?php echo ini_get('memory_limit'); ?></td>
                        </tr>
                        <tr>
                            <th>Display Errors:</th>
                            <td>
                                <span class="badge bg-<?php echo ini_get('display_errors') ? 'warning' : 'success'; ?>">
                                    <?php echo ini_get('display_errors') ? 'On' : 'Off'; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column - Health & Statistics -->
        <div class="col-lg-6">
            <!-- System Health -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-heartbeat me-2 text-danger"></i>
                        System Health
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($health_checks as $check): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <?php if ($check['status'] == 'good'): ?>
                                    <span class="badge bg-success rounded-circle p-2">
                                        <i class="fas fa-check"></i>
                                    </span>
                                <?php elseif ($check['status'] == 'warning'): ?>
                                    <span class="badge bg-warning rounded-circle p-2">
                                        <i class="fas fa-exclamation"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-circle p-2">
                                        <i class="fas fa-times"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-0"><?php echo $check['message']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Database Statistics -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-pie me-2 text-success"></i>
                        Database Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="statsChart" style="height: 200px;"></canvas>
                    
                    <table class="table table-sm mt-4">
                        <tr>
                            <th>Total Members:</th>
                            <td><?php echo $stats['total_members']; ?></td>
                            <td class="text-end"><?php echo $stats['total_members'] > 0 ? round(($stats['active_members'] / $stats['total_members']) * 100) : 0; ?>% active</td>
                        </tr>
                        <tr>
                            <th>System Users:</th>
                            <td><?php echo $stats['total_users']; ?></td>
                            <td class="text-end">accounts</td>
                        </tr>
                        <tr>
                            <th>Small Groups:</th>
                            <td><?php echo $stats['total_groups']; ?></td>
                            <td class="text-end">active ministries</td>
                        </tr>
                        <tr>
                            <th>Total Events:</th>
                            <td><?php echo $stats['total_events']; ?></td>
                            <td class="text-end">scheduled</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Version Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-code-branch me-2 text-info"></i>
                        Version Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span>Current Version:</span>
                        <span class="badge bg-primary p-2">v<?php echo $current_version; ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span>Latest Version:</span>
                        <span class="badge bg-<?php echo $current_version == $latest_version ? 'success' : 'warning'; ?> p-2">
                            v<?php echo $latest_version; ?>
                        </span>
                    </div>
                    <?php if ($current_version != $latest_version): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-download me-2"></i>
                            A new version is available! <a href="#" class="alert-link">Update now</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Credits Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-heart me-2 text-danger"></i>
                        Credits & Acknowledgments
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6>Technologies Used</h6>
                            <ul class="list-unstyled">
                                <li><i class="fab fa-php me-2 text-primary"></i> PHP <?php echo $system_info['php_version']; ?></li>
                                <li><i class="fas fa-database me-2 text-success"></i> MySQL <?php echo $system_info['mysql_version']; ?></li>
                                <li><i class="fab fa-bootstrap me-2 text-purple"></i> Bootstrap 5</li>
                                <li><i class="fab fa-js me-2 text-warning"></i> JavaScript</li>
                                <li><i class="fas fa-chart-line me-2 text-info"></i> Chart.js</li>
                                <li><i class="fas fa-font-awesome me-2 text-danger"></i> Font Awesome 6</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Development Team</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($team_members as $member): ?>
                                    <div class="text-center" style="width: 100px;">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 60px; height: 60px;">
                                            <?php if ($member['avatar']): ?>
                                                <img src="<?php echo $member['avatar']; ?>" class="rounded-circle w-100 h-100" alt="<?php echo $member['name']; ?>">
                                            <?php else: ?>
                                                <i class="fas fa-user fa-2x text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small fw-bold"><?php echo $member['name']; ?></div>
                                        <div class="small text-muted"><?php echo $member['role']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <p class="text-center text-muted small mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
                        <br>
                        Built with <i class="fas fa-heart text-danger"></i> for church communities
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Statistics Chart
    const ctx = document.getElementById('statsChart')?.getContext('2d');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Members', 'Users', 'Groups', 'Events'],
                datasets: [{
                    data: [
                        <?php echo $stats['total_members']; ?>,
                        <?php echo $stats['total_users']; ?>,
                        <?php echo $stats['total_groups']; ?>,
                        <?php echo $stats['total_events']; ?>
                    ],
                    backgroundColor: [
                        '#4361ee',
                        '#f59e0b',
                        '#10b981',
                        '#ef4444'
                    ]
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
});

// Print function
function printSystemInfo() {
    window.print();
}

// Check for updates (simulated)
function checkForUpdates() {
    showNotification('Checking for updates...', 'info');
    // Simulate API call
    setTimeout(() => {
        showNotification('You are running the latest version!', 'success');
    }, 1500);
}

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

// Run system check
function runSystemCheck() {
    showNotification('Running system diagnostics...', 'info');
    // Reload page to get fresh health checks
    setTimeout(() => {
        location.reload();
    }, 1500);
}
</script>

<style>
/* About page specific styles */
.card {
    border-radius: 12px;
    overflow: hidden;
}

.table-borderless th {
    font-weight: 600;
    color: #495057;
}

.table-borderless td {
    color: #6c757d;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Hover effects */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}

/* Progress bars */
.progress {
    height: 8px;
    border-radius: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .display-6 {
        font-size: 1.5rem;
    }
    
    .table-borderless th {
        width: auto !important;
        display: block;
    }
    
    .table-borderless td {
        display: block;
        padding-left: 0;
    }
}

/* Print styles */
@media print {
    .btn, .navbar, .footer, .card-header .btn {
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
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: transparent !important;
    }
}

/* Custom animations */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.display-4 i {
    animation: pulse 2s infinite;
}

/* Status indicators */
.status-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 5px;
}

.status-dot.good {
    background-color: #10b981;
    box-shadow: 0 0 5px #10b981;
}

.status-dot.warning {
    background-color: #f59e0b;
    box-shadow: 0 0 5px #f59e0b;
}

.status-dot.error {
    background-color: #ef4444;
    box-shadow: 0 0 5px #ef4444;
}
</style>

<?php
// Include footer
include '../footer.php';
?>