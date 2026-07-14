<?php
// member_events.php - Member Events View and Registration
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php';

// Require member login
requireMember();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current member info
$member_id = getCurrentMemberId();
$user_name = getCurrentUserName();

// Handle event registration
$registration_message = '';
$registration_error = '';

if (isset($_GET['register']) && is_numeric($_GET['register'])) {
    $event_id = (int)$_GET['register'];
    
    // Check if already registered
    $check_stmt = $db->prepare("SELECT registration_id FROM event_registrations WHERE event_id = ? AND member_id = ?");
    $check_stmt->bind_param("ii", $event_id, $member_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $registration_error = "You are already registered for this event.";
    } else {
        // Check if event has capacity
        $capacity_check = $db->prepare("SELECT max_participants, 
                                        (SELECT COUNT(*) FROM event_registrations WHERE event_id = ?) as current_registrations
                                        FROM events WHERE event_id = ?");
        $capacity_check->bind_param("ii", $event_id, $event_id);
        $capacity_check->execute();
        $event_info = $capacity_check->get_result()->fetch_assoc();
        
        if ($event_info['max_participants'] > 0 && $event_info['current_registrations'] >= $event_info['max_participants']) {
            $registration_error = "Sorry, this event has reached its maximum capacity.";
        } else {
            // Register for event
            $register_stmt = $db->prepare("INSERT INTO event_registrations (event_id, member_id, registration_date) VALUES (?, ?, NOW())");
            $register_stmt->bind_param("ii", $event_id, $member_id);
            
            if ($register_stmt->execute()) {
                $registration_message = "Successfully registered for the event!";
            } else {
                $registration_error = "Registration failed. Please try again.";
            }
        }
    }
}

// Handle unregistration
if (isset($_GET['unregister']) && is_numeric($_GET['unregister'])) {
    $event_id = (int)$_GET['unregister'];
    
    $unregister_stmt = $db->prepare("DELETE FROM event_registrations WHERE event_id = ? AND member_id = ?");
    $unregister_stmt->bind_param("ii", $event_id, $member_id);
    
    if ($unregister_stmt->execute()) {
        $registration_message = "You have been unregistered from the event.";
    } else {
        $registration_error = "Failed to unregister. Please try again.";
    }
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on filters
$query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrations,
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND member_id = ?) as registered
          FROM events e
          WHERE 1=1";

$params = [$member_id];
$types = "i";

// Add filter conditions
if ($filter == 'upcoming') {
    $query .= " AND e.event_date >= CURDATE()";
} elseif ($filter == 'past') {
    $query .= " AND e.event_date < CURDATE()";
} elseif ($filter == 'registered') {
    $query .= " AND e.event_id IN (SELECT event_id FROM event_registrations WHERE member_id = ?)";
    $params[] = $member_id;
    $types .= "i";
} elseif ($filter == 'available') {
    $query .= " AND e.event_date >= CURDATE() 
                AND e.event_id NOT IN (SELECT event_id FROM event_registrations WHERE member_id = ?)";
    $params[] = $member_id;
    $types .= "i";
}

// Add category filter
if ($category != 'all') {
    $query .= " AND e.event_type = ?";
    $params[] = $category;
    $types .= "s";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (e.event_name LIKE ? OR e.event_description LIKE ? OR e.location LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

// Add ordering
$query .= " ORDER BY e.event_date ASC, e.event_time ASC";

// Prepare and execute query
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$events = $stmt->get_result();

// Get unique event categories for filter
$categories = $db->query("SELECT DISTINCT event_type FROM events WHERE event_type IS NOT NULL ORDER BY event_type");

// Get member's registered events count
$registered_count = $db->prepare("SELECT COUNT(*) as count FROM event_registrations WHERE member_id = ?");
$registered_count->bind_param("i", $member_id);
$registered_count->execute();
$registered_total = $registered_count->get_result()->fetch_assoc()['count'];

// Set page title
$page_title = "Events";

// Include member header
include 'member_header.php';
?>

<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-calendar-alt me-2 text-primary"></i>Church Events</h2>
            <p class="text-muted mb-0">Browse and register for upcoming church events</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary p-3">
                <i class="fas fa-ticket-alt me-2"></i>
                <?php echo $registered_total; ?> Registered
            </span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="member-card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <select name="filter" class="form-select">
                        <option value="upcoming" <?php echo $filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                        <option value="available" <?php echo $filter == 'available' ? 'selected' : ''; ?>>Available to Register</option>
                        <option value="registered" <?php echo $filter == 'registered' ? 'selected' : ''; ?>>My Registered Events</option>
                        <option value="past" <?php echo $filter == 'past' ? 'selected' : ''; ?>>Past Events</option>
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Events</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="category" class="form-select">
                        <option value="all">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['event_type']; ?>" <?php echo $category == $cat['event_type'] ? 'selected' : ''; ?>>
                                <?php echo $cat['event_type']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search events..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-member-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Events Grid -->
    <?php if ($events->num_rows > 0): ?>
        <div class="row g-4">
            <?php while ($event = $events->fetch_assoc()): 
                $is_past = strtotime($event['event_date']) < strtotime(date('Y-m-d'));
                $is_registered = $event['registered'] > 0;
                $available_spots = $event['max_participants'] - $event['registrations'];
                $is_full = $event['max_participants'] > 0 && $available_spots <= 0;
                
                // Determine card class based on status
                $card_class = '';
                if ($is_past) $card_class = 'opacity-75';
                if ($is_registered) $card_class = 'border-success border-2';
            ?>
                <div class="col-lg-6">
                    <div class="member-card event-card <?php echo $card_class; ?>">
                        <div class="row g-0">
                            <!-- Date Sidebar -->
                            <div class="col-md-3 event-date-sidebar <?php 
                                if ($is_past) echo 'bg-secondary';
                                elseif ($is_registered) echo 'bg-success';
                                else echo 'bg-primary';
                            ?>">
                                <div class="p-3 text-center text-white">
                                    <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                    <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                    <div class="year"><?php echo date('Y', strtotime($event['event_date'])); ?></div>
                                    <div class="time mt-2 small">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Event Details -->
                            <div class="col-md-9">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title fw-bold mb-0">
                                            <?php echo htmlspecialchars($event['event_name']); ?>
                                        </h5>
                                        <?php if ($event['event_type']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <?php echo $event['event_type']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($event['event_description'])): ?>
                                        <p class="card-text text-muted small mb-3">
                                            <?php echo nl2br(htmlspecialchars(substr($event['event_description'], 0, 150))); ?>
                                            <?php if (strlen($event['event_description']) > 150): ?>...<?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="event-details mb-3">
                                        <div class="row g-2 small">
                                            <?php if ($event['location']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                                    <?php echo htmlspecialchars($event['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($event['organizer']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-user me-1 text-info"></i>
                                                    <?php echo htmlspecialchars($event['organizer']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($event['end_date'])): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-hourglass-end me-1 text-warning"></i>
                                                    Ends: <?php echo date('M d', strtotime($event['end_date'])); ?>
                                                    <?php if ($event['end_time']): ?>
                                                        <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Registration Info -->
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <?php if ($event['max_participants'] > 0): ?>
                                                <div class="registration-info">
                                                    <small class="text-muted">Capacity:</small>
                                                    <div class="progress mt-1" style="height: 5px; width: 150px;">
                                                        <?php $percentage = round(($event['registrations'] / $event['max_participants']) * 100); ?>
                                                        <div class="progress-bar <?php 
                                                            if ($percentage >= 100) echo 'bg-danger';
                                                            elseif ($percentage >= 80) echo 'bg-warning';
                                                            else echo 'bg-success';
                                                        ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo $event['registrations']; ?>/<?php echo $event['max_participants']; ?> registered
                                                        <?php if (!$is_full && !$is_past && $available_spots > 0 && $available_spots <= 10): ?>
                                                            <span class="text-warning">(Only <?php echo $available_spots; ?> spots left!)</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i>
                                                    <?php echo $event['registrations']; ?> registered
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div>
                                            <?php if ($is_past): ?>
                                                <span class="badge bg-secondary">Event Ended</span>
                                            <?php elseif ($is_registered): ?>
                                                <div class="btn-group">
                                                    <span class="btn btn-success btn-sm disabled">
                                                        <i class="fas fa-check me-1"></i>Registered
                                                    </span>
                                                    <a href="?unregister=<?php echo $event['event_id']; ?>&filter=<?php echo $filter; ?>&category=<?php echo $category; ?>&search=<?php echo urlencode($search); ?>" 
                                                       class="btn btn-outline-danger btn-sm"
                                                       onclick="return confirm('Are you sure you want to cancel your registration?')">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            <?php elseif ($is_full): ?>
                                                <span class="badge bg-danger">Full</span>
                                            <?php else: ?>
                                                <a href="?register=<?php echo $event['event_id']; ?>&filter=<?php echo $filter; ?>&category=<?php echo $category; ?>&search=<?php echo urlencode($search); ?>" 
                                                   class="btn btn-member-primary btn-sm"
                                                   onclick="return confirm('Register for this event?')">
                                                    <i class="fas fa-ticket-alt me-1"></i>Register
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Registered badge for my events view -->
                                    <?php if ($filter == 'registered' && $is_registered): ?>
                                        <div class="mt-2 text-end">
                                            <small class="text-success">
                                                <i class="fas fa-check-circle"></i>
                                                Registered on <?php 
                                                    $reg_date = $db->prepare("SELECT registration_date FROM event_registrations WHERE event_id = ? AND member_id = ?");
                                                    $reg_date->bind_param("ii", $event['event_id'], $member_id);
                                                    $reg_date->execute();
                                                    $reg_info = $reg_date->get_result()->fetch_assoc();
                                                    echo date('M d, Y', strtotime($reg_info['registration_date']));
                                                ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <!-- No Events Found -->
        <div class="member-card text-center py-5">
            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
            <h5>No events found</h5>
            <p class="text-muted mb-3">Try adjusting your filters or check back later for new events.</p>
            <a href="member_events.php" class="btn btn-member-outline">
                <i class="fas fa-redo-alt me-2"></i>Reset Filters
            </a>
        </div>
    <?php endif; ?>

    <!-- My Registrations Summary -->
    <?php if ($registered_total > 0): ?>
        <div class="member-card mt-4">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="fas fa-ticket-alt me-2 text-success"></i>My Registrations Summary</h5>
            </div>
            <div class="card-body">
                <?php
                $upcoming_reg = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM event_registrations er
                    JOIN events e ON er.event_id = e.event_id
                    WHERE er.member_id = ? AND e.event_date >= CURDATE()
                ");
                $upcoming_reg->bind_param("i", $member_id);
                $upcoming_reg->execute();
                $upcoming_count = $upcoming_reg->get_result()->fetch_assoc()['count'];
                
                $past_reg = $registered_total - $upcoming_count;
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $upcoming_count; ?></h3>
                        <small class="text-muted">Upcoming Events</small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-secondary"><?php echo $past_reg; ?></h3>
                        <small class="text-muted">Past Events</small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add to Calendar Modal -->
<div class="modal fade" id="calendarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add to Calendar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Choose calendar type:</p>
                <div class="d-grid gap-2">
                    <a href="#" class="btn btn-outline-primary" id="googleCal">
                        <i class="fab fa-google me-2"></i>Google Calendar
                    </a>
                    <a href="#" class="btn btn-outline-primary" id="outlookCal">
                        <i class="fab fa-windows me-2"></i>Outlook Calendar
                    </a>
                    <a href="#" class="btn btn-outline-primary" id="appleCal">
                        <i class="fab fa-apple me-2"></i>Apple Calendar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Event card specific styles */
.event-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.event-date-sidebar {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 180px;
}

.event-date-sidebar.bg-primary {
    background: linear-gradient(135deg, #4361ee, #3046c0) !important;
}

.event-date-sidebar.bg-success {
    background: linear-gradient(135deg, #10b981, #059669) !important;
}

.event-date-sidebar.bg-secondary {
    background: linear-gradient(135deg, #6c757d, #495057) !important;
}

.event-date-sidebar .month {
    font-size: 1.2rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.event-date-sidebar .day {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin: 5px 0;
}

.event-date-sidebar .year {
    font-size: 1rem;
    opacity: 0.9;
}

/* Progress bar */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

/* Responsive */
@media (max-width: 768px) {
    .event-date-sidebar {
        min-height: 100px;
        padding: 15px !important;
    }
    
    .event-date-sidebar .day {
        font-size: 2rem;
    }
    
    .event-date-sidebar .month,
    .event-date-sidebar .year {
        display: inline-block;
        margin: 0 5px;
    }
    
    .event-date-sidebar .time {
        display: inline-block;
        margin-left: 10px;
    }
}

/* Animation for new events */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.border-success {
    animation: pulse 2s infinite;
}

/* Capacity warnings */
.text-warning {
    font-weight: 500;
}

/* Hover effects */
.btn-group .btn {
    transition: all 0.3s ease;
}

.btn-group .btn:hover {
    transform: translateY(-2px);
}
</style>

<script>
// Add to calendar functionality
function addToCalendar(eventId, eventName, eventDate, eventTime, eventLocation, eventDescription) {
    // Format date for calendar
    const date = new Date(eventDate + 'T' + eventTime);
    const endDate = new Date(date.getTime() + (2 * 60 * 60 * 1000)); // Assume 2 hours duration
    
    const formatDate = (d) => d.toISOString().replace(/-|:|\.\d+/g, '');
    
    // Google Calendar link
    const googleUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(eventName)}&dates=${formatDate(date)}/${formatDate(endDate)}&details=${encodeURIComponent(eventDescription)}&location=${encodeURIComponent(eventLocation)}`;
    
    // Set modal links
    document.getElementById('googleCal').href = googleUrl;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('calendarModal')).show();
}

// Quick filter buttons
document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelector('select[name="filter"]').value = this.dataset.filter;
        document.querySelector('form').submit();
    });
});

// Auto-submit on filter change
document.querySelector('select[name="filter"]').addEventListener('change', function() {
    this.form.submit();
});

document.querySelector('select[name="category"]').addEventListener('change', function() {
    this.form.submit();
});

// Search with debounce
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Highlight today's events
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toDateString();
    document.querySelectorAll('.event-card').forEach(card => {
        // Could add today highlighting if needed
    });
});
</script>

<?php
// Include member footer
include 'member_footer.php';
?>