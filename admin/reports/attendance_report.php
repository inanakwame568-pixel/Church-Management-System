<?php
// reports/attendance_report.php - Attendance Report
if (!defined('ACCESS_CHECK')) { die('Direct access not allowed'); }

// Get attendance statistics
$total_services = $db->query("SELECT COUNT(DISTINCT CONCAT(service_date, service_type)) as count 
                               FROM attendance WHERE service_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];

$total_attendance = $db->query("SELECT COUNT(*) as count FROM attendance 
                                WHERE service_date BETWEEN '$start_date' AND '$end_date' AND attended = 1")->fetch_assoc()['count'];

$avg_attendance = $total_services > 0 ? round($total_attendance / $total_services) : 0;

// Attendance by service type
$by_type = $db->query("SELECT service_type, COUNT(*) as count, 
                       SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as present
                       FROM attendance 
                       WHERE service_date BETWEEN '$start_date' AND '$end_date'
                       GROUP BY service_type");

// Daily attendance for chart
$daily = $db->query("SELECT service_date, 
                     COUNT(*) as total,
                     SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as present
                     FROM attendance 
                     WHERE service_date BETWEEN '$start_date' AND '$end_date'
                     GROUP BY service_date
                     ORDER BY service_date");

$chart_labels = [];
$chart_data = [];
while ($day = $daily->fetch_assoc()) {
    $chart_labels[] = date('M d', strtotime($day['service_date']));
    $chart_data[] = $day['present'];
}
$daily->data_seek(0);
?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="summary-card">
            <div class="number"><?php echo $total_services; ?></div>
            <div class="label">Total Services</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="summary-card">
            <div class="number text-success"><?php echo $total_attendance; ?></div>
            <div class="label">Total Attendance</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="summary-card">
            <div class="number text-info"><?php echo $avg_attendance; ?></div>
            <div class="label">Average per Service</div>
        </div>
    </div>
</div>

<!-- Attendance Chart -->
<div class="chart-container mb-4">
    <canvas id="attendanceTrendChart"></canvas>
</div>

<!-- Attendance by Service Type -->
<div class="row mb-4">
    <div class="col-12">
        <h6 class="fw-bold mb-3">Attendance by Service Type</h6>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Service Type</th>
                    <th>Total Records</th>
                    <th>Present</th>
                    <th>Attendance Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($type = $by_type->fetch_assoc()): 
                    $rate = $type['count'] > 0 ? round(($type['present'] / $type['count']) * 100) : 0;
                ?>
                <tr>
                    <td><?php echo $type['service_type']; ?></td>
                    <td><?php echo $type['count']; ?></td>
                    <td><?php echo $type['present']; ?></td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%">
                                <?php echo $rate; ?>%
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed Attendance Table -->
<h6 class="fw-bold mb-3">Daily Attendance Details</h6>
<div class="table-responsive">
    <table class="table table-hover report-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Service Type</th>
                <th>Member</th>
                <th>Time</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $detail_query = "SELECT a.*, CONCAT(m.first_name, ' ', m.last_name) as member_name,
                                    DAYNAME(a.service_date) as day_name
                             FROM attendance a
                             JOIN members m ON a.member_id = m.member_id
                             WHERE a.service_date BETWEEN ? AND ?
                             ORDER BY a.service_date DESC, a.service_type";
            $stmt = $db->prepare($detail_query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $details = $stmt->get_result();
            
            while ($row = $details->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($row['service_date'])); ?></td>
                <td><?php echo $row['day_name']; ?></td>
                <td><?php echo $row['service_type']; ?></td>
                <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                <td><?php echo $row['check_in_time'] ? date('g:i A', strtotime($row['check_in_time'])) : '—'; ?></td>
                <td>
                    <?php if ($row['attended']): ?>
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