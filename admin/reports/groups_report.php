<?php
// reports/groups_report.php - Groups Report
if (!defined('ACCESS_CHECK')) { 
    die('Direct access not allowed');
}

// Get groups statistics
$total_groups = $db->query("SELECT COUNT(*) as count FROM groups")->fetch_assoc()['count'];
$active_groups = $db->query("SELECT COUNT(*) as count FROM groups WHERE status = 'Active'")->fetch_assoc()['count'];
$inactive_groups = $db->query("SELECT COUNT(*) as count FROM groups WHERE status = 'Inactive'")->fetch_assoc()['count'];
$forming_groups = $db->query("SELECT COUNT(*) as count FROM groups WHERE status = 'Forming'")->fetch_assoc()['count'];

// Total members in groups
$total_group_members = $db->query("SELECT COUNT(*) as count FROM group_members WHERE status = 'Active'")->fetch_assoc()['count'];

// Average group size
$avg_group_size = $active_groups > 0 ? round($total_group_members / $active_groups, 1) : 0;

// Groups by type
$by_type = $db->query("SELECT group_type, COUNT(*) as count, 
                       SUM(current_members) as total_members,
                       AVG(current_members) as avg_size
                       FROM groups 
                       WHERE status = 'Active'
                       GROUP BY group_type 
                       ORDER BY count DESC");

// Groups by meeting day
$by_day = $db->query("SELECT meeting_day, COUNT(*) as count 
                      FROM groups 
                      WHERE meeting_day IS NOT NULL AND status = 'Active'
                      GROUP BY meeting_day 
                      ORDER BY FIELD(meeting_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");

// Top groups by size
$top_groups = $db->query("SELECT g.*, 
                          CONCAT(m.first_name, ' ', m.last_name) as leader_name,
                          (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count
                          FROM groups g
                          LEFT JOIN members m ON g.leader_id = m.member_id
                          WHERE g.status = 'Active'
                          ORDER BY member_count DESC
                          LIMIT 5");

// Groups with no leader
$no_leader = $db->query("SELECT COUNT(*) as count FROM groups WHERE leader_id IS NULL AND status = 'Active'")->fetch_assoc()['count'];

// Groups at capacity
$at_capacity = $db->query("SELECT COUNT(*) as count FROM groups 
                           WHERE max_capacity > 0 AND current_members >= max_capacity AND status = 'Active'")->fetch_assoc()['count'];

// Recent meetings attendance
$recent_meetings = $db->prepare("
    SELECT gm.meeting_date, g.group_name, gm.attendance_count,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as total_members
    FROM group_meetings gm
    JOIN groups g ON gm.group_id = g.group_id
    WHERE gm.meeting_date BETWEEN ? AND ?
    ORDER BY gm.meeting_date DESC
    LIMIT 10
");
$recent_meetings->bind_param("ss", $start_date, $end_date);
$recent_meetings->execute();
$recent_meetings_result = $recent_meetings->get_result();

// Get all groups for detailed table
$groups_query = "SELECT g.*, 
                 CONCAT(m.first_name, ' ', m.last_name) as leader_name,
                 CONCAT(cl.first_name, ' ', cl.last_name) as co_leader_name,
                 (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count,
                 (SELECT COUNT(*) FROM group_meetings WHERE group_id = g.group_id AND meeting_date BETWEEN ? AND ?) as meeting_count,
                 (SELECT AVG(attendance_count) FROM group_meetings WHERE group_id = g.group_id) as avg_attendance
                 FROM groups g
                 LEFT JOIN members m ON g.leader_id = m.member_id
                 LEFT JOIN members cl ON g.co_leader_id = cl.member_id
                 ORDER BY g.status, g.group_name";

$groups_stmt = $db->prepare($groups_query);
$groups_stmt->bind_param("ss", $start_date, $end_date);
$groups_stmt->execute();
$groups_result = $groups_stmt->get_result();

// Chart data for groups by type
$type_labels = [];
$type_counts = [];
$type_members = [];

while ($type = $by_type->fetch_assoc()) {
    $type_labels[] = $type['group_type'];
    $type_counts[] = $type['count'];
    $type_members[] = $type['total_members'];
}
$by_type->data_seek(0);

// Chart data for meeting days
$day_labels = [];
$day_counts = [];
$day_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

while ($day = $by_day->fetch_assoc()) {
    $day_labels[] = $day['meeting_day'];
    $day_counts[] = $day['count'];
}
$by_day->data_seek(0);
?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number"><?php echo $total_groups; ?></div>
            <div class="label">Total Groups</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-success"><?php echo $active_groups; ?></div>
            <div class="label">Active Groups</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-info"><?php echo $total_group_members; ?></div>
            <div class="label">Group Members</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-primary"><?php echo $avg_group_size; ?></div>
            <div class="label">Avg Group Size</div>
        </div>
    </div>
</div>

<!-- Second Row of Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="summary-card bg-light">
            <div class="number text-warning"><?php echo $forming_groups; ?></div>
            <div class="label">Forming</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card bg-light">
            <div class="number text-danger"><?php echo $inactive_groups; ?></div>
            <div class="label">Inactive</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card bg-light">
            <div class="number text-secondary"><?php echo $no_leader; ?></div>
            <div class="label">No Leader</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card bg-light">
            <div class="number text-info"><?php echo $at_capacity; ?></div>
            <div class="label">At Capacity</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Groups by Type</h6>
            </div>
            <div class="card-body">
                <canvas id="groupsByTypeChart" style="height: 300px;"></canvas>
                <?php if (empty($type_labels)): ?>
                <p class="text-muted text-center mt-3">No type data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Meeting Day Distribution</h6>
            </div>
            <div class="card-body">
                <canvas id="meetingDayChart" style="height: 300px;"></canvas>
                <?php if (empty($day_labels)): ?>
                <p class="text-muted text-center mt-3">No meeting day data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Groups -->
<?php if ($top_groups->num_rows > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Largest Groups</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Group Name</th>
                                <th>Type</th>
                                <th>Leader</th>
                                <th>Members</th>
                                <th>Capacity</th>
                                <th>Meeting Day</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($group = $top_groups->fetch_assoc()): 
                                $capacity_percentage = $group['max_capacity'] > 0 ? 
                                                      round(($group['member_count'] / $group['max_capacity']) * 100) : 0;
                            ?>
                            <tr>
                                <td>
                                    <a href="group_view.php?id=<?php echo $group['group_id']; ?>" class="text-decoration-none fw-bold">
                                        <?php echo htmlspecialchars($group['group_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo $group['group_type']; ?></td>
                                <td><?php echo htmlspecialchars($group['leader_name'] ?: 'No leader'); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $group['member_count']; ?></span>
                                </td>
                                <td>
                                    <?php if ($group['max_capacity'] > 0): ?>
                                        <?php echo $group['max_capacity']; ?>
                                        <br>
                                        <small class="text-muted"><?php echo $capacity_percentage; ?>% full</small>
                                    <?php else: ?>
                                        Unlimited
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $group['meeting_day'] ?: 'TBD'; ?></td>
                                <td>
                                    <?php
                                    $status_class = 'success';
                                    if ($group['status'] == 'Inactive') $status_class = 'secondary';
                                    if ($group['status'] == 'Forming') $status_class = 'info';
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $group['status']; ?>
                                    </span>
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

<!-- Recent Meetings Attendance -->
<?php if ($recent_meetings_result->num_rows > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Recent Meetings Attendance</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Group</th>
                                <th>Attendance</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($meeting = $recent_meetings_result->fetch_assoc()): 
                                $attendance_rate = $meeting['total_members'] > 0 ? 
                                                  round(($meeting['attendance_count'] / $meeting['total_members']) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($meeting['meeting_date'])); ?></td>
                                <td><?php echo htmlspecialchars($meeting['group_name']); ?></td>
                                <td><?php echo $meeting['attendance_count']; ?> / <?php echo $meeting['total_members']; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $attendance_rate >= 75 ? 'success' : ($attendance_rate >= 50 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $attendance_rate; ?>%">
                                            <?php echo $attendance_rate; ?>%
                                        </div>
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

<!-- Detailed Groups Table -->
<div class="row">
    <div class="col-12">
        <h6 class="fw-bold mb-3">Detailed Groups List</h6>
        <div class="table-responsive">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Type</th>
                        <th>Leader</th>
                        <th>Co-Leader</th>
                        <th>Members</th>
                        <th>Meeting</th>
                        <th>Meetings</th>
                        <th>Avg Attendance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($groups_result->num_rows > 0): ?>
                        <?php while ($group = $groups_result->fetch_assoc()): 
                            $meeting_info = $group['meeting_day'] ? $group['meeting_day'] . ' ' . date('g:i A', strtotime($group['meeting_time'])) : 'Not set';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($group['group_name']); ?></strong>
                                <?php if (!empty($group['description'])): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo substr(htmlspecialchars($group['description']), 0, 50); ?>...
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $group['group_type']; ?></td>
                            <td>
                                <?php if ($group['leader_name']): ?>
                                    <a href="member_view.php?id=<?php echo $group['leader_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($group['leader_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($group['co_leader_name']): ?>
                                    <a href="member_view.php?id=<?php echo $group['co_leader_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($group['co_leader_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?php echo $group['member_count']; ?></span>
                                <?php if ($group['max_capacity'] > 0): ?>
                                    <br>
                                    <small class="text-muted">/ <?php echo $group['max_capacity']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-calendar me-1 text-muted"></i>
                                <?php echo $meeting_info; ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($group['meeting_location'] ?: 'TBD'); ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?php echo $group['meeting_count']; ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($group['avg_attendance']): ?>
                                    <span class="badge bg-success"><?php echo round($group['avg_attendance']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_class = 'success';
                                $status_text = $group['status'];
                                if ($group['status'] == 'Inactive') {
                                    $status_class = 'secondary';
                                } elseif ($group['status'] == 'Forming') {
                                    $status_class = 'info';
                                }
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="fas fa-users fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No groups found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <!-- Summary Row -->
                <?php if ($groups_result->num_rows > 0): ?>
                <tfoot class="table-total">
                    <tr>
                        <td colspan="4" class="text-end"><strong>Totals:</strong></td>
                        <td class="text-center"><strong><?php echo $total_group_members; ?> members</strong></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Group Members Details (Optional) -->
<?php if ($total_group_members > 0): ?>
<div class="row mt-4">
    <div class="col-12">
        <h6 class="fw-bold mb-3">Group Membership Distribution</h6>
        <?php
        // Get member distribution across groups
        $dist_query = "SELECT 
                       CONCAT(m.first_name, ' ', m.last_name) as member_name,
                       COUNT(gm.group_id) as group_count,
                       GROUP_CONCAT(g.group_name SEPARATOR ', ') as group_names
                       FROM members m
                       JOIN group_members gm ON m.member_id = gm.member_id
                       JOIN groups g ON gm.group_id = g.group_id
                       WHERE gm.status = 'Active' AND g.status = 'Active'
                       GROUP BY m.member_id
                       ORDER BY group_count DESC
                       LIMIT 20";
        $dist_result = $db->query($dist_query);
        
        if ($dist_result && $dist_result->num_rows > 0):
        ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Groups</th>
                        <th>Group Names</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($member = $dist_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?php echo $member['group_count']; ?></span>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($member['group_names']); ?></small>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <p class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>
                Showing top 20 members by group participation
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
// Groups by Type Chart
<?php if (!empty($type_labels)): ?>
const typeCtx = document.getElementById('groupsByTypeChart')?.getContext('2d');
if (typeCtx) {
    new Chart(typeCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($type_labels); ?>,
            datasets: [
                {
                    label: 'Number of Groups',
                    data: <?php echo json_encode($type_counts); ?>,
                    backgroundColor: '#4361ee',
                    yAxisID: 'y'
                },
                {
                    label: 'Total Members',
                    data: <?php echo json_encode($type_members); ?>,
                    backgroundColor: '#10b981',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Groups'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Total Members'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Meeting Day Chart
<?php if (!empty($day_labels)): ?>
const dayCtx = document.getElementById('meetingDayChart')?.getContext('2d');
if (dayCtx) {
    new Chart(dayCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($day_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($day_counts); ?>,
                backgroundColor: [
                    '#4361ee', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'
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
<?php endif; ?>

// Add search functionality
function searchGroups() {
    const input = document.getElementById('groupSearch');
    const filter = input.value.toUpperCase();
    const table = document.querySelector('.report-table');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toUpperCase();
        row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
    });
}

// Add search input
const searchDiv = document.createElement('div');
searchDiv.className = 'mb-3';
searchDiv.innerHTML = `
    <div class="input-group" style="max-width: 300px;">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" class="form-control" id="groupSearch" placeholder="Search groups..." onkeyup="searchGroups()">
    </div>
`;

// Insert search after the table header
const tableHeader = document.querySelector('h6.fw-bold.mb-3');
if (tableHeader) {
    tableHeader.parentNode.insertBefore(searchDiv, tableHeader.nextSibling);
}

// Export function for charts to print
window.onbeforeprint = function() {
    // Convert charts to images for printing
    // This is handled by browser's print functionality
};
</script>

<!-- Export Options -->
<div class="mt-4 text-end">
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print
        </button>
        <a href="?type=groups&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" 
           class="btn btn-sm btn-outline-success">
            <i class="fas fa-file-csv me-1"></i>CSV
        </a>
    </div>
</div>

<style>
/* Additional report styles */
.summary-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    transition: transform 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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

/* Table styles */
.report-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    white-space: nowrap;
}

.report-table td {
    vertical-align: middle;
}

.table-total {
    background-color: #e9ecef;
    font-weight: bold;
}

/* Progress bars */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

/* Badge styles */
.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Responsive */
@media (max-width: 768px) {
    .summary-card .number {
        font-size: 1.8rem;
    }
    
    .btn-group {
        display: flex;
        width: 100%;
    }
    
    .btn-group .btn {
        flex: 1;
    }
}
</style>