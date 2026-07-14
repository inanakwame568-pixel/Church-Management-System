<?php
// index.php - Main landing page
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Get some basic stats for the landing page
$db = Database::getInstance()->getConnection();

// Get upcoming events (next 3)
$eventsStmt = $db->prepare("
    SELECT * FROM events 
    WHERE event_date >= CURDATE() 
    ORDER BY event_date ASC 
    LIMIT 3
");
$eventsStmt->execute();
$upcomingEvents = $eventsStmt->get_result();

// Get service times (you can customize these)
$serviceTimes = [
    ['day' => 'Sunday', 'time' => '8:00 AM', 'name' => 'Mountain Dew'],
    ['day' => 'Sunday', 'time' => '9:00 AM', 'name' => 'Word Assembly'],
    ['day' => 'Sunday', 'time' => '11:00 AM', 'name' => 'Spirit Assembly'],
    ['day' => 'Wednesday', 'time' => '10:00 AM', 'name' => 'Breakthrough Hour']
];

// Get announcements (you can create a table for this later)
$announcements = [
    ['title' => 'New Members Class', 'date' => 'Next Sunday', 'description' => 'Join us for our new members class before service-PDF.'],
    ['title' => 'Food Drive', 'date' => 'All Month', 'description' => 'Bring canned goods to support our local community.'],
    ['title' => 'Youth Retreat', 'date' => 'March 15-17', 'description' => 'Registration now open for our annual youth retreat.']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Church Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Landing Page Specific Styles */
        :root {
            --hero-overlay: linear-gradient(135deg, rgba(44, 62, 80, 0.9), rgba(52, 152, 219, 0.8));
        }

        body {
            background: #f5f6fa;
        }

        .landing-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            font-size: 2rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-menu a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-menu a:hover {
            color: var(--secondary-color);
        }

        .login-btn {
            background: var(--secondary-color);
            color: white !important;
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #2980b9;
            color: white !important;
        }

        /* Hero Section */
        .hero {
            background: var(--hero-overlay), url('assets/images/church-bg.jpg') center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 80px 20px;
            margin-top: 60px;
        }

        .hero-content {
            max-width: 800px;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .hero-btn {
            padding: 1rem 2rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.3s;
        }

        .hero-btn:hover {
            transform: translateY(-3px);
        }

        .hero-btn-primary {
            background: var(--secondary-color);
            color: white;
        }

        .hero-btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        /* Features Section */
        .features {
            padding: 80px 20px;
            background: white;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .section-title p {
            color: #666;
            font-size: 1.1rem;
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            text-align: center;
            padding: 30px;
            border-radius: 8px;
            background: #f8f9fa;
            transition: transform 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Service Times */
        .service-times {
            padding: 80px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .service-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .service-card {
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .service-day {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .service-time {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .service-name {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Events Section */
        .events {
            padding: 80px 20px;
            background: white;
        }

        .events-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .events-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
        }

        .event-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }

        .event-item:last-child {
            border-bottom: none;
        }

        .event-date {
            text-align: center;
            min-width: 80px;
        }

        .event-month {
            font-size: 1.2rem;
            color: var(--secondary-color);
            text-transform: uppercase;
        }

        .event-day {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            line-height: 1;
        }

        .event-details h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .event-details p {
            color: #666;
        }

        .announcements {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 8px;
            padding: 30px;
        }

        .announcement-item {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .announcement-date {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        /* CTA Section */
        .cta {
            padding: 80px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-btn {
            display: inline-block;
            padding: 1rem 3rem;
            background: white;
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: transform 0.3s;
        }

        .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        /* Footer */
        .footer {
            background: var(--primary-color);
            color: white;
            padding: 60px 20px 20px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }

        .footer-section h3 {
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .footer-section p, .footer-section a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            line-height: 1.8;
        }

        .footer-section a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .social-links a:hover {
            background: var(--secondary-color);
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 40px auto 0;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .events-container {
                grid-template-columns: 1fr;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .feature-card {
                margin: 0 10px;
            }
        }

        /* Quick Links for Members */
        .member-quick-links {
            background: #f8f9fa;
            padding: 40px 20px;
        }

        .quick-links-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .quick-link {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .quick-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            background: var(--secondary-color);
            color: white;
        }

        .quick-link-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .quick-link-text {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <header class="landing-header">
        <nav class="nav-container">
            <div class="logo">
                <span class="logo-icon">⛪</span>
                <span class="logo-text"><?php echo APP_NAME; ?></span>
            </div>
            <div class="nav-menu">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#services">Services</a>
                <a href="#events">Events</a>
                <a href="#about">About</a>
                <a href="login.php" class="login-btn">Member Login</a>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Welcome to <?php echo APP_NAME; ?></h1>
            <p>Connecting Faith, Community, and Technology to Serve You Better</p>
            <div class="hero-buttons">
                <a href="#services" class="hero-btn hero-btn-primary">Service Times</a>
                <a href="#events" class="hero-btn hero-btn-secondary">Upcoming Events</a>
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="hero-btn hero-btn-secondary">New Here?</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="section-title">
            <h2>Church Management Made Simple</h2>
            <p>Tools to help our community grow and stay connected</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Member Directory</h3>
                <p>Stay connected with our church family through our online directory</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Online Giving</h3>
                <p>Easy and secure way to support the ministry through tithes and offerings</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h3>Event Calendar</h3>
                <p>Never miss an important church event or activity</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Small Groups</h3>
                <p>Find and join small groups that match your interests</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🙏</div>
                <h3>Prayer Requests</h3>
                <p>Share prayer requests with our prayer team</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Mobile Access</h3>
                <p>Access church information anytime, anywhere</p>
            </div>
        </div>
    </section>

    <!-- Service Times -->
    <section id="services" class="service-times">
        <div class="section-title" style="color: white;">
            <h2 style="color: white;">Service Times</h2>
            <p style="color: rgba(255,255,255,0.9);">Join us for fellowship and worship</p>
        </div>
        <div class="service-grid">
            <?php foreach ($serviceTimes as $service): ?>
            <div class="service-card">
                <div class="service-day"><?php echo $service['day']; ?></div>
                <div class="service-time"><?php echo $service['time']; ?></div>
                <div class="service-name"><?php echo $service['name']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Events and Announcements -->
    <section id="events" class="events">
        <div class="events-container">
            <!-- Upcoming Events -->
            <div class="events-list">
                <h3 style="margin-bottom: 20px; color: var(--primary-color);">📅 Upcoming Events</h3>
                <?php if ($upcomingEvents->num_rows > 0): ?>
                    <?php while ($event = $upcomingEvents->fetch_assoc()): ?>
                    <div class="event-item">
                        <div class="event-date">
                            <div class="event-month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                            <div class="event-day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                        </div>
                        <div class="event-details">
                            <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                            <p><?php echo htmlspecialchars(substr($event['event_description'], 0, 100)) . '...'; ?></p>
                            <p><small>🕒 <?php echo date('g:i A', strtotime($event['event_time'])); ?></small></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 30px;">No upcoming events scheduled</p>
                <?php endif; ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="events.php" class="btn btn-secondary">View All Events</a>
                </div>
            </div>

            <!-- Announcements -->
            <div class="announcements">
                <h3 style="margin-bottom: 20px;">📢 Announcements</h3>
                <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-item">
                    <div class="announcement-title"><?php echo $announcement['title']; ?></div>
                    <div class="announcement-date"><?php echo $announcement['date']; ?></div>
                    <p><?php echo $announcement['description']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Quick Links for Members -->
    <?php if (isLoggedIn()): ?>
    <section class="member-quick-links">
        <div class="section-title">
            <h2>Welcome Back!</h2>
            <p>Quick access to member features</p>
        </div>
        <div class="quick-links-grid">
            <a href="admin/dashboard.php" class="quick-link">
                <div class="quick-link-icon">📊</div>
                <div class="quick-link-text">Dashboard</div>
            </a>
            <a href="admin/members.php" class="quick-link">
                <div class="quick-link-icon">👥</div>
                <div class="quick-link-text">Directory</div>
            </a>
            <a href="admin/events.php" class="quick-link">
                <div class="quick-link-icon">📅</div>
                <div class="quick-link-text">Events</div>
            </a>
            <a href="admin/donations.php" class="quick-link">
                <div class="quick-link-icon">💰</div>
                <div class="quick-link-text">Give Online</div>
            </a>
            <a href="admin/groups.php" class="quick-link">
                <div class="quick-link-icon">👥</div>
                <div class="quick-link-text">Small Groups</div>
            </a>
            <a href="admin/profile.php" class="quick-link">
                <div class="quick-link-icon">👤</div>
                <div class="quick-link-text">My Profile</div>
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Call to Action -->
    <section class="cta">
        <h2>Become Part of Our Church Family</h2>
        <p>Whether you're new to the area or looking for a church home, we'd love to have you join us!</p>
        <?php if (!isLoggedIn()): ?>
            <a href="register.php" class="cta-btn">Register as Member</a>
        <?php else: ?>
            <a href="admin/dashboard.php" class="cta-btn">Go to Dashboard</a>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><?php echo APP_NAME; ?></h3>
                <p>Connecting faith and community through technology.</p>
                <div class="social-links">
                    <a href="#eastwoodanaba.com"><span>📘</span></a>
                    <a href="#twitter.com/eastwoodanaba"><span>🐦</span></a>
                    <a href="#facebook.com/eastwoodanabaministriesofficialpage"><span>📷</span></a>
                    <a href="www.youtube.com/@EastwoodAnaba"><span>▶️</span></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <p><a href="#home">Home</a></p>
                <p><a href="#features">Features</a></p>
                <p><a href="#services">Services</a></p>
                <p><a href="#events">Events</a></p>
                <p><a href="contact.php">Contact Us</a></p>
            </div>
            <div class="footer-section">
                <h3>Contact Info</h3>
                <p>📍 Bolga-Temale Road</p>
                <p>📞 (+233) 55 335 8568</p>
                <p>✉️ info@desertpastures.org</p>
            </div>
            <div class="footer-section">
                <h3>Office Hours</h3>
                <p>Monday - Friday: 9am - 5pm</p>
                <p>Saturday: 10am - 6pm</p>
                <p>Sunday: 8am - 4pm</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- Floating Action Button for Quick Access -->
    <div style="position: fixed; bottom: 30px; right: 30px; z-index: 999;">
        <?php if (isLoggedIn()): ?>
            <a href="admin/dashboard.php" style="display: block; width: 60px; height: 60px; background: var(--secondary-color); color: white; border-radius: 50%; text-align: center; line-height: 60px; font-size: 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); text-decoration: none;">
                📋
            </a>
        <?php else: ?>
            <a href="login.php" style="display: block; width: 60px; height: 60px; background: var(--secondary-color); color: white; border-radius: 50%; text-align: center; line-height: 60px; font-size: 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); text-decoration: none;">
                🔑
            </a>
        <?php endif; ?>
    </div>

    <!-- Smooth Scroll Script -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.landing-header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255,255,255,0.95)';
                header.style.backdropFilter = 'blur(10px)';
            } else {
                header.style.background = 'white';
                header.style.backdropFilter = 'none';
            }
        });

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });
    </script>
</body>
</html>