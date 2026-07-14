<?php
// reports/members_report.php - Membership Report
if (!defined('ACCESS_CHECK')) { die('Direct access not allowed'); }

// Get membership statistics
$total_members = $db->query("SELECT COUNT(*) as count FROM members")->fetch_assoc()['count'];
$active_members = $db->query("SELECT COUNT(*) as count FROM members WHERE membership_status = 'Active'")->fetch_assoc()['count'];
$inactive_members = $db->query("SELECT COUNT(*) as count FROM members WHERE membership_status = 'Inactive'")->fetch_assoc()['count'];
$visitors = $db->query("SELECT COUNT(*) as count FROM members WHERE membership_status = 'Visitor'")->fetch_assoc()['count'];

// Gender distribution
$male = $db->query("SELECT COUNT(*) as count FROM members WHERE gender = 'Male'")->fetch_assoc()['count'];
$female = $db->query("SELECT COUNT(*) as count FROM members WHERE gender = 'Female'")->fetch_assoc()['count'];

// New members in date range
$new_members = $db->prepare("SELECT COUNT(*) as count FROM members WHERE membership_date BETWEEN ? AND ?");
$new_members->bind_param("ss", $start_date, $end_date);
$new_members->execute();
$new_count = $new_members->get_result()->fetch_assoc()['count'];

// Get members list
$query = "SELECT * FROM members ORDER BY last_name, first_name";
$members = $db->query($query);
?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number"><?php echo $total_members; ?></div>
            <div class="label">Total Members</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-success"><?php echo $active_members; ?></div>
            <div class="label">Active</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-warning"><?php echo $inactive_members; ?></div>
            <div class="label">Inactive</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <div class="number text-info"><?php echo $new_count; ?></div>
            <div class="label">New This Period</div>
        </div>
    </div>
</div>

<!-- Gender Distribution -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Gender Distribution</h6>
            </div>
            <div class="card-body">
                <canvas id="genderChart" style="height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Membership Status</h6>
            </div>
            <div class="card-body">
                <canvas id="statusChart" style="height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Members Table -->
<div class="table-responsive">
    <table class="table table-hover report-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Birthday</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($member = $members->fetch_assoc()): ?>
            <tr>
                <td><?php echo $member['member_id']; ?></td>
                <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                <td><?php echo htmlspecialchars($member['email']); ?></td>
                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                <td>
                    <span class="badge bg-<?php 
                        echo $member['membership_status'] == 'Active' ? 'success' : 
                            ($member['membership_status'] == 'Inactive' ? 'warning' : 'info'); 
                    ?>">
                        <?php echo $member['membership_status']; ?>
                    </span>
                </td>
                <td><?php echo $member['membership_date'] ? date('M d, Y', strtotime($member['membership_date'])) : 'N/A'; ?></td>
                <td><?php echo $member['date_of_birth'] ? date('M d', strtotime($member['date_of_birth'])) : 'N/A'; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
// Gender Chart
const genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'pie',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?php echo $male; ?>, <?php echo $female; ?>],
            backgroundColor: ['#4361ee', '#f59e0b']
        }]
    }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Active', 'Inactive', 'Visitor'],
        datasets: [{
            data: [<?php echo $active_members; ?>, <?php echo $inactive_members; ?>, <?php echo $visitors; ?>],
            backgroundColor: ['#10b981', '#f59e0b', '#3b82f6']
        }]
    }
});
</script>