<?php
// member_dashboard.php - Main Member Dashboard (UPDATED)
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php';

// Require member login - now in functions.php
requireMember();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current member info
$user_id = getCurrentUserId();
$member_id = getCurrentMemberId(); // Now in functions.php
$user_name = getCurrentUserName();

// Get member details
$member_query = "SELECT * FROM members WHERE member_id = ?";
$member_stmt = $db->prepare($member_query);
$member_stmt->bind_param("i", $member_id);
$member_stmt->execute();
$member = $member_stmt->get_result()->fetch_assoc();

// Get dashboard stats using helper function
$stats = getMemberDashboardStats($member_id);

// Get recent donations
$recent_donations = $db->prepare("
    SELECT * FROM donations 
    WHERE member_id = ? 
    ORDER BY donation_date DESC 
    LIMIT 5
");
$recent_donations->bind_param("i", $member_id);
$recent_donations->execute();
$recent_donations_result = $recent_donations->get_result();

// Get upcoming events using helper function
$upcoming_events = getMemberUpcomingEvents($member_id, 5);

// Get member's groups using helper function
$groups_result = getMemberGroups($member_id);

// Get available groups to join using helper function
$available_groups = getAvailableGroups($member_id, 5);

// ... (rest of your dashboard code) ...

// Get upcoming events
$upcoming_events = $db->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrations,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND member_id = $member_id) as registered
    FROM events e
    WHERE e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
    LIMIT 5
");

// Get member's groups
$groups_query = "SELECT g.*, gm.role, gm.joined_date
                 FROM `groups` g
                 JOIN group_members gm ON g.group_id = gm.group_id
                 WHERE gm.member_id = ? AND gm.status = 'Active'
                 ORDER BY g.group_name";
$groups_stmt = $db->prepare($groups_query);
$groups_stmt->bind_param("i", $member_id);
$groups_stmt->execute();
$groups_result = $groups_stmt->get_result();

// Get available groups to join
$available_groups = $db->query("
    SELECT g.*, 
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count
    FROM `groups` g
    WHERE g.status = 'Active' 
    AND g.group_id NOT IN (SELECT group_id FROM group_members WHERE member_id = $member_id)
    ORDER BY g.group_name
    LIMIT 5
");

// Get announcements
$announcements = [
    ['title' => 'New Members Class', 'date' => 'Next Sunday', 'description' => 'Join us for our new members class after service.', 'priority' => 'high'],
    ['title' => 'Food Drive', 'date' => 'All Month', 'description' => 'Bring canned goods to support our local community.', 'priority' => 'normal'],
    ['title' => 'Youth Retreat', 'date' => 'March 15-17', 'description' => 'Registration now open for our annual youth retreat.', 'priority' => 'high'],
    ['title' => 'Church Work Day', 'date' => 'Saturday, 9am', 'description' => 'Help beautify our church grounds.', 'priority' => 'low']
];

// Get attendance summary
$attendance_query = "SELECT 
                      COUNT(*) as total_attended,
                      COUNT(DISTINCT service_date) as days_attended
                     FROM attendance 
                     WHERE member_id = ? AND attended = 1";
$attendance_stmt = $db->prepare($attendance_query);
$attendance_stmt->bind_param("i", $member_id);
$attendance_stmt->execute();
$attendance_stats = $attendance_stmt->get_result()->fetch_assoc();

// Get next service time (you can customize this)
$next_service = "Sunday, 10:30 AM";

// Set page title
$page_title = "Member Dashboard";

// Include member header
include 'member_header.php';
?>

<div class="fade-in">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold mb-3">
                    Welcome back, <?php echo htmlspecialchars($member['first_name'] ?? $user_name); ?>!
                </h1>
                <p class="lead mb-0">
                    <i class="fas fa-calendar-check me-2"></i>Next Service: <strong><?php echo $next_service; ?></strong>
                </p>
                <p class="mb-0 mt-2">
                    <i class="fas fa-map-marker-alt me-2"></i><?php echo $member['address'] ?: 'Main Sanctuary'; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="member_profile.php" class="btn btn-light btn-lg">
                    <i class="fas fa-user-edit me-2"></i>Update Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?php echo $attendance_stats['days_attended'] ?? 0; ?></div>
                <div class="stat-label">Services Attended</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?php echo $donation_stats['donation_count'] ?? 0; ?></div>
                <div class="stat-label">Donations Made</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?php echo $groups_result->num_rows; ?></div>
                <div class="stat-label">Groups Joined</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?php echo $upcoming_events->num_rows; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-6">
            <!-- Upcoming Events -->
            <div class="member-card mb-4">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Upcoming Events</h5>
                    <a href="member_events.php" class="btn btn-sm btn-member-outline">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($upcoming_events->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex">
                                        <div class="me-3 text-center">
                                            <div class="bg-light rounded p-2" style="width: 60px;">
                                                <div class="small text-muted"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                                <div class="fw-bold fs-5"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h6>
                                            <p class="small text-muted mb-1">
                                                <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                                <?php if ($event['location']): ?>
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['location']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($event['registered'] > 0): ?>
                                                <span class="badge bg-success">You're registered</span>
                                            <?php else: ?>
                                                <a href="event_register.php?id=<?php echo $event['event_id']; ?>" 
                                                   class="btn btn-sm btn-member-primary mt-1">
                                                    Register Now
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No upcoming events</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Groups -->
            <div class="member-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users me-2 text-success"></i>My Groups</h5>
                    <a href="member_groups.php" class="btn btn-sm btn-member-outline">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($groups_result->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($group = $groups_result->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($group['group_name']); ?></h6>
                                            <p class="small text-muted mb-0">
                                                <i class="fas fa-tag me-1"></i><?php echo $group['group_type']; ?>
                                                <?php if ($group['meeting_day']): ?>
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-clock me-1"></i><?php echo $group['meeting_day']; ?>s 
                                                    <?php echo $group['meeting_time'] ? date('g:i A', strtotime($group['meeting_time'])) : ''; ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-primary"><?php echo ucfirst($group['role']); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                            <p class="mb-2">You haven't joined any groups yet</p>
                            <a href="member_groups.php" class="btn btn-sm btn-member-primary">Browse Groups</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-6">
            <!-- Recent Donations -->
            <div class="member-card mb-4">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-hand-holding-heart me-2 text-warning"></i>Recent Donations</h5>
                    <a href="member_donations.php" class="btn btn-sm btn-member-outline">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_donations_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Fund</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($donation = $recent_donations_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency($donation['amount']); ?></td>
                                            <td><?php echo $donation['fund_type']; ?></td>
                                            <td><?php echo $donation['payment_method']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2"><strong>Total:</strong></td>
                                        <td colspan="2"><strong><?php echo formatCurrency($donation_stats['total_amount'] ?? 0); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-hand-holding-heart fa-3x text-muted mb-3"></i>
                            <p class="mb-2">No donation history yet</p>
                            <a href="give.php" class="btn btn-sm btn-member-primary">Make a Donation</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Announcements -->
            <div class="member-card mb-4">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-bullhorn me-2 text-danger"></i>Announcements</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1">
                                        <?php if ($announcement['priority'] == 'high'): ?>
                                            <span class="badge bg-danger me-2">Important</span>
                                        <?php endif; ?>
                                        <?php echo $announcement['title']; ?>
                                    </h6>
                                    <small class="text-muted"><?php echo $announcement['date']; ?></small>
                                </div>
                                <p class="small text-muted mb-0"><?php echo $announcement['description']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Groups to Join -->
            <?php if ($available_groups->num_rows > 0): ?>
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2 text-info"></i>Groups You Can Join</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($group = $available_groups->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($group['group_name']); ?></h6>
                                        <p class="small text-muted mb-0">
                                            <i class="fas fa-users me-1"></i><?php echo $group['member_count']; ?> members
                                            <?php if ($group['meeting_day']): ?>
                                                <span class="mx-2">|</span>
                                                <i class="fas fa-clock me-1"></i><?php echo $group['meeting_day']; ?>s
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <a href="group_join.php?id=<?php echo $group['group_id']; ?>" 
                                       class="btn btn-sm btn-member-outline"
                                       onclick="return confirm('Join this group?')">
                                        Join
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include member footer
include 'member_footer.php';
?>