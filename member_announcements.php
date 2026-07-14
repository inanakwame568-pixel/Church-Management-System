<?php
// member_announcements.php - Member Announcements View
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

// Handle announcement read status (mark as read)
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $announcement_id = (int)$_GET['mark_read'];
    
    // Check if read record exists
    $check_stmt = $db->prepare("SELECT read_id FROM announcement_reads WHERE announcement_id = ? AND member_id = ?");
    $check_stmt->bind_param("ii", $announcement_id, $member_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        $insert_stmt = $db->prepare("INSERT INTO announcement_reads (announcement_id, member_id, read_at) VALUES (?, ?, NOW())");
        $insert_stmt->bind_param("ii", $announcement_id, $member_id);
        $insert_stmt->execute();
    }
    
    header('Location: member_announcements.php');
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    // Get all unread announcements
    $unread_query = "SELECT announcement_id FROM announcements 
                     WHERE announcement_id NOT IN (
                         SELECT announcement_id FROM announcement_reads WHERE member_id = ?
                     ) AND (target_audience = 'all' OR target_audience = 'members')";
    $unread_stmt = $db->prepare($unread_query);
    $unread_stmt->bind_param("i", $member_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    
    while ($row = $unread_result->fetch_assoc()) {
        $insert_stmt = $db->prepare("INSERT INTO announcement_reads (announcement_id, member_id, read_at) VALUES (?, ?, NOW())");
        $insert_stmt->bind_param("ii", $row['announcement_id'], $member_id);
        $insert_stmt->execute();
    }
    
    header('Location: member_announcements.php');
    exit();
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on filters
$query = "SELECT a.*, 
                 u.full_name as author_name,
                 (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.announcement_id) as read_count,
                 CASE WHEN ar.read_id IS NOT NULL THEN 1 ELSE 0 END as is_read,
                 DATEDIFF(NOW(), a.created_at) as days_ago,
                 CASE 
                    WHEN a.priority = 'urgent' THEN 1
                    WHEN a.priority = 'high' THEN 2
                    WHEN a.priority = 'normal' THEN 3
                    ELSE 4
                 END as priority_order
          FROM announcements a
          LEFT JOIN users u ON a.created_by = u.user_id
          LEFT JOIN announcement_reads ar ON a.announcement_id = ar.announcement_id AND ar.member_id = ?
          WHERE (a.target_audience = 'all' OR a.target_audience = 'members')
          AND a.status = 'published'";

$params = [$member_id];
$types = "i";

// Add date filter
if ($filter == 'recent') {
    $query .= " AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter == 'unread') {
    $query .= " AND ar.read_id IS NULL";
} elseif ($filter == 'urgent') {
    $query .= " AND a.priority = 'urgent'";
}

// Add category filter
if ($category != 'all') {
    $query .= " AND a.category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Add ordering
$query .= " ORDER BY priority_order, a.created_at DESC";

// Prepare and execute query
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$announcements = $stmt->get_result();

// Get pinned announcements (always show first)
$pinned_query = "SELECT a.*, u.full_name as author_name,
                        CASE WHEN ar.read_id IS NOT NULL THEN 1 ELSE 0 END as is_read
                 FROM announcements a
                 LEFT JOIN users u ON a.created_by = u.user_id
                 LEFT JOIN announcement_reads ar ON a.announcement_id = ar.announcement_id AND ar.member_id = ?
                 WHERE a.is_pinned = 1 
                 AND a.status = 'published'
                 AND (a.target_audience = 'all' OR a.target_audience = 'members')
                 ORDER BY a.created_at DESC";
$pinned_stmt = $db->prepare($pinned_query);
$pinned_stmt->bind_param("i", $member_id);
$pinned_stmt->execute();
$pinned_announcements = $pinned_stmt->get_result();

// Get announcement categories for filter
$categories = $db->query("SELECT DISTINCT category FROM announcements WHERE category IS NOT NULL ORDER BY category");

// Get statistics
$stats = [];

// Total announcements
$total_query = "SELECT COUNT(*) as count FROM announcements WHERE status = 'published' AND (target_audience = 'all' OR target_audience = 'members')";
$total_result = $db->query($total_query);
$stats['total'] = $total_result->fetch_assoc()['count'];

// Unread count
$unread_query = "SELECT COUNT(*) as count FROM announcements a
                 WHERE a.status = 'published' 
                 AND (a.target_audience = 'all' OR a.target_audience = 'members')
                 AND a.announcement_id NOT IN (
                     SELECT announcement_id FROM announcement_reads WHERE member_id = ?
                 )";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->bind_param("i", $member_id);
$unread_stmt->execute();
$stats['unread'] = $unread_stmt->get_result()->fetch_assoc()['count'];

// Urgent count
$urgent_query = "SELECT COUNT(*) as count FROM announcements 
                 WHERE priority = 'urgent' AND status = 'published' 
                 AND (target_audience = 'all' OR target_audience = 'members')";
$urgent_result = $db->query($urgent_query);
$stats['urgent'] = $urgent_result->fetch_assoc()['count'];

// Set page title
$page_title = "Announcements";

// Include member header
include 'member_header.php';
?>

<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-bullhorn me-2 text-primary"></i>Church Announcements</h2>
            <p class="text-muted mb-0">Stay updated with the latest news and events</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($stats['unread'] > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-outline-primary" onclick="return confirm('Mark all announcements as read?')">
                    <i class="fas fa-check-double me-2"></i>Mark All Read
                </a>
            <?php endif; ?>
            <span class="badge bg-<?php echo $stats['unread'] > 0 ? 'danger' : 'success'; ?> p-3">
                <i class="fas fa-envelope me-2"></i>
                <?php echo $stats['unread']; ?> Unread
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Announcements</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number text-<?php echo $stats['unread'] > 0 ? 'danger' : 'success'; ?>">
                    <?php echo $stats['unread']; ?>
                </div>
                <div class="stat-label">Unread</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number text-warning"><?php echo $stats['urgent']; ?></div>
                <div class="stat-label">Urgent</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="member-card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <select name="filter" class="form-select">
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Announcements</option>
                        <option value="unread" <?php echo $filter == 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                        <option value="recent" <?php echo $filter == 'recent' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="urgent" <?php echo $filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="all">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category']; ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo $cat['category']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search announcements..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-member-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pinned Announcements Section -->
    <?php if ($pinned_announcements->num_rows > 0): ?>
        <div class="mb-4">
            <h5 class="mb-3"><i class="fas fa-thumbtack me-2 text-danger"></i>Pinned Announcements</h5>
            <?php while ($announcement = $pinned_announcements->fetch_assoc()): ?>
                <div class="member-card pinned-announcement mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="pin-icon me-3">
                                <i class="fas fa-thumbtack fa-2x text-danger"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                        <?php if (!$announcement['is_read']): ?>
                                            <span class="badge bg-danger ms-2">New</span>
                                        <?php endif; ?>
                                    </h5>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="announcement-content mb-3">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($announcement['author_name'] ?? 'Administrator'); ?>
                                        </small>
                                        <?php if ($announcement['category']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info ms-2">
                                                <?php echo $announcement['category']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$announcement['is_read']): ?>
                                        <a href="?mark_read=<?php echo $announcement['announcement_id']; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-check me-1"></i>Mark as Read
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <!-- Regular Announcements -->
    <h5 class="mb-3">Latest Announcements</h5>
    
    <?php if ($announcements->num_rows > 0): ?>
        <div class="row g-4">
            <?php while ($announcement = $announcements->fetch_assoc()): 
                $priority_class = '';
                $priority_icon = '';
                
                if ($announcement['priority'] == 'urgent') {
                    $priority_class = 'urgent-announcement';
                    $priority_icon = '<i class="fas fa-exclamation-circle text-danger me-2"></i>';
                } elseif ($announcement['priority'] == 'high') {
                    $priority_class = 'high-announcement';
                    $priority_icon = '<i class="fas fa-arrow-up text-warning me-2"></i>';
                } else {
                    $priority_icon = '<i class="fas fa-info-circle text-info me-2"></i>';
                }
            ?>
                <div class="col-12">
                    <div class="member-card announcement-card <?php echo $priority_class; ?> <?php echo !$announcement['is_read'] ? 'unread' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?php echo $priority_icon; ?>
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                        <?php if ($announcement['priority'] == 'urgent'): ?>
                                            <span class="badge bg-danger ms-2">URGENT</span>
                                        <?php endif; ?>
                                        <?php if (!$announcement['is_read']): ?>
                                            <span class="badge bg-primary ms-2">New</span>
                                        <?php endif; ?>
                                    </h5>
                                    <div class="d-flex gap-3 small text-muted mb-2">
                                        <span>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php 
                                            $days = $announcement['days_ago'];
                                            if ($days == 0) echo 'Today';
                                            elseif ($days == 1) echo 'Yesterday';
                                            else echo $days . ' days ago';
                                            ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($announcement['author_name'] ?? 'Administrator'); ?>
                                        </span>
                                        <?php if ($announcement['category']): ?>
                                            <span>
                                                <i class="fas fa-tag me-1"></i>
                                                <?php echo $announcement['category']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <span>
                                            <i class="fas fa-eye me-1"></i>
                                            <?php echo $announcement['read_count']; ?> reads
                                        </span>
                                    </div>
                                </div>
                                <?php if (!$announcement['is_read']): ?>
                                    <a href="?mark_read=<?php echo $announcement['announcement_id']; ?>&filter=<?php echo $filter; ?>&category=<?php echo $category; ?>&search=<?php echo urlencode($search); ?>" 
                                       class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                            </div>
                            
                            <?php if (!empty($announcement['link'])): ?>
                                <div class="mt-3">
                                    <a href="<?php echo htmlspecialchars($announcement['link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt me-1"></i>Learn More
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="member-card text-center py-5">
            <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
            <h5>No announcements found</h5>
            <p class="text-muted mb-3">Check back later for updates and news.</p>
            <a href="member_announcements.php" class="btn btn-member-outline">
                <i class="fas fa-redo-alt me-2"></i>Reset Filters
            </a>
        </div>
    <?php endif; ?>

    <!-- Subscribe Section -->
    <div class="member-card mt-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1"><i class="fas fa-bell me-2 text-warning"></i>Stay Updated</h5>
                    <p class="text-muted mb-md-0">Get notifications for new announcements and urgent updates.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-member-primary" onclick="subscribeToNotifications()">
                        <i class="fas fa-bell me-2"></i>Subscribe to Updates
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Announcement Detail Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Announcement card styles */
.announcement-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-left: 4px solid transparent;
}

.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.announcement-card.unread {
    border-left-color: #4361ee;
    background: #f0f5ff;
}

.announcement-card.urgent-announcement {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.announcement-card.high-announcement {
    border-left-color: #fd7e14;
}

/* Pinned announcement */
.pinned-announcement {
    background: linear-gradient(to right, #fff9e6, white);
    border: 1px solid #ffd966;
}

.pin-icon {
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

/* Announcement content */
.announcement-content {
    font-size: 1rem;
    line-height: 1.6;
    color: #2d3748;
    white-space: pre-line;
}

/* Priority badges */
.badge.bg-danger {
    background: #dc3545 !important;
}

.badge.bg-warning {
    background: #fd7e14 !important;
}

/* Read status */
.read-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.read-indicator.unread {
    background: #4361ee;
    box-shadow: 0 0 5px #4361ee;
}

/* Responsive */
@media (max-width: 768px) {
    .announcement-card .d-flex {
        flex-direction: column;
        gap: 10px;
    }
    
    .announcement-card .d-flex .btn {
        align-self: flex-start;
    }
    
    .d-flex.gap-3 {
        flex-wrap: wrap;
        gap: 0.5rem !important;
    }
}

/* Animation for new announcements */
@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.announcement-card {
    animation: fadeInLeft 0.3s ease-out;
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

/* Print styles */
@media print {
    .btn, .filter-bar, .subscribe-section {
        display: none !important;
    }
    
    .announcement-card {
        break-inside: avoid;
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>

<script>
// Mark announcement as read
function markAsRead(announcementId) {
    fetch('ajax/mark_announcement_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ announcement_id: announcementId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const card = document.querySelector(`[data-announcement-id="${announcementId}"]`);
            if (card) {
                card.classList.remove('unread');
                const badge = card.querySelector('.badge.bg-primary');
                if (badge) badge.remove();
            }
            
            // Update unread count
            updateUnreadCount();
        }
    });
}

// Update unread count in header
function updateUnreadCount() {
    fetch('ajax/get_unread_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.badge.bg-danger.p-3');
            if (badge) {
                badge.innerHTML = `<i class="fas fa-envelope me-2"></i>${data.unread} Unread`;
                if (data.unread == 0) {
                    badge.className = 'badge bg-success p-3';
                }
            }
        });
}

// Subscribe to notifications
function subscribeToNotifications() {
    if (!('Notification' in window)) {
        alert('This browser does not support desktop notifications');
        return;
    }
    
    Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
            alert('Notifications enabled! You will receive updates for new announcements.');
        }
    });
}

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

// Auto-submit on filter change
document.querySelector('select[name="filter"]').addEventListener('change', function() {
    this.form.submit();
});

document.querySelector('select[name="category"]').addEventListener('change', function() {
    this.form.submit();
});

// View announcement details in modal
function viewAnnouncement(title, content, author, date) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalContent').innerHTML = `
        <p class="text-muted small">
            <i class="fas fa-user me-1"></i>${author}
            <span class="mx-2">|</span>
            <i class="fas fa-calendar me-1"></i>${date}
        </p>
        <div class="mt-3">${content.replace(/\n/g, '<br>')}</div>
    `;
    
    new bootstrap.Modal(document.getElementById('announcementModal')).show();
}

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Highlight new announcements
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to new announcements
    document.querySelectorAll('.announcement-card.unread').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // 'M' key to mark all as read
    if (e.key === 'm' && e.ctrlKey) {
        e.preventDefault();
        if (confirm('Mark all announcements as read?')) {
            window.location.href = '?mark_all_read=1';
        }
    }
    
    // 'U' key to show unread only
    if (e.key === 'u' && e.ctrlKey) {
        e.preventDefault();
        window.location.href = '?filter=unread';
    }
});

// Copy announcement link
function copyLink(announcementId) {
    const url = window.location.origin + '/member_announcements.php#announcement-' + announcementId;
    navigator.clipboard.writeText(url).then(() => {
        alert('Link copied to clipboard!');
    });
}

// Updated loadMoreAnnouncements function
let page = 1;
let loading = false;
let hasMore = true;

function loadMoreAnnouncements() {
    if (loading || !hasMore) return;
    
    loading = true;
    page++;
    
    // Get current filter values
    const filter = document.querySelector('select[name="filter"]')?.value || 'all';
    const category = document.querySelector('select[name="category"]')?.value || 'all';
    const search = document.querySelector('input[name="search"]')?.value || '';
    
    // Show loading indicator
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadMoreText = document.getElementById('load-more-text');
    const loadMoreSpinner = document.getElementById('load-more-spinner');
    
    if (loadMoreText) loadMoreText.style.display = 'none';
    if (loadMoreSpinner) loadMoreSpinner.style.display = 'inline-block';
    
    // Make AJAX request
    fetch(`ajax/get_more_announcements.php?page=${page}&filter=${encodeURIComponent(filter)}&category=${encodeURIComponent(category)}&search=${encodeURIComponent(search)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.html) {
                // Insert new announcements
                const container = document.querySelector('#announcementsContainer');
                if (container) {
                    container.insertAdjacentHTML('beforeend', data.html);
                } else {
                    document.querySelector('.row.g-4')?.insertAdjacentHTML('beforeend', data.html);
                }
            }
            
            hasMore = data.has_more;
            
            if (!hasMore) {
                const loadMoreDiv = document.querySelector('.col-12.text-center.mt-4');
                if (loadMoreDiv) {
                    loadMoreDiv.innerHTML = '<p class="text-muted">No more announcements to load</p>';
                }
            }
        } else {
            console.error('Error loading announcements:', data.error);
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        }
        
        // Reset loading state
        loading = false;
        if (loadMoreText) loadMoreText.style.display = 'inline';
        if (loadMoreSpinner) loadMoreSpinner.style.display = 'none';
    })
    .catch(error => {
        console.error('Fetch error:', error);
        loading = false;
        if (loadMoreText) loadMoreText.style.display = 'inline';
        if (loadMoreSpinner) loadMoreSpinner.style.display = 'none';
        
        // Show user-friendly error
        const container = document.querySelector('.row.g-4');
        if (container && !document.querySelector('.alert-danger')) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'col-12';
            errorDiv.innerHTML = '<div class="alert alert-danger">Failed to load more announcements. Please refresh the page.</div>';
            container.appendChild(errorDiv);
        }
    });
}

// Mark as read function
function markAsRead(announcementId) {
    fetch('ajax/mark_announcement_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ announcement_id: announcementId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const item = document.querySelector(`.announcement-item[data-announcement-id="${announcementId}"]`);
            if (item) {
                const card = item.querySelector('.announcement-card');
                if (card) card.classList.remove('unread');
                const newBadge = item.querySelector('.badge.bg-primary');
                if (newBadge) newBadge.remove();
                const markReadBtn = item.querySelector('.mark-read-btn');
                if (markReadBtn) markReadBtn.remove();
            }
            
            // Update unread count
            updateUnreadCount();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Update unread count function
function updateUnreadCount() {
    fetch('ajax/get_unread_count.php', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.badge.bg-danger.p-3, .badge.bg-success.p-3');
            if (badge) {
                badge.innerHTML = `<i class="fas fa-envelope me-2"></i>${data.unread} Unread`;
                if (data.unread == 0) {
                    badge.className = 'badge bg-success p-3';
                } else {
                    badge.className = 'badge bg-danger p-3';
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Infinite scroll with debounce
let scrollTimeout;
window.addEventListener('scroll', function() {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 500) {
            if (!loading && hasMore) {
                loadMoreAnnouncements();
            }
        }
    }, 100);
});

// Reset infinite scroll when filters change
document.querySelector('select[name="filter"]')?.addEventListener('change', function() {
    page = 1;
    hasMore = true;
    loading = false;
    // Clear existing announcements container except first page
    const container = document.querySelector('.row.g-4');
    if (container) {
        const items = container.querySelectorAll('.col-12.announcement-item');
        items.forEach((item, index) => {
            if (index >= 5) item.remove(); // Keep only first 5 (initial load)
        });
    }
});

document.querySelector('select[name="category"]')?.addEventListener('change', function() {
    page = 1;
    hasMore = true;
    loading = false;
});

document.querySelector('input[name="search"]')?.addEventListener('keyup', function() {
    page = 1;
    hasMore = true;
    loading = false;
});

// Add announcements container ID for easier targeting
document.addEventListener('DOMContentLoaded', function() {
    const announcementsGrid = document.querySelector('.row.g-4');
    if (announcementsGrid && !announcementsGrid.id) {
        announcementsGrid.id = 'announcementsContainer';
    }
});

}
</script>

<!-- Create announcements table if needed -->
<?php
// Check if announcements table exists
$table_check = $db->query("SHOW TABLES LIKE 'announcements'");
if ($table_check->num_rows == 0):
?>
<!-- Hidden message to create table -->
<div class="alert alert-info mt-4">
    <i class="fas fa-database me-2"></i>
    To enable announcements, create the required tables. 
    <button class="btn btn-sm btn-primary ms-3" onclick="createAnnouncementTables()">Create Tables</button>
</div>

<script>
function createAnnouncementTables() {
    if (confirm('Create announcements tables? This will set up the announcement system.')) {
        window.location.href = 'admin/setup_announcements.php';
    }
}
</script>
<?php endif; ?>

<?php
// Include member footer
include 'member_footer.php';
?>