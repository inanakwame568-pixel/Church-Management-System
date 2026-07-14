<?php
// reports/birthdays_report.php - Birthdays Report
if (!defined('ACCESS_CHECK')) { 
    die('Direct access not allowed');
}

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-12-31');
$month_filter = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$age_group = isset($_GET['age_group']) ? $_GET['age_group'] : 'all';

// Build query based on filters
$query = "SELECT m.*, 
                 DATE_FORMAT(m.date_of_birth, '%M %d') as birthday_formatted,
                 DATE_FORMAT(m.date_of_birth, '%m') as birth_month,
                 DATE_FORMAT(m.date_of_birth, '%d') as birth_day,
                 TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) as age,
                 CASE 
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) < 18 THEN 'Child'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN 'Young Adult'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN 'Adult'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN 'Middle Age'
                    ELSE 'Senior'
                 END as age_group_calc
          FROM members m 
          WHERE m.date_of_birth IS NOT NULL";

$count_query = "SELECT COUNT(*) as total FROM members WHERE date_of_birth IS NOT NULL";

$params = [];
$types = "";

// Apply month filter
if ($month_filter > 0) {
    $query .= " AND MONTH(m.date_of_birth) = ?";
    $count_query .= " AND MONTH(date_of_birth) = ?";
    $params[] = $month_filter;
    $types .= "i";
}

// Apply date range filter (for custom date ranges)
if ($month_filter == 0 && $start_date && $end_date) {
    $start_month = date('m', strtotime($start_date));
    $end_month = date('m', strtotime($end_date));
    
    if ($start_month <= $end_month) {
        $query .= " AND MONTH(m.date_of_birth) BETWEEN ? AND ?";
        $count_query .= " AND MONTH(date_of_birth) BETWEEN ? AND ?";
        $params[] = $start_month;
        $params[] = $end_month;
        $types .= "ii";
    } else {
        // Handle year wrap (e.g., December to January)
        $query .= " AND (MONTH(m.date_of_birth) >= ? OR MONTH(m.date_of_birth) <= ?)";
        $count_query .= " AND (MONTH(date_of_birth) >= ? OR MONTH(date_of_birth) <= ?)";
        $params[] = $start_month;
        $params[] = $end_month;
        $types .= "ii";
    }
}

// Apply age group filter
if ($age_group != 'all') {
    switch ($age_group) {
        case 'child':
            $query .= " AND TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) < 18";
            $count_query .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18";
            break;
        case 'young':
            $query .= " AND TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) BETWEEN 18 AND 30";
            $count_query .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30";
            break;
        case 'adult':
            $query .= " AND TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) BETWEEN 31 AND 50";
            $count_query .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50";
            break;
        case 'middle':
            $query .= " AND TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) BETWEEN 51 AND 65";
            $count_query .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 65";
            break;
        case 'senior':
            $query .= " AND TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) > 65";
            $count_query .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 65";
            break;
    }
}

$query .= " ORDER BY MONTH(m.date_of_birth), DAY(m.date_of_birth)";

// Prepare and execute main query
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$birthdays_result = $stmt->get_result();

// Get total count
$count_stmt = $db->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_birthdays = $count_stmt->get_result()->fetch_assoc()['total'];

// Get statistics
$stats = [];

// Members with birthdays
$total_with_birthdays = $db->query("SELECT COUNT(*) as count FROM members WHERE date_of_birth IS NOT NULL")->fetch_assoc()['count'];
$total_members = $db->query("SELECT COUNT(*) as count FROM members")->fetch_assoc()['count'];
$birthday_percentage = $total_members > 0 ? round(($total_with_birthdays / $total_members) * 100) : 0;

// Birthdays by month
$by_month = $db->query("
    SELECT 
        MONTH(date_of_birth) as month_num,
        DATE_FORMAT(date_of_birth, '%M') as month_name,
        COUNT(*) as count
    FROM members 
    WHERE date_of_birth IS NOT NULL
    GROUP BY MONTH(date_of_birth), DATE_FORMAT(date_of_birth, '%M')
    ORDER BY month_num
");

$month_labels = [];
$month_counts = [];
while ($month = $by_month->fetch_assoc()) {
    $month_labels[] = $month['month_name'];
    $month_counts[] = $month['count'];
}
$by_month->data_seek(0);

// Birthdays by age group
$age_groups = $db->query("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Child'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN 'Young Adult'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN 'Adult'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN 'Middle Age'
            ELSE 'Senior'
        END as age_group,
        COUNT(*) as count
    FROM members 
    WHERE date_of_birth IS NOT NULL
    GROUP BY age_group
    ORDER BY FIELD(age_group, 'Child', 'Young Adult', 'Adult', 'Middle Age', 'Senior')
");

$age_labels = [];
$age_counts = [];
while ($age = $age_groups->fetch_assoc()) {
    $age_labels[] = $age['age_group'];
    $age_counts[] = $age['count'];
}

// Upcoming birthdays (next 30 days)
$upcoming = $db->query("
    SELECT *, 
           DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) as next_birthday,
           TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age,
           DATEDIFF(
               DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) + 
                   CASE 
                       WHEN DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) < CURDATE() 
                       THEN 1 
                       ELSE 0 
                   END YEAR),
               CURDATE()
           ) as days_until
    FROM members 
    WHERE date_of_birth IS NOT NULL
    HAVING days_until BETWEEN 0 AND 30
    ORDER BY days_until
    LIMIT 10
");

// Today's birthdays
$today = $db->query("
    SELECT *,
           TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
    FROM members 
    WHERE MONTH(date_of_birth) = MONTH(CURDATE()) 
      AND DAY(date_of_birth) = DAY(CURDATE())
    ORDER BY first_name
");

// This week's birthdays
$week_start = date('Y-m-d');
$week_end = date('Y-m-d', strtotime('+7 days'));
$this_week = $db->query("
    SELECT *,
           DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) as next_birthday,
           TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
    FROM members 
    WHERE DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) 
          BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY DAY(date_of_birth)
");

// Set page title
$page_title = "Birthdays Report";

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
                        <i class="fas fa-birthday-cake me-3 text-danger"></i>
                        Birthdays Report
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Track and celebrate member birthdays
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <a href="?type=birthdays&export=csv&month=<?php echo $month_filter; ?>&age_group=<?php echo $age_group; ?>" 
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
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <input type="hidden" name="type" value="birthdays">
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Month</label>
                            <select name="month" class="form-select">
                                <option value="0">All Months</option>
                                <option value="1" <?php echo $month_filter == 1 ? 'selected' : ''; ?>>January</option>
                                <option value="2" <?php echo $month_filter == 2 ? 'selected' : ''; ?>>February</option>
                                <option value="3" <?php echo $month_filter == 3 ? 'selected' : ''; ?>>March</option>
                                <option value="4" <?php echo $month_filter == 4 ? 'selected' : ''; ?>>April</option>
                                <option value="5" <?php echo $month_filter == 5 ? 'selected' : ''; ?>>May</option>
                                <option value="6" <?php echo $month_filter == 6 ? 'selected' : ''; ?>>June</option>
                                <option value="7" <?php echo $month_filter == 7 ? 'selected' : ''; ?>>July</option>
                                <option value="8" <?php echo $month_filter == 8 ? 'selected' : ''; ?>>August</option>
                                <option value="9" <?php echo $month_filter == 9 ? 'selected' : ''; ?>>September</option>
                                <option value="10" <?php echo $month_filter == 10 ? 'selected' : ''; ?>>October</option>
                                <option value="11" <?php echo $month_filter == 11 ? 'selected' : ''; ?>>November</option>
                                <option value="12" <?php echo $month_filter == 12 ? 'selected' : ''; ?>>December</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Age Group</label>
                            <select name="age_group" class="form-select">
                                <option value="all" <?php echo $age_group == 'all' ? 'selected' : ''; ?>>All Ages</option>
                                <option value="child" <?php echo $age_group == 'child' ? 'selected' : ''; ?>>Children (<18)</option>
                                <option value="young" <?php echo $age_group == 'young' ? 'selected' : ''; ?>>Young Adults (18-30)</option>
                                <option value="adult" <?php echo $age_group == 'adult' ? 'selected' : ''; ?>>Adults (31-50)</option>
                                <option value="middle" <?php echo $age_group == 'middle' ? 'selected' : ''; ?>>Middle Age (51-65)</option>
                                <option value="senior" <?php echo $age_group == 'senior' ? 'selected' : ''; ?>>Seniors (65+)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filter
                            </button>
                        </div>
                        
                        <div class="col-md-2">
                            <a href="?type=birthdays" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                        
                        <div class="col-md-2 text-end">
                            <span class="badge bg-info p-2">
                                <i class="fas fa-users me-1"></i>
                                <?php echo $total_birthdays; ?> Birthdays Found
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="summary-card">
                <div class="number"><?php echo $total_with_birthdays; ?></div>
                <div class="label">Members with Birthdays</div>
                <small class="text-muted"><?php echo $birthday_percentage; ?>% of all members</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="number text-success"><?php echo $today->num_rows; ?></div>
                <div class="label">Birthdays Today</div>
                <small class="text-success">
                    <i class="fas fa-calendar-check me-1"></i><?php echo date('F j'); ?>
                </small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="number text-info"><?php echo $this_week->num_rows; ?></div>
                <div class="label">This Week</div>
                <small class="text-info">Next 7 days</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <?php
                // Find month with most birthdays
                $max_month = $db->query("
                    SELECT DATE_FORMAT(date_of_birth, '%M') as month, COUNT(*) as count
                    FROM members 
                    WHERE date_of_birth IS NOT NULL
                    GROUP BY MONTH(date_of_birth)
                    ORDER BY count DESC
                    LIMIT 1
                ")->fetch_assoc();
                ?>
                <div class="number text-warning"><?php echo $max_month['month'] ?? 'N/A'; ?></div>
                <div class="label">Peak Month</div>
                <small class="text-warning"><?php echo $max_month['count'] ?? 0; ?> birthdays</small>
            </div>
        </div>
    </div>

    <!-- Today's Birthdays Alert -->
    <?php if ($today->num_rows > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-birthday-cake fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="alert-heading mb-1">🎉 Happy Birthday!</h5>
                        <p class="mb-0">
                            Today we're celebrating: 
                            <?php 
                            $today_names = [];
                            while ($bday = $today->fetch_assoc()) {
                                $today_names[] = $bday['first_name'] . ' ' . $bday['last_name'] . ' (' . $bday['age'] . ')';
                            }
                            echo implode(' • ', $today_names);
                            ?>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Birthdays by Month</h6>
                </div>
                <div class="card-body">
                    <canvas id="birthdaysByMonthChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Age Group Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="ageGroupChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Birthdays -->
    <?php if ($upcoming->num_rows > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-clock me-2 text-warning"></i>
                        Upcoming Birthdays (Next 30 Days)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Birthday</th>
                                    <th>Age</th>
                                    <th>Days Until</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($bday = $upcoming->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($bday['first_name'] . ' ' . $bday['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo date('F j', strtotime($bday['date_of_birth'])); ?></td>
                                    <td>
                                        <?php 
                                        $new_age = $bday['age'] + 1;
                                        echo "Turning $new_age";
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($bday['days_until'] == 0): ?>
                                            <span class="badge bg-success">Today!</span>
                                        <?php elseif ($bday['days_until'] == 1): ?>
                                            <span class="badge bg-warning text-dark">Tomorrow</span>
                                        <?php else: ?>
                                            <span class="badge bg-info"><?php echo $bday['days_until']; ?> days</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($bday['email']): ?>
                                            <a href="mailto:<?php echo $bday['email']; ?>" class="text-decoration-none me-2">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($bday['phone']): ?>
                                            <a href="tel:<?php echo $bday['phone']; ?>" class="text-decoration-none">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                        <?php endif; ?>
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

    <!-- This Week's Birthdays -->
    <?php if ($this_week->num_rows > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar-week me-2 text-success"></i>
                        This Week's Birthdays
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Day</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $this_week->data_seek(0);
                                while ($bday = $this_week->fetch_assoc()): 
                                    $day_name = date('l', strtotime($bday['next_birthday']));
                                    $is_today = date('Y-m-d', strtotime($bday['next_birthday'])) == date('Y-m-d');
                                ?>
                                <tr class="<?php echo $is_today ? 'table-success' : ''; ?>">
                                    <td><?php echo date('F j', strtotime($bday['next_birthday'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($bday['first_name'] . ' ' . $bday['last_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $new_age = $bday['age'] + 1;
                                        echo "Turning $new_age";
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($is_today): ?>
                                            <span class="badge bg-success">Today</span>
                                        <?php else: ?>
                                            <?php echo $day_name; ?>
                                        <?php endif; ?>
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

    <!-- Complete Birthdays List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2 text-primary"></i>
                        Complete Birthdays List
                        <?php if ($month_filter > 0): ?>
                            <span class="badge bg-primary ms-2">
                                <?php echo date('F', mktime(0, 0, 0, $month_filter, 1)); ?>
                            </span>
                        <?php endif; ?>
                    </h6>
                    <span class="badge bg-secondary"><?php echo $total_birthdays; ?> records</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Age Group</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($birthdays_result->num_rows > 0): ?>
                                    <?php while ($member = $birthdays_result->fetch_assoc()): 
                                        $is_today = date('m-d') == date('m-d', strtotime($member['date_of_birth']));
                                    ?>
                                    <tr class="<?php echo $is_today ? 'table-success' : ''; ?>">
                                        <td>
                                            <strong><?php echo $member['birthday_formatted']; ?></strong>
                                            <?php if ($is_today): ?>
                                                <span class="badge bg-success ms-1">Today!</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="member_view.php?id=<?php echo $member['member_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $member['age']; ?> years</span>
                                        </td>
                                        <td>
                                            <?php
                                            $age_group_calc = $member['age_group_calc'];
                                            $group_class = 'secondary';
                                            if ($age_group_calc == 'Child') $group_class = 'warning';
                                            if ($age_group_calc == 'Young Adult') $group_class = 'info';
                                            if ($age_group_calc == 'Adult') $group_class = 'primary';
                                            if ($age_group_calc == 'Middle Age') $group_class = 'success';
                                            if ($age_group_calc == 'Senior') $group_class = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $group_class; ?>">
                                                <?php echo $age_group_calc; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($member['email']): ?>
                                                <a href="mailto:<?php echo $member['email']; ?>" class="text-decoration-none">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($member['email']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($member['phone']): ?>
                                                <a href="tel:<?php echo $member['phone']; ?>" class="text-decoration-none">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($member['phone']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = 'success';
                                            if ($member['membership_status'] == 'Inactive') $status_class = 'warning';
                                            if ($member['membership_status'] == 'Visitor') $status_class = 'info';
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $member['membership_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="member_view.php?id=<?php echo $member['member_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="mailto:<?php echo $member['email']; ?>" 
                                                   class="btn btn-sm btn-outline-success" 
                                                   title="Send Email">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-birthday-cake fa-3x mb-3 opacity-50"></i>
                                            <p class="mb-0">No birthdays found matching your criteria</p>
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

    <!-- Birthday Calendar (Mini View) -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                        Birthday Calendar Overview
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $months = $by_month->fetch_all(MYSQLI_ASSOC);
                        $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        
                        for ($i = 1; $i <= 12; $i++):
                            $month_count = 0;
                            $month_name = $month_names[$i-1];
                            foreach ($months as $m) {
                                if ($m['month_num'] == $i) {
                                    $month_count = $m['count'];
                                    break;
                                }
                            }
                            
                            $bar_height = $total_with_birthdays > 0 ? round(($month_count / $total_with_birthdays) * 100) : 0;
                        ?>
                        <div class="col-1 text-center mb-3">
                            <div class="small fw-bold"><?php echo $month_name; ?></div>
                            <div class="progress vertical" style="height: 60px; width: 20px; margin: 0 auto;">
                                <div class="progress-bar bg-primary" 
                                     role="progressbar" 
                                     style="height: <?php echo $bar_height; ?>%; width: 20px;" 
                                     aria-valuenow="<?php echo $bar_height; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <div class="small mt-1"><?php echo $month_count; ?></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Birthdays by Month Chart
const monthCtx = document.getElementById('birthdaysByMonthChart')?.getContext('2d');
if (monthCtx) {
    new Chart(monthCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($month_labels); ?>,
            datasets: [{
                label: 'Number of Birthdays',
                data: <?php echo json_encode($month_counts); ?>,
                backgroundColor: '#f59e0b',
                borderColor: '#d97706',
                borderWidth: 1
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
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Age Group Chart
const ageCtx = document.getElementById('ageGroupChart')?.getContext('2d');
if (ageCtx) {
    new Chart(ageCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($age_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($age_counts); ?>,
                backgroundColor: [
                    '#f59e0b', // Child
                    '#3b82f6', // Young Adult
                    '#10b981', // Adult
                    '#8b5cf6', // Middle Age
                    '#ef4444'  // Senior
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

// Search functionality
function searchBirthdays() {
    const input = document.getElementById('birthdaySearch');
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
        <input type="text" class="form-control" id="birthdaySearch" placeholder="Search birthdays..." onkeyup="searchBirthdays()">
    </div>
`;

// Insert search after the card header
const cardHeader = document.querySelector('.card-header .d-flex');
if (cardHeader) {
    cardHeader.parentNode.parentNode.querySelector('.card-body').insertBefore(searchDiv, cardHeader.parentNode.parentNode.querySelector('.table-responsive'));
}
</script>

<style>
/* Summary cards */
.summary-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    transition: transform 0.3s ease;
    height: 100%;
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

/* Vertical progress bars */
.progress.vertical {
    transform: rotate(180deg);
    writing-mode: bt-lr;
    background-color: #e9ecef;
    border-radius: 10px;
}

.progress.vertical .progress-bar {
    width: 100%;
    height: 0;
    transition: height 0.3s ease;
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

/* Print styles */
@media print {
    .btn, .navbar, .footer, form, .card-header .btn {
        display: none !important;
    }
    
    body {
        background: white;
        padding: 20px;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: transparent !important;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .summary-card .number {
        font-size: 1.8rem;
    }
    
    .col-1 {
        width: 16.66%;
        flex: 0 0 16.66%;
    }
}
</style>

<?php
// Include footer
include '../footer.php';
?>