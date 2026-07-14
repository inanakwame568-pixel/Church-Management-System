<?php
// events.php - Public Events Page
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : 'upcoming';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 9;
$offset = ($page - 1) * $records_per_page;

// Build the query
$query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrations,
                 CASE 
                    WHEN e.event_date < CURDATE() THEN 'Past'
                    WHEN e.event_date = CURDATE() THEN 'Today'
                    ELSE 'Upcoming'
                 END as date_status
          FROM events e
          WHERE e.status = 'Active'";

$count_query = "SELECT COUNT(*) as total FROM events WHERE status = 'Active'";
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $query .= " AND (e.event_name LIKE ? OR e.event_description LIKE ? OR e.location LIKE ?)";
    $count_query .= " AND (event_name LIKE ? OR event_description LIKE ? OR location LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

// Add type filter
if (!empty($type_filter)) {
    $query .= " AND e.event_type = ?";
    $count_query .= " AND event_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

// Add date filter
switch ($date_filter) {
    case 'upcoming':
        $query .= " AND e.event_date >= CURDATE()";
        $count_query .= " AND event_date >= CURDATE()";
        break;
    case 'past':
        $query .= " AND e.event_date < CURDATE()";
        $count_query .= " AND event_date < CURDATE()";
        break;
    case 'today':
        $query .= " AND e.event_date = CURDATE()";
        $count_query .= " AND event_date = CURDATE()";
        break;
    case 'week':
        $query .= " AND e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $count_query .= " AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $query .= " AND MONTH(e.event_date) = MONTH(CURDATE()) AND YEAR(e.event_date) = YEAR(CURDATE())";
        $count_query .= " AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())";
        break;
}

// Add ordering
$query .= " ORDER BY e.event_date ASC, e.event_time ASC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the main query
$stmt = $db->prepare($query);

if (!$stmt) {
    die("Error preparing query: " . $db->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

// Get total records for pagination
$count_stmt = $db->prepare($count_query);
if (!$count_stmt) {
    die("Error preparing count query: " . $db->error);
}

if (!empty($params)) {
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get upcoming events for sidebar
$upcoming_events = $db->query("
    SELECT event_id, event_name, event_date, event_time, location
    FROM events 
    WHERE event_date >= CURDATE() AND status = 'Active'
    ORDER BY event_date ASC 
    LIMIT 5
");

// Get event types for filter
$types_result = $db->query("SELECT DISTINCT event_type FROM events WHERE event_type IS NOT NULL AND event_type != '' AND status = 'Active' ORDER BY event_type");
$event_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $event_types[] = $row['event_type'];
    }
}

// Get featured/upcoming events count for stats
$stats = [];
$stats['upcoming'] = $db->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE() AND status = 'Active'")->fetch_assoc()['count'];
$stats['total'] = $db->query("SELECT COUNT(*) as count FROM events WHERE status = 'Active'")->fetch_assoc()['count'];

// Set page title
$page_title = "Events";

// Include header
include 'header.php';
?>

<!-- Hero Section -->
<section class="events-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold mb-4">Upcoming Events</h1>
                <p class="lead mb-4">Join us for worship, fellowship, and community activities</p>
                <?php if ($stats['upcoming'] > 0): ?>
                    <div class="d-flex justify-content-center gap-3">
                        <span class="badge bg-primary p-3">
                            <i class="fas fa-calendar-week me-2"></i><?php echo $stats['upcoming']; ?> Upcoming Events
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<section class="events-section py-5">
    <div class="container">
        <!-- Filter Bar -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control border-start-0 ps-0" 
                                           name="search" 
                                           placeholder="Search events by name, description, or location..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <select name="date_range" class="form-select">
                                    <option value="upcoming" <?php echo $date_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                                    <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                                    <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="past" <?php echo $date_filter == 'past' ? 'selected' : ''; ?>>Past Events</option>
                                    <option value="all" <?php echo $date_filter == 'all' ? 'selected' : ''; ?>>All Events</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <?php foreach ($event_types as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="row g-4">
            <?php if (empty($events)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                        <h5>No events found</h5>
                        <p class="text-muted">Try adjusting your filters or check back later for new events.</p>
                        <a href="events.php" class="btn btn-outline-primary mt-3">View All Events</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): 
                    $is_past = strtotime($event['event_date']) < strtotime(date('Y-m-d'));
                    $is_today = $event['event_date'] == date('Y-m-d');
                    $is_full = ($event['max_participants'] > 0 && $event['registrations'] >= $event['max_participants']);
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="event-card h-100">
                            <div class="event-date-badge <?php 
                                echo $is_past ? 'bg-secondary' : ($is_today ? 'bg-warning' : 'bg-primary'); 
                            ?>">
                                <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title fw-bold"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                    <?php if ($event['event_type']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($event['event_type']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-meta mb-3">
                                    <div class="mb-2">
                                        <i class="fas fa-clock me-2 text-primary"></i>
                                        <span><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                        <?php if (!empty($event['end_time'])): ?>
                                            <span> - <?php echo date('g:i A', strtotime($event['end_time'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($event['location']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-map-marker-alt me-2 text-danger"></i>
                                            <span><?php echo htmlspecialchars($event['location']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($event['organizer']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-user me-2 text-info"></i>
                                            <span><?php echo htmlspecialchars($event['organizer']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($event['event_description'])): ?>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars(substr($event['event_description'], 0, 100)); ?>
                                        <?php echo strlen($event['event_description']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Capacity Indicator -->
                                <?php if ($event['max_participants'] > 0 && !$is_past): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>Capacity</span>
                                            <span><?php echo $event['registrations']; ?>/<?php echo $event['max_participants']; ?></span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <?php $percentage = round(($event['registrations'] / $event['max_participants']) * 100); ?>
                                            <div class="progress-bar <?php 
                                                echo $percentage >= 100 ? 'bg-danger' : ($percentage >= 80 ? 'bg-warning' : 'bg-success'); 
                                            ?>" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex gap-2 mt-3">
                                    <?php if ($is_past): ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-history me-2"></i>Event Ended
                                        </button>
                                    <?php elseif ($is_full): ?>
                                        <button class="btn btn-danger w-100" disabled>
                                            <i class="fas fa-ban me-2"></i>Event Full
                                        </button>
                                    <?php else: ?>
                                        <a href="event_register.php?id=<?php echo $event['event_id']; ?>" class="btn btn-primary w-100">
                                            <i class="fas fa-ticket-alt me-2"></i>Register
                                        </a>
                                    <?php endif; ?>
                                    <a href="event_details.php?id=<?php echo $event['event_id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo urlencode($date_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo urlencode($date_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo urlencode($date_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Upcoming Events Sidebar Section (for larger screens) -->
<section class="upcoming-sidebar d-none d-lg-block">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="quick-events">
                    <h5 class="mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Quick View - Upcoming Events</h5>
                    <div class="row g-3">
                        <?php 
                        $quick_events = $db->query("
                            SELECT event_id, event_name, event_date, event_time, location
                            FROM events 
                            WHERE event_date >= CURDATE() AND status = 'Active'
                            ORDER BY event_date ASC 
                            LIMIT 3
                        ");
                        while ($qevent = $quick_events->fetch_assoc()):
                        ?>
                        <div class="col-md-4">
                            <div class="quick-event-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($qevent['event_name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><?php echo date('M d', strtotime($qevent['event_date'])); ?>
                                            <i class="fas fa-clock ms-2 me-1"></i><?php echo date('g:i A', strtotime($qevent['event_time'])); ?>
                                        </small>
                                    </div>
                                    <a href="event_details.php?id=<?php echo $qevent['event_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Events page specific styles */
.events-hero {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.9), rgba(6, 182, 212, 0.9)), 
                url('assets/images/events-hero.jpg') center/cover;
    color: white;
    padding: 80px 0;
    margin-top: -20px;
}

/* Event Card */
.event-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    position: relative;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
}

.event-date-badge {
    position: relative;
    top: 15px;
    left: 15px;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    z-index: 1;
}

.event-date-badge .month {
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.event-date-badge .day {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.event-card .card-body {
    padding: 20px;
    padding-top: 30px;
}

.event-meta {
    font-size: 0.85rem;
    color: #6c757d;
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

/* Quick events section */
.quick-events {
    background: #f8fafc;
    border-radius: 16px;
    padding: 25px;
    margin-top: 30px;
}

.quick-event-card {
    background: white;
    border-radius: 12px;
    padding: 12px 15px;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.quick-event-card:hover {
    transform: translateX(5px);
    border-color: #4361ee;
}

/* Filter bar */
.form-control, .form-select {
    border: 2px solid #e9ecef;
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

/* Pagination */
.pagination .page-link {
    color: #4361ee;
    border-radius: 8px;
    margin: 0 2px;
    border: none;
}

.pagination .page-item.active .page-link {
    background-color: #4361ee;
    color: white;
}

.pagination .page-item.disabled .page-link {
    color: #adb5bd;
}

/* Responsive */
@media (max-width: 768px) {
    .events-hero {
        padding: 60px 0;
    }
    
    .events-hero h1 {
        font-size: 2rem;
    }
    
    .event-card {
        margin-bottom: 15px;
    }
    
    .btn {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.event-card {
    animation: fadeInUp 0.5s ease-out forwards;
}

/* Loading skeleton */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>

<script>
// Live search with debounce
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});

// Auto-submit on filter change
document.querySelector('select[name="date_range"]').addEventListener('change', function() {
    this.form.submit();
});

document.querySelector('select[name="type"]').addEventListener('change', function() {
    this.form.submit();
});

// Add animation to event cards
document.querySelectorAll('.event-card').forEach((card, index) => {
    card.style.animationDelay = `${index * 0.05}s`;
});

// Load more events with AJAX (optional)
let loading = false;
let currentPage = <?php echo $page; ?>;
let hasMore = <?php echo $total_pages > $page ? 'true' : 'false'; ?>;

window.addEventListener('scroll', function() {
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
        if (!loading && hasMore) {
            loadMoreEvents();
        }
    }
});

function loadMoreEvents() {
    loading = true;
    currentPage++;
    
    const formData = new FormData();
    formData.append('page', currentPage);
    formData.append('search', '<?php echo addslashes($search); ?>');
    formData.append('date_range', '<?php echo $date_filter; ?>');
    formData.append('type', '<?php echo $type_filter; ?>');
    
    fetch('ajax/load_more_events.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        if (html.trim()) {
            document.querySelector('.row.g-4').insertAdjacentHTML('beforeend', html);
            loading = false;
        } else {
            hasMore = false;
        }
    });
}

// Calendar view toggle (optional)
let viewMode = 'grid';

function toggleView() {
    const container = document.querySelector('.row.g-4');
    const toggleBtn = document.querySelector('.view-toggle');
    
    if (viewMode === 'grid') {
        container.classList.remove('row-cols-1', 'row-cols-md-2', 'row-cols-lg-3');
        container.classList.add('flex-column');
        toggleBtn.innerHTML = '<i class="fas fa-th-large me-1"></i>Grid View';
        viewMode = 'list';
    } else {
        container.classList.remove('flex-column');
        container.classList.add('row-cols-1', 'row-cols-md-2', 'row-cols-lg-3');
        toggleBtn.innerHTML = '<i class="fas fa-list me-1"></i>List View';
        viewMode = 'grid';
    }
}

// Add view toggle button if desired
const filterCard = document.querySelector('.card-body');
if (filterCard && document.querySelector('.row.g-4')) {
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'btn btn-sm btn-outline-secondary view-toggle';
    toggleBtn.innerHTML = '<i class="fas fa-list me-1"></i>List View';
    toggleBtn.onclick = toggleView;
    filterCard.querySelector('.row.g-3')?.appendChild(toggleBtn);
}

// Share event function
function shareEvent(eventId, eventName) {
    if (navigator.share) {
        navigator.share({
            title: eventName,
            text: 'Check out this event at our church!',
            url: window.location.origin + '/event_details.php?id=' + eventId
        });
    } else {
        // Fallback
        prompt('Copy this link to share:', window.location.origin + '/event_details.php?id=' + eventId);
    }
}

// Add to calendar function
function addToCalendar(eventId, eventName, eventDate, eventTime, eventLocation) {
    const startDate = new Date(eventDate + 'T' + eventTime);
    const endDate = new Date(startDate.getTime() + (2 * 60 * 60 * 1000));
    
    const formatDate = (date) => {
        return date.toISOString().replace(/-|:|\.\d+/g, '');
    };
    
    const googleUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(eventName)}&dates=${formatDate(startDate)}/${formatDate(endDate)}&location=${encodeURIComponent(eventLocation)}`;
    
    window.open(googleUrl, '_blank');
}
</script>

<?php
// Include footer
include 'footer.php';
?>