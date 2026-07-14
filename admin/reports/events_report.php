<?php
// reports/events_report.php - Events Report
if (!defined('ACCESS_CHECK')) { 
    die('Direct access not allowed');
}

// Get events statistics
$total_events = $db->prepare("SELECT COUNT(*) as count FROM events WHERE event_date BETWEEN ? AND ?");
$total_events->bind_param("ss", $start_date, $end_date);
$total_events->execute();
$total_events_count = $total_events->get_result()->fetch_assoc()['count'];

// Upcoming events (within range)
$upcoming = $db->prepare("SELECT COUNT(*) as count FROM events WHERE event_date BETWEEN ? AND ? AND event_date >= CURDATE()");
$upcoming->bind_param("ss", $start_date, $end_date);
$upcoming->execute();
$upcoming_count = $upcoming->get_result()->fetch_assoc()['count'];

// Past events (within range)
$past = $db->prepare("SELECT COUNT(*) as count FROM events WHERE event_date BETWEEN ? AND ? AND event_date < CURDATE()");
$past->bind_param("ss", $start_date, $end_date);
$past->execute();
$past_count = $past->get_result()->fetch_assoc()['count'];

// Total registrations
$registrations = $db->prepare("SELECT COUNT(*) as count FROM event_registrations er 
                               JOIN events e ON er.event_id = e.event_id 
                               WHERE e.event_date BETWEEN ? AND ?");
$registrations->bind_param("ss", $start_date, $end_date);
$registrations->execute();
$total_registrations = $registrations->get_result()->fetch_assoc()['count'];

// Average registrations per event
$avg_registrations = $total_events_count > 0 ? round($total_registrations / $total_events_count, 1) : 0;

// Events by type (if event_type column exists)
$by_type = [];
$type_check = $db->query("SHOW COLUMNS FROM events LIKE 'event_type'");
if ($type_check && $type_check->num_rows > 0) {
    $by_type_query = $db->prepare("SELECT event_type, COUNT(*) as count 
                                   FROM events 
                                   WHERE event_date BETWEEN ? AND ?
                                   GROUP BY event_type");
    $by_type_query->bind_param("ss", $start_date, $end_date);
    $by_type_query->execute();
    $by_type_result = $by_type_query->get_result();
    while ($row = $by_type_result->fetch_assoc()) {
        $by_type[] = $row;
    }
}

// Monthly event distribution for chart
$monthly = $db->prepare("SELECT 
                         DATE_FORMAT(event_date, '%Y-%m') as month,
                         COUNT(*) as event_count,
                         SUM((SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id)) as registration_count
                         FROM events e
                         WHERE event_date BETWEEN ? AND ?
                         GROUP BY DATE_FORMAT(event_date, '%Y-%m')
                         ORDER BY month");
$monthly->bind_param("ss", $start_date, $end_date);
$monthly->execute();
$monthly_result = $monthly->get_result();

$chart_labels = [];
$chart_events = [];
$chart_registrations = [];

while ($row = $monthly_result->fetch_assoc()) {
    $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $chart_events[] = $row['event_count'];
    $chart_registrations[] = $row['registration_count'] ?? 0;
}
$monthly_result->data_seek(0);

// Get top events by registrations
$top_events = $db->prepare("SELECT 
                            e.event_id,
                            e.event_name,
                            e.event_date,
                            e.location,
                            e.max_participants,
                            COUNT(er.registration_id) as registration_count,
                            SUM(CASE WHEN er.attended = 1 THEN 1 ELSE 0 END) as attended_count
                            FROM events e
                            LEFT JOIN event_registrations er ON e.event_id = er.event_id
                            WHERE e.event_date BETWEEN ? AND ?
                            GROUP BY e.event_id
                            ORDER BY registration_count DESC
                            LIMIT 5");
$top_events->bind_param("ss", $start_date, $end_date);
$top_events->execute();
$top_events_result = $top_events->get_result();

// Get all events for detailed table
$events_query = $db->prepare("SELECT 
                              e.*,
                              COUNT(DISTINCT er.registration_id) as total_registrations,
                              SUM(CASE WHEN er.attended = 1 THEN 1 ELSE 0 END) as total_attended,
                              CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                              FROM events e
                              LEFT JOIN event_registrations er ON e.event_id = er.event_id
                              LEFT JOIN users u ON e.created_by = u.user_id
                              WHERE e.event_date BETWEEN ? AND ?
                              GROUP BY e.event_id
                              ORDER BY e.event_date DESC");
$events_query->bind_param("ss", $start_date, $end_date);
$events_query->execute();
$events_result = $events_query->get_result();
?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number"><?php echo $total_events_count; ?></div>
            <div class="label">Total Events</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-success"><?php echo $upcoming_count; ?></div>
            <div class="label">Upcoming</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-warning"><?php echo $past_count; ?></div>
            <div class="label">Past Events</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-info"><?php echo $total_registrations; ?></div>
            <div class="label">Total Registrations</div>
        </div>
    </div>
</div>

<!-- Second Row of Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="summary-card bg-light">
            <div class="number text-primary"><?php echo $avg_registrations; ?></div>
            <div class="label">Avg Registrations/Event</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="summary-card bg-light">
            <?php
            // Calculate average attendance rate
            $attendance_rate = 0;
            if ($total_registrations > 0) {
                $attended_total = 0;
                $events_result->data_seek(0);
                while ($ev = $events_result->fetch_assoc()) {
                    $attended_total += $ev['total_attended'];
                }
                $events_result->data_seek(0);
                $attendance_rate = round(($attended_total / $total_registrations) * 100);
            }
            ?>
            <div class="number text-success"><?php echo $attendance_rate; ?>%</div>
            <div class="label">Attendance Rate</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="summary-card bg-light">
            <?php
            // Calculate capacity utilization
            $total_capacity = 0;
            $total_filled = 0;
            $events_result->data_seek(0);
            while ($ev = $events_result->fetch_assoc()) {
                if ($ev['max_participants'] > 0) {
                    $total_capacity += $ev['max_participants'];
                    $total_filled += min($ev['total_registrations'], $ev['max_participants']);
                }
            }
            $events_result->data_seek(0);
            $utilization = $total_capacity > 0 ? round(($total_filled / $total_capacity) * 100) : 0;
            ?>
            <div class="number text-warning"><?php echo $utilization; ?>%</div>
            <div class="label">Capacity Utilization</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Monthly Event Trends</h6>
            </div>
            <div class="card-body">
                <canvas id="eventsTrendChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Events by Type</h6>
            </div>
            <div class="card-body">
                <canvas id="eventsTypeChart" style="height: 300px;"></canvas>
                <?php if (empty($by_type)): ?>
                <p class="text-muted text-center mt-3">No type data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Events -->
<?php if ($top_events_result->num_rows > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Top 5 Events by Registrations</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Capacity</th>
                                <th>Registrations</th>
                                <th>Attended</th>
                                <th>Fill Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = $top_events_result->fetch_assoc()): 
                                $fill_rate = $event['max_participants'] > 0 ? 
                                            round(($event['registration_count'] / $event['max_participants']) * 100) : 
                                            ($event['registration_count'] > 0 ? 100 : 0);
                            ?>
                            <tr>
                                <td>
                                    <a href="event_view.php?id=<?php echo $event['event_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($event['event_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                <td><?php echo htmlspecialchars($event['location'] ?: 'TBD'); ?></td>
                                <td><?php echo $event['max_participants'] ?: 'Unlimited'; ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $event['registration_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $event['attended_count']; ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-info" style="width: <?php echo $fill_rate; ?>%">
                                            <?php echo $fill_rate; ?>%
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

<!-- Detailed Events Table -->
<div class="row">
    <div class="col-12">
        <h6 class="fw-bold mb-3">Detailed Events List</h6>
        <div class="table-responsive">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Organizer</th>
                        <th>Capacity</th>
                        <th>Registrations</th>
                        <th>Attended</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($events_result->num_rows > 0): ?>
                        <?php while ($event = $events_result->fetch_assoc()): 
                            $is_past = strtotime($event['event_date']) < strtotime(date('Y-m-d'));
                            $is_today = $event['event_date'] == date('Y-m-d');
                            $fill_percentage = $event['max_participants'] > 0 ? 
                                              round(($event['total_registrations'] / $event['max_participants']) * 100) : 0;
                        ?>
                        <tr class="<?php echo $is_today ? 'table-info' : ($is_past ? 'table-light' : ''); ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                <?php if (!empty($event['event_description'])): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo substr(htmlspecialchars($event['event_description']), 0, 50); ?>...
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                </small>
                                <?php if ($is_today): ?>
                                    <span class="badge bg-warning text-dark d-inline-block ms-1">Today</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                <?php echo htmlspecialchars($event['location'] ?: 'TBD'); ?>
                            </td>
                            <td><?php echo htmlspecialchars($event['organizer'] ?: '—'); ?></td>
                            <td class="text-center">
                                <?php echo $event['max_participants'] ?: '∞'; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?php echo $event['total_registrations']; ?></span>
                                <?php if ($event['max_participants'] > 0): ?>
                                    <br>
                                    <small class="text-muted"><?php echo $fill_percentage; ?>% full</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success"><?php echo $event['total_attended']; ?></span>
                                <?php if ($event['total_registrations'] > 0): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo round(($event['total_attended'] / $event['total_registrations']) * 100); ?>% attended
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_class = 'success';
                                $status_text = 'Active';
                                
                                if ($is_past) {
                                    $status_class = 'secondary';
                                    $status_text = 'Completed';
                                }
                                if (isset($event['status']) && $event['status'] == 'Cancelled') {
                                    $status_class = 'danger';
                                    $status_text = 'Cancelled';
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
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No events found in the selected date range</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <!-- Summary Row -->
                <?php if ($events_result->num_rows > 0): ?>
                <tfoot class="table-total">
                    <tr>
                        <td colspan="4" class="text-end"><strong>Totals:</strong></td>
                        <td class="text-center"><strong><?php echo $total_events_count; ?> events</strong></td>
                        <td class="text-center"><strong><?php echo $total_registrations; ?> regs</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Event Registrations Details (if any events have registrations) -->
<?php
// Check if any events have registrations
$has_registrations = $db->prepare("SELECT COUNT(*) as count FROM event_registrations er 
                                   JOIN events e ON er.event_id = e.event_id 
                                   WHERE e.event_date BETWEEN ? AND ?");
$has_registrations->bind_param("ss", $start_date, $end_date);
$has_registrations->execute();
$reg_count = $has_registrations->get_result()->fetch_assoc()['count'];

if ($reg_count > 0):
?>
<div class="row mt-4">
    <div class="col-12">
        <h6 class="fw-bold mb-3">Registration Details</h6>
        <div class="table-responsive">
            <table class="table table-sm report-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Registration Date</th>
                        <th>Attended</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $reg_query = $db->prepare("SELECT 
                                               e.event_name,
                                               e.event_date,
                                               CONCAT(m.first_name, ' ', m.last_name) as member_name,
                                               er.registration_date,
                                               er.attended
                                               FROM event_registrations er
                                               JOIN events e ON er.event_id = e.event_id
                                               JOIN members m ON er.member_id = m.member_id
                                               WHERE e.event_date BETWEEN ? AND ?
                                               ORDER BY e.event_date DESC, er.registration_date DESC
                                               LIMIT 50");
                    $reg_query->bind_param("ss", $start_date, $end_date);
                    $reg_query->execute();
                    $reg_result = $reg_query->get_result();
                    
                    while ($reg = $reg_result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reg['event_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($reg['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($reg['member_name']); ?></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($reg['registration_date'])); ?></td>
                        <td>
                            <?php if ($reg['attended']): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($reg_count > 50): ?>
            <p class="text-muted small mt-2">
                <i class="fas fa-info-circle me-1"></i>
                Showing last 50 of <?php echo $reg_count; ?> registrations.
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Monthly Trend Chart
const trendCtx = document.getElementById('eventsTrendChart')?.getContext('2d');
if (trendCtx) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Number of Events',
                    data: <?php echo json_encode($chart_events); ?>,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    yAxisID: 'y',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Total Registrations',
                    data: <?php echo json_encode($chart_registrations); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    yAxisID: 'y1',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
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
                        text: 'Number of Events'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Registrations'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Events by Type Chart (if data exists)
<?php if (!empty($by_type)): ?>
const typeCtx = document.getElementById('eventsTypeChart')?.getContext('2d');
if (typeCtx) {
    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($by_type, 'event_type')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($by_type, 'count')); ?>,
                backgroundColor: [
                    '#4361ee', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899'
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
function searchEvents() {
    const input = document.getElementById('eventSearch');
    const filter = input.value.toUpperCase();
    const table = document.querySelector('.report-table');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toUpperCase();
        row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
    });
}

// Add search input if desired
const searchDiv = document.createElement('div');
searchDiv.className = 'mb-3';
searchDiv.innerHTML = `
    <div class="input-group" style="max-width: 300px;">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" class="form-control" id="eventSearch" placeholder="Search events..." onkeyup="searchEvents()">
    </div>
`;

// Insert search after the table header
const tableHeader = document.querySelector('h6.fw-bold.mb-3');
if (tableHeader) {
    tableHeader.parentNode.insertBefore(searchDiv, tableHeader.nextSibling);
}
</script>

<!-- Export Options -->
<div class="mt-4 text-end">
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-primary" onclick="printReport()">
            <i class="fas fa-print me-1"></i>Print
        </button>
        <a href="?type=events&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" 
           class="btn btn-sm btn-outline-success">
            <i class="fas fa-file-csv me-1"></i>CSV
        </a>
    </div>
</div>