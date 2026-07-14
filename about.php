<?php
// about.php - Public About Page
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Get church information (you can customize this or store in database)
$church_info = [
    'name' => APP_NAME,
    'founded' => '1985',
    'denomination' => 'Non-denominational',
    'weekly_attendance' => '350+',
    'members' => '500+',
    'staff_count' => '12',
    'mission' => 'To love God, love others, and make disciples of Jesus Christ.',
    'vision' => 'A community where everyone can find hope, purpose, and belonging.',
    'core_values' => [
        'Worship' => 'Passionate, authentic worship that honors God',
        'Community' => 'Building meaningful relationships through fellowship',
        'Discipleship' => 'Growing in faith through teaching and mentoring',
        'Service' => 'Serving our church and local community with love',
        'Outreach' => 'Sharing God\'s love locally and globally'
    ]
];

// Get upcoming events for display
$upcoming_events = $db->query("
    SELECT event_name, event_date, event_time 
    FROM events 
    WHERE event_date >= CURDATE() 
    ORDER BY event_date ASC 
    LIMIT 3
");

// Get service times (you can store these in database or config)
$service_times = [
    ['day' => 'Sunday', 'time' => '9:00 AM', 'name' => 'Early Morning Worship'],
    ['day' => 'Sunday', 'time' => '10:30 AM', 'name' => 'Morning Service'],
    ['day' => 'Sunday', 'time' => '6:00 PM', 'name' => 'Evening Service'],
    ['day' => 'Wednesday', 'time' => '7:00 PM', 'name' => 'Bible Study & Prayer Meeting']
];

// Get leadership team (you can store in database)
$leadership = [
    [
        'name' => 'Pastor John Smith',
        'position' => 'Senior Pastor',
        'bio' => 'Pastor John has been leading our church for 15 years. He is passionate about teaching God\'s Word and seeing lives transformed.',
        'image' => 'assets/images/leaders/pastor-john.jpg',
        'email' => 'john.smith@church.org'
    ],
    [
        'name' => 'Pastor Sarah Johnson',
        'position' => 'Associate Pastor',
        'bio' => 'Pastor Sarah oversees our small groups and discipleship ministries. She has a heart for helping people grow in their faith.',
        'image' => 'assets/images/leaders/pastor-sarah.jpg',
        'email' => 'sarah.johnson@church.org'
    ],
    [
        'name' => 'Mike Williams',
        'position' => 'Worship Director',
        'bio' => 'Mike leads our worship team with passion and creativity. He believes that worship is a lifestyle, not just a Sunday activity.',
        'image' => 'assets/images/leaders/mike-williams.jpg',
        'email' => 'mike.williams@church.org'
    ],
    [
        'name' => 'Lisa Brown',
        'position' => 'Children\'s Ministry Director',
        'bio' => 'Lisa has been serving in children\'s ministry for over 10 years. She is dedicated to creating a safe and fun environment for kids to learn about Jesus.',
        'image' => 'assets/images/leaders/lisa-brown.jpg',
        'email' => 'lisa.brown@church.org'
    ],
    [
        'name' => 'David Lee',
        'position' => 'Youth Pastor',
        'bio' => 'David is passionate about reaching the next generation for Christ. He leads our youth group with energy and enthusiasm.',
        'image' => 'assets/images/leaders/david-lee.jpg',
        'email' => 'david.lee@church.org'
    ],
    [
        'name' => 'Mary Davis',
        'position' => 'Administrative Director',
        'bio' => 'Mary keeps our church running smoothly behind the scenes. She manages our office and coordinates church events.',
        'image' => 'assets/images/leaders/mary-davis.jpg',
        'email' => 'mary.davis@church.org'
    ]
];

// Get statistics
$stats = [];

// Count members (if table exists)
$table_check = $db->query("SHOW TABLES LIKE 'members'");
if ($table_check && $table_check->num_rows > 0) {
    $result = $db->query("SELECT COUNT(*) as count FROM members WHERE membership_status = 'Active'");
    $stats['members'] = $result->fetch_assoc()['count'] ?? 0;
} else {
    $stats['members'] = 0;
}

// Count groups
$group_check = $db->query("SHOW TABLES LIKE 'groups'");
if ($group_check && $group_check->num_rows > 0) {
    $result = $db->query("SELECT COUNT(*) as count FROM `groups` WHERE status = 'Active'");
    $stats['groups'] = $result->fetch_assoc()['count'] ?? 0;
} else {
    $stats['groups'] = 0;
}

// Set page title
$page_title = "About Us";

// Include header
include 'header.php';
?>

<!-- Hero Section -->
<section class="about-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold mb-4">About <?php echo $church_info['name']; ?></h1>
                <p class="lead mb-4">A community of faith, hope, and love since <?php echo $church_info['founded']; ?></p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="#our-story" class="btn btn-light btn-lg">
                        <i class="fas fa-book-open me-2"></i>Our Story
                    </a>
                    <a href="#visit" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-calendar-check me-2"></i>Plan Your Visit
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision Section -->
<section class="mission-vision py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="mission-card h-100">
                    <div class="card-body p-4 text-center">
                        <div class="icon-box mb-3">
                            <i class="fas fa-bullseye fa-3x text-primary"></i>
                        </div>
                        <h3 class="h4 mb-3">Our Mission</h3>
                        <p class="mb-0"><?php echo $church_info['mission']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="vision-card h-100">
                    <div class="card-body p-4 text-center">
                        <div class="icon-box mb-3">
                            <i class="fas fa-eye fa-3x text-success"></i>
                        </div>
                        <h3 class="h4 mb-3">Our Vision</h3>
                        <p class="mb-0"><?php echo $church_info['vision']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Story Section -->
<section id="our-story" class="our-story py-5 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="display-5 fw-bold mb-4">Our Story</h2>
                <p class="lead mb-4">Founded in <?php echo $church_info['founded']; ?>, our church began with a small group of believers meeting in a living room.</p>
                <p class="mb-4">Over the years, we've grown into a thriving community of faith, but our core values remain the same: loving God, loving others, and making disciples. We believe that church is not just a Sunday morning gathering, but a family on mission together.</p>
                <p class="mb-4">Today, we're blessed to have over <?php echo $church_info['members']; ?> members and <?php echo $church_info['weekly_attendance']; ?> people joining us each week. We offer ministries for all ages and stages of life, from our youngest children to our senior saints.</p>
                
                <div class="row g-3 mt-4">
                    <div class="col-4 text-center">
                        <div class="stat-circle">
                            <div class="h2 fw-bold text-primary mb-0"><?php echo $church_info['founded']; ?></div>
                            <small>Founded</small>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="stat-circle">
                            <div class="h2 fw-bold text-primary mb-0"><?php echo $stats['members'] ?: '500+'; ?></div>
                            <small>Members</small>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="stat-circle">
                            <div class="h2 fw-bold text-primary mb-0"><?php echo $stats['groups'] ?: '20+'; ?></div>
                            <small>Groups</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="story-image rounded-4 shadow-lg">
                    <img src="assets/images/church-history.jpg" alt="Church History" class="img-fluid rounded-4">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Values Section -->
<section class="core-values py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Our Core Values</h2>
            <p class="lead text-muted">The principles that guide everything we do</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($church_info['core_values'] as $value => $description): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="value-card h-100">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3"><?php echo $value; ?></h3>
                            <p class="small text-muted mb-0"><?php echo $description; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Leadership Team Section -->
<section class="leadership py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Meet Our Leadership Team</h2>
            <p class="lead text-muted">Dedicated servants leading our church family</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($leadership as $leader): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="leader-card h-100">
                        <div class="card-body p-4 text-center">
                            <div class="leader-image mb-3">
                                <?php if (file_exists($leader['image'])): ?>
                                    <img src="<?php echo $leader['image']; ?>" alt="<?php echo $leader['name']; ?>" class="rounded-circle img-fluid" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px;">
                                        <i class="fas fa-user fa-3x text-primary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="h5 fw-bold mb-1"><?php echo $leader['name']; ?></h3>
                            <p class="small text-primary mb-2"><?php echo $leader['position']; ?></p>
                            <p class="small text-muted mb-3"><?php echo $leader['bio']; ?></p>
                            <a href="mailto:<?php echo $leader['email']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-envelope me-1"></i>Contact
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- What We Believe Section -->
<section class="beliefs py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="display-5 fw-bold mb-4">What We Believe</h2>
                <div class="accordion" id="beliefsAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#belief1">
                                The Bible
                            </button>
                        </h2>
                        <div id="belief1" class="accordion-collapse collapse show" data-bs-parent="#beliefsAccordion">
                            <div class="accordion-body">
                                We believe the Bible is the inspired and authoritative Word of God, without error in its original manuscripts, and the final authority for our faith and practice.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#belief2">
                                God
                            </button>
                        </h2>
                        <div id="belief2" class="accordion-collapse collapse" data-bs-parent="#beliefsAccordion">
                            <div class="accordion-body">
                                We believe in one God, eternally existing in three persons: Father, Son, and Holy Spirit. He is loving, holy, just, and sovereign over all creation.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#belief3">
                                Jesus Christ
                            </button>
                        </h2>
                        <div id="belief3" class="accordion-collapse collapse" data-bs-parent="#beliefsAccordion">
                            <div class="accordion-body">
                                We believe in Jesus Christ, God's only Son, conceived by the Holy Spirit, born of the Virgin Mary. He lived a sinless life, died on the cross for our sins, rose bodily from the dead, and ascended to heaven where He intercedes for us.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#belief4">
                                Salvation
                            </button>
                        </h2>
                        <div id="belief4" class="accordion-collapse collapse" data-bs-parent="#beliefsAccordion">
                            <div class="accordion-body">
                                We believe salvation is a gift from God, received through faith in Jesus Christ. It cannot be earned by good works but is freely given to all who repent and believe.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#belief5">
                                The Church
                            </button>
                        </h2>
                        <div id="belief5" class="accordion-collapse collapse" data-bs-parent="#beliefsAccordion">
                            <div class="accordion-body">
                                We believe the church is the body of Christ, composed of all believers. We are called to worship God, grow in faith, serve others, and share the gospel with the world.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="assets/images/bible-study.jpg" alt="Bible Study" class="img-fluid rounded-4 shadow-lg">
            </div>
        </div>
    </div>
</section>

<!-- Service Times Section -->
<section class="service-times py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Join Us This Sunday</h2>
            <p class="lead text-muted">We'd love to have you worship with us</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($service_times as $service): ?>
                <div class="col-md-3 col-6">
                    <div class="service-card text-center">
                        <div class="service-icon mb-3">
                            <i class="fas fa-clock fa-2x text-primary"></i>
                        </div>
                        <h3 class="h6 fw-bold mb-1"><?php echo $service['day']; ?></h3>
                        <p class="h5 text-primary mb-1"><?php echo $service['time']; ?></p>
                        <small class="text-muted"><?php echo $service['name']; ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <a href="visit.php" class="btn btn-primary btn-lg">
                <i class="fas fa-calendar-check me-2"></i>Plan Your Visit
            </a>
        </div>
    </div>
</section>

<!-- Upcoming Events Preview -->
<?php if ($upcoming_events && $upcoming_events->num_rows > 0): ?>
<section class="upcoming-events py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Upcoming Events</h2>
            <p class="lead text-muted">Join us for these special gatherings</p>
        </div>
        
        <div class="row g-4">
            <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="event-card h-100">
                        <div class="event-date text-center p-3">
                            <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                            <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                            <p class="card-text small text-muted">
                                <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                            </p>
                            <a href="events.php" class="btn btn-outline-primary btn-sm">Learn More</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="events.php" class="btn btn-outline-primary">
                <i class="fas fa-calendar-alt me-2"></i>View All Events
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Visit Section -->
<section id="visit" class="visit-section py-5 bg-primary text-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="display-5 fw-bold mb-4">Plan Your Visit</h2>
                <p class="lead mb-4">New to <?php echo $church_info['name']; ?>? We'd love to welcome you and help you feel at home.</p>
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <div class="visit-card text-center">
                            <i class="fas fa-calendar-check fa-3x mb-3"></i>
                            <h5>Sunday Services</h5>
                            <p class="small">9:00 AM & 10:30 AM</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="visit-card text-center">
                            <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
                            <h5>Location</h5>
                            <p class="small">123 Church Street<br>City, ST 12345</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="visit-card text-center">
                            <i class="fas fa-parking fa-3x mb-3"></i>
                            <h5>Parking</h5>
                            <p class="small">Free parking available<br>Welcome team at entrance</p>
                        </div>
                    </div>
                </div>
                <a href="visit.php" class="btn btn-light btn-lg">
                    <i class="fas fa-heart me-2"></i>I'm New Here
                </a>
            </div>
        </div>
    </div>
</section>

<style>
/* About page specific styles */
.about-hero {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.9), rgba(6, 182, 212, 0.9)), 
                url('assets/images/church-hero.jpg') center/cover;
    color: white;
    padding: 120px 0;
    margin-top: -20px;
}

/* Mission & Vision cards */
.mission-card, .vision-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.mission-card:hover, .vision-card:hover {
    transform: translateY(-10px);
}

.icon-box {
    width: 80px;
    height: 80px;
    background: rgba(67, 97, 238, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

/* Value cards */
.value-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.value-card:hover {
    border-left-color: #4361ee;
    transform: translateX(5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

/* Leadership cards */
.leader-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.leader-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

/* Service cards */
.service-card {
    background: white;
    border-radius: 15px;
    padding: 25px 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

/* Event cards */
.event-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.event-date {
    background: linear-gradient(135deg, #4361ee, #06b6d4);
    color: white;
    padding: 15px;
}

.event-date .month {
    font-size: 1.2rem;
    font-weight: 500;
    text-transform: uppercase;
}

.event-date .day {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
}

/* Visit cards */
.visit-card {
    padding: 20px;
    border-radius: 15px;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.visit-card:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-5px);
}

/* Stat circles */
.stat-circle {
    background: white;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

/* Story image */
.story-image {
    overflow: hidden;
    transition: transform 0.3s ease;
}

.story-image:hover {
    transform: scale(1.02);
}

/* Accordion customization */
.accordion-button:not(.collapsed) {
    background-color: rgba(67, 97, 238, 0.1);
    color: #4361ee;
}

.accordion-button:focus {
    box-shadow: none;
    border-color: rgba(67, 97, 238, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .about-hero {
        padding: 80px 0;
    }
    
    .about-hero h1 {
        font-size: 2rem;
    }
    
    .stat-circle .h2 {
        font-size: 1.5rem;
    }
}

/* Animations */
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

.mission-card, .vision-card, .value-card, .leader-card, .service-card {
    animation: fadeInUp 0.5s ease-out forwards;
}

/* Section dividers */
section {
    position: relative;
}

section::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 50px;
    height: 3px;
    background: linear-gradient(90deg, transparent, #4361ee, transparent);
}

section:last-child::after {
    display: none;
}
</style>

<?php
// Include footer
include 'footer.php';
?>