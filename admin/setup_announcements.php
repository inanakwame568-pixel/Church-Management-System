<?php
// admin/setup_announcements.php - Create announcement tables
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require admin login
requireLogin();
if (!isAdmin()) {
    die('Access denied. Admin only.');
}

$db = Database::getInstance()->getConnection();
$messages = [];
$errors = [];

// Create announcements table
$announcements_table = "CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100),
    priority ENUM('normal', 'high', 'urgent') DEFAULT 'normal',
    is_pinned BOOLEAN DEFAULT FALSE,
    link VARCHAR(500),
    target_audience ENUM('all', 'members', 'leaders', 'admin') DEFAULT 'all',
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($announcements_table)) {
    $messages[] = "✅ Announcements table created successfully";
} else {
    $errors[] = "❌ Error creating announcements table: " . $db->error;
}

// Create announcement reads table
$reads_table = "CREATE TABLE IF NOT EXISTS announcement_reads (
    read_id INT PRIMARY KEY AUTO_INCREMENT,
    announcement_id INT NOT NULL,
    member_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (announcement_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($reads_table)) {
    $messages[] = "✅ Announcement reads table created successfully";
} else {
    $errors[] = "❌ Error creating announcement reads table: " . $db->error;
}

// Insert sample announcements
$sample_announcements = [
    [
        'title' => 'Welcome to Our New Member Portal',
        'content' => 'We are excited to announce our new member portal where you can manage your profile, view events, and connect with groups. Explore the new features today!',
        'category' => 'Announcement',
        'priority' => 'high',
        'is_pinned' => 1,
        'target_audience' => 'all'
    ],
    [
        'title' => 'Easter Service Schedule',
        'content' => "Join us for our Easter services:\n\n• Good Friday - 7:00 PM\n• Easter Sunday - 8:00 AM, 10:00 AM, 12:00 PM\n\nAll are welcome!",
        'category' => 'Events',
        'priority' => 'urgent',
        'is_pinned' => 1,
        'target_audience' => 'all'
    ],
    [
        'title' => 'Food Drive This Weekend',
        'content' => 'Bring canned goods and non-perishable items to support our local food bank. Collection bins are in the lobby.',
        'category' => 'Ministry',
        'priority' => 'normal',
        'is_pinned' => 0,
        'target_audience' => 'all'
    ],
    [
        'title' => 'Youth Retreat Registration Open',
        'content' => 'Registration for the summer youth retreat is now open. Early bird pricing ends May 1st. Visit the youth table after service for more information.',
        'category' => 'Youth',
        'priority' => 'high',
        'is_pinned' => 0,
        'target_audience' => 'members'
    ],
    [
        'title' => 'Wednesday Night Bible Study',
        'content' => 'Join us for a new study on the Book of Acts starting this Wednesday at 7 PM in the Fellowship Hall.',
        'category' => 'Bible Study',
        'priority' => 'normal',
        'is_pinned' => 0,
        'target_audience' => 'all'
    ]
];

foreach ($sample_announcements as $ann) {
    $stmt = $db->prepare("INSERT INTO announcements (title, content, category, priority, is_pinned, target_audience, created_by, status) 
                          VALUES (?, ?, ?, ?, ?, ?, 1, 'published')");
    $stmt->bind_param("ssssis", 
        $ann['title'], 
        $ann['content'], 
        $ann['category'], 
        $ann['priority'], 
        $ann['is_pinned'], 
        $ann['target_audience']
    );
    
    if ($stmt->execute()) {
        $messages[] = "✅ Sample announcement '{$ann['title']}' created";
    } else {
        $errors[] = "❌ Error creating sample announcement '{$ann['title']}': " . $db->error;
    }
}

// Set page title
$page_title = "Setup Announcements";

// Include admin header
include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-bullhorn me-2 text-primary"></i>
                        Announcement System Setup
                    </h5>
                </div>
                <div class="card-body p-4">
                    <h6 class="mb-3">Setup Results:</h6>
                    
                    <?php if (!empty($messages)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Success!</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($messages as $msg): ?>
                                    <li><?php echo $msg; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Errors:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3">Database Structure:</h6>
                    <div class="bg-light p-3 rounded">
                        <p><strong>announcements</strong> table - Stores all announcements</p>
                        <ul>
                            <li><code>announcement_id</code> - Primary key</li>
                            <li><code>title</code> - Announcement title</li>
                            <li><code>content</code> - Announcement content</li>
                            <li><code>category</code> - Category (Events, Ministry, etc.)</li>
                            <li><code>priority</code> - normal, high, urgent</li>
                            <li><code>is_pinned</code> - Whether it stays on top</li>
                            <li><code>target_audience</code> - Who can see it</li>
                            <li><code>status</code> - draft, published, archived</li>
                        </ul>
                        
                        <p class="mt-3"><strong>announcement_reads</strong> table - Tracks read status</p>
                        <ul>
                            <li><code>read_id</code> - Primary key</li>
                            <li><code>announcement_id</code> - Foreign key to announcements</li>
                            <li><code>member_id</code> - Foreign key to members</li>
                            <li><code>read_at</code> - When it was read</li>
                        </ul>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex gap-2">
                        <a href="../member_announcements.php" class="btn btn-primary" target="_blank">
                            <i class="fas fa-eye me-2"></i>View Announcements Page
                        </a>
                        <a href="announcements.php" class="btn btn-outline-primary">
                            <i class="fas fa-cog me-2"></i>Manage Announcements
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>