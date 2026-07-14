<?php
// visit.php - Plan Your Visit Page for First-Time Guests
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Handle visitor information request
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_info'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $visit_date = $_POST['visit_date'] ?? '';
    $interests = isset($_POST['interests']) ? implode(', ', $_POST['interests']) : '';
    $questions = sanitize($_POST['questions'] ?? '');
    $tour = isset($_POST['tour']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($errors)) {
        // Send email to church staff
        $to = "welcome@church.org"; // Change to your church email
        $subject = "New Visitor Information Request";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background: #4361ee; color: white; padding: 20px; }
                .content { padding: 20px; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #4361ee; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>New Visitor Information Request</h2>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='label'>Name:</div>
                    <div>$name</div>
                </div>
                <div class='field'>
                    <div class='label'>Email:</div>
                    <div>$email</div>
                </div>
                <div class='field'>
                    <div class='label'>Phone:</div>
                    <div>$phone</div>
                </div>
                <div class='field'>
                    <div class='label'>Planning to visit:</div>
                    <div>$visit_date</div>
                </div>
                <div class='field'>
                    <div class='label'>Interested in:</div>
                    <div>$interests</div>
                </div>
                <div class='field'>
                    <div class='label'>Questions:</div>
                    <div>$questions</div>
                </div>
                <div class='field'>
                    <div class='label'>Tour Requested:</div>
                    <div>" . ($tour ? 'Yes' : 'No') . "</div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "From: $name <$email>\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
            // Development mode
            $success = "✅ Thank you for your interest! In production, this would be sent to our welcome team.";
        } else {
            // Production
            if (mail($to, $subject, $message, $headers)) {
                $success = "Thank you! Our welcome team will contact you soon.";
            } else {
                $error = "Failed to send request. Please try again or call us directly.";
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get upcoming events for visitors
$upcoming_events = $db->query("
    SELECT event_name, event_date, event_time, location, event_description 
    FROM events 
    WHERE event_date >= CURDATE() 
    ORDER BY event_date ASC 
    LIMIT 3
");

// Service times
$service_times = [
    ['day' => 'Sunday', 'time' => '9:00 AM', 'name' => 'Early Worship', 'description' => 'Traditional service with hymns'],
    ['day' => 'Sunday', 'time' => '10:30 AM', 'name' => 'Morning Service', 'description' => 'Contemporary worship with modern music'],
    ['day' => 'Sunday', 'time' => '6:00 PM', 'name' => 'Evening Service', 'description' => 'Intimate gathering with teaching'],
    ['day' => 'Wednesday', 'time' => '7:00 PM', 'name' => 'Bible Study', 'description' => 'Verse-by-verse teaching and discussion']
];

// FAQ for visitors
$faqs = [
    [
        'question' => 'What should I wear?',
        'answer' => 'Come as you are! You\'ll see everything from casual clothes to business attire. We care more about you than what you wear.'
    ],
    [
        'question' => 'Where do I park?',
        'answer' => 'We have a dedicated guest parking section near the main entrance. Look for the "Guest Parking" signs. Our parking team will be happy to assist you.'
    ],
    [
        'question' => 'What about my kids?',
        'answer' => 'We have safe, fun children\'s programs during all services. Our children\'s check-in system ensures your kids are secure and you can enjoy the service worry-free.'
    ],
    [
        'question' => 'How long is the service?',
        'answer' => 'Our services typically last about 75 minutes. This includes worship, announcements, and a relevant Bible-based message.'
    ],
    [
        'question' => 'Will I have to say anything or be singled out?',
        'answer' => 'Not at all! We won\'t ask you to stand or introduce yourself. You can participate at your own comfort level.'
    ],
    [
        'question' => 'What about Communion?',
        'answer' => 'We celebrate communion on the first Sunday of each month. All believers in Jesus are welcome to participate.'
    ]
];

// Set page title
$page_title = "Plan Your Visit";

// Include header
include 'header.php';
?>

<!-- Hero Section -->
<section class="visit-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold mb-4">Plan Your Visit</h1>
                <p class="lead mb-4">We know visiting a new church can be intimidating. Let us help you feel at home.</p>
                <a href="#next-steps" class="btn btn-light btn-lg">
                    <i class="fas fa-arrow-down me-2"></i>What to Expect
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Welcome Message -->
<section class="welcome-message py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="display-5 fw-bold mb-4">You're Welcome Here</h2>
                <p class="lead mb-4">Whether you're exploring faith for the first time or looking for a church home, we're honored that you're considering visiting us.</p>
                <p class="mb-4">At <?php echo APP_NAME; ?>, you'll find a community of real people on a journey of faith. We don't have everything figured out, but we're learning and growing together. You don't have to be perfect to belong here.</p>
                <div class="d-flex gap-3">
                    <div class="text-center">
                        <div class="h2 fw-bold text-primary"><?php echo date('g:i A', strtotime($service_times[0]['time'])); ?></div>
                        <small>First Service</small>
                    </div>
                    <div class="text-center">
                        <div class="h2 fw-bold text-primary"><?php echo date('g:i A', strtotime($service_times[1]['time'])); ?></div>
                        <small>Second Service</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="welcome-video rounded-4 overflow-hidden shadow-lg">
                    <img src="assets/images/welcome.jpg" alt="Welcome to our church" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- What to Expect Section -->
<section id="next-steps" class="what-to-expect py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">What to Expect</h2>
            <p class="lead text-muted">Your first visit made simple</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="expect-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3">
                            <i class="fas fa-parking fa-2x text-primary"></i>
                        </div>
                        <h5>1. Arrival & Parking</h5>
                        <p class="small text-muted">Look for our "Guest Parking" signs near the main entrance. Our parking team will greet you and point you to the welcome center.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="expect-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3">
                            <i class="fas fa-handshake fa-2x text-primary"></i>
                        </div>
                        <h5>2. Welcome Center</h5>
                        <p class="small text-muted">Stop by our welcome center in the lobby. We'd love to meet you, answer questions, and help you find your way around.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="expect-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3">
                            <i class="fas fa-child fa-2x text-primary"></i>
                        </div>
                        <h5>3. Children's Check-in</h5>
                        <p class="small text-muted">If you have kids, our children's check-in area is right off the lobby. Our team will help you get them registered and settled.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="expect-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3">
                            <i class="fas fa-music fa-2x text-primary"></i>
                        </div>
                        <h5>4. Worship Service</h5>
                        <p class="small text-muted">Our services include contemporary worship music, practical Bible teaching, and a friendly atmosphere. Feel free to participate at your own comfort level.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="expect-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3">
                            <i class="fas fa-coffee fa-2x text-primary"></i>
                        </div>
                        <h5>5. Coffee & Connection</h5>
                        <p class="small text-muted">After service, join us in the lobby for coffee and conversation. It's a great time to meet people and ask any questions you might have.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="expect-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3">
                            <i class="fas fa-gift fa-2x text-primary"></i>
                        </div>
                        <h5>6. Welcome Gift</h5>
                        <p class="small text-muted">First-time guests receive a special welcome gift at our welcome center. It's our way of saying thank you for visiting.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Service Times -->
<section class="service-times py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Service Times</h2>
            <p class="lead text-muted">Choose the service that works best for you</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($service_times as $service): ?>
                <div class="col-md-3">
                    <div class="time-card h-100">
                        <div class="card-body text-center">
                            <div class="day-badge mb-2"><?php echo $service['day']; ?></div>
                            <div class="time-display mb-2"><?php echo $service['time']; ?></div>
                            <h5 class="h6 mb-2"><?php echo $service['name']; ?></h5>
                            <p class="small text-muted mb-0"><?php echo $service['description']; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Kids & Families -->
<section class="kids-section py-5 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="display-5 fw-bold mb-4">We Love Kids!</h2>
                <p class="lead mb-4">Your children will have a blast learning about Jesus in a safe, age-appropriate environment.</p>
                
                <div class="kid-features mb-4">
                    <div class="d-flex mb-3">
                        <i class="fas fa-check-circle text-success me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-1">Secure Check-In System</h6>
                            <p class="small text-muted mb-0">Parent matching tags ensure only you can pick up your child</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-check-circle text-success me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-1">Background-Checked Volunteers</h6>
                            <p class="small text-muted mb-0">All children's workers are screened and trained</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-check-circle text-success me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-1">Age-Appropriate Teaching</h6>
                            <p class="small text-muted mb-0">Lessons designed for each age group</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-check-circle text-success me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-1">Nursing Mother's Room</h6>
                            <p class="small text-muted mb-0">Private space available during services</p>
                        </div>
                    </div>
                </div>
                
                <a href="kids.php" class="btn btn-primary">
                    <i class="fas fa-child me-2"></i>Learn About Kids' Ministry
                </a>
            </div>
            <div class="col-lg-6">
                <div class="kids-image rounded-4 overflow-hidden shadow-lg">
                    <img src="assets/images/kids-ministry.jpg" alt="Children's Ministry" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Frequently Asked Questions</h2>
            <p class="lead text-muted">Everything you need to know before you visit</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="accordion" id="faqAccordion">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" 
                                        type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#faq<?php echo $index; ?>">
                                    <?php echo $faq['question']; ?>
                                </button>
                            </h2>
                            <div id="faq<?php echo $index; ?>" 
                                 class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" 
                                 data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <?php echo $faq['answer']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map & Location -->
<section class="location-section py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Find Us</h2>
            <p class="lead text-muted">We're easy to find and easy to get to</p>
        </div>
        
        <div class="row">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="map-container rounded-4 overflow-hidden shadow-lg" style="height: 400px;">
                    <!-- Google Maps iframe - Replace with your church address -->
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3022.9663095343008!2d-73.9851076845849!3d40.74881797932892!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c259a9b3117469%3A0xd134e199a405a163!2sEmpire%20State%20Building!5e0!3m2!1sen!2sus!4v1620000000000!5m2!1sen!2sus" 
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="location-info h-100 d-flex flex-column justify-content-center">
                    <h4 class="fw-bold mb-4">Church Location</h4>
                    
                    <div class="d-flex mb-4">
                        <i class="fas fa-map-marker-alt text-primary fs-4 me-3"></i>
                        <div>
                            <h6 class="mb-1">Address</h6>
                            <p class="mb-0">123 Church Street<br>City, ST 12345</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <i class="fas fa-car text-primary fs-4 me-3"></i>
                        <div>
                            <h6 class="mb-1">Parking</h6>
                            <p class="mb-0">Free parking available in our lot. Guest parking near the main entrance.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <i class="fas fa-bus text-primary fs-4 me-3"></i>
                        <div>
                            <h6 class="mb-1">Public Transit</h6>
                            <p class="mb-0">Bus stop #15 at Church & Main, just 2 blocks away.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex">
                        <i class="fas fa-wheelchair text-primary fs-4 me-3"></i>
                        <div>
                            <h6 class="mb-1">Accessibility</h6>
                            <p class="mb-0">Fully wheelchair accessible with ramps and elevators.</p>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="https://maps.google.com/?q=123+Church+Street+City+ST+12345" target="_blank" 
                           class="btn btn-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Request Information Form -->
<section class="request-form py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold mb-2">Have Questions?</h2>
                            <p class="text-muted">We'd love to connect with you before your visit</p>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="visitForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Your Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Phone</label>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">When do you plan to visit?</label>
                                    <input type="date" class="form-control" name="visit_date" 
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-bold">I'm interested in:</label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="interests[]" value="Service Times">
                                                <label class="form-check-label">Service Times</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="interests[]" value="Children's Ministry">
                                                <label class="form-check-label">Children's Ministry</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="interests[]" value="Youth Group">
                                                <label class="form-check-label">Youth Group</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="interests[]" value="Small Groups">
                                                <label class="form-check-label">Small Groups</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="interests[]" value="Baptism">
                                                <label class="form-check-label">Baptism</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="interests[]" value="Membership">
                                                <label class="form-check-label">Membership</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-bold">Questions or prayer requests</label>
                                    <textarea class="form-control" name="questions" rows="3"></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tour" id="tour">
                                        <label class="form-check-label" for="tour">
                                            I'd like a personal tour of the facility
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" name="request_info" class="btn btn-primary btn-lg w-100" id="submitBtn">
                                        <i class="fas fa-paper-plane me-2"></i>Send Request
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="small text-muted mb-0">
                                <i class="fas fa-phone me-1"></i>Or call us directly: <strong>(555) 123-4567</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Next Steps -->
<section class="next-steps py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Ready for Your Visit?</h2>
            <p class="lead text-muted">Here are some helpful next steps</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="step-card text-center">
                    <div class="step-number">1</div>
                    <h5>Plan Your Sunday</h5>
                    <p class="small text-muted">Choose a service time that works for you and check out our location.</p>
                    <a href="#service-times" class="btn btn-outline-primary btn-sm">View Times</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card text-center">
                    <div class="step-number">2</div>
                    <h5>Fill Out a Connect Card</h5>
                    <p class="small text-muted">Let us know you visited and how we can connect with you.</p>
                    <a href="#connect" class="btn btn-outline-primary btn-sm">Get Connected</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card text-center">
                    <div class="step-number">3</div>
                    <h5>Join a Welcome Lunch</h5>
                    <p class="small text-muted">Meet our pastors and learn more about our church over lunch.</p>
                    <a href="welcome-lunch.php" class="btn btn-outline-primary btn-sm">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Visit page specific styles */
.visit-hero {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.95), rgba(6, 182, 212, 0.95)), 
                url('assets/images/welcome-hero.jpg') center/cover;
    color: white;
    padding: 120px 0;
    margin-top: -20px;
}

/* Expectation cards */
.expect-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    overflow: hidden;
}

.expect-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.icon-circle {
    width: 80px;
    height: 80px;
    background: rgba(67, 97, 238, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    transition: all 0.3s ease;
}

.expect-card:hover .icon-circle {
    background: #4361ee;
    color: white;
}

/* Time cards */
.time-card {
    background: white;
    border-radius: 15px;
    padding: 25px 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
}

.time-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.day-badge {
    background: #4361ee;
    color: white;
    display: inline-block;
    padding: 5px 15px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 500;
}

.time-display {
    font-size: 1.8rem;
    font-weight: 700;
    color: #4361ee;
    line-height: 1.2;
}

/* Form card */
.form-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

/* Step cards */
.step-card {
    background: white;
    border-radius: 15px;
    padding: 30px 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    position: relative;
    transition: all 0.3s ease;
}

.step-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.step-number {
    width: 40px;
    height: 40px;
    background: #4361ee;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto 20px;
}

/* Welcome video/image */
.welcome-video {
    position: relative;
    overflow: hidden;
}

.welcome-video::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(67, 97, 238, 0.1);
    pointer-events: none;
}

/* Kids features */
.kid-features {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

/* Map container */
.map-container {
    background: #e9ecef;
}

/* Responsive */
@media (max-width: 768px) {
    .visit-hero {
        padding: 80px 0;
    }
    
    .visit-hero h1 {
        font-size: 2rem;
    }
    
    .time-display {
        font-size: 1.5rem;
    }
}

/* Animations */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.expect-card, .time-card, .step-card {
    animation: slideInUp 0.5s ease-out forwards;
}

/* Hover effects */
.expect-card, .time-card, .step-card {
    position: relative;
    overflow: hidden;
}

.expect-card::before, .time-card::before, .step-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.expect-card:hover::before, .time-card:hover::before, .step-card:hover::before {
    left: 100%;
}

/* Form styling */
.form-control, .form-select {
    border: 2px solid #e9ecef;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

/* Checkbox styling */
.form-check-input:checked {
    background-color: #4361ee;
    border-color: #4361ee;
}
</style>

<script>
// Form validation and submission
document.getElementById('visitForm')?.addEventListener('submit', function(e) {
    const name = document.querySelector('input[name="name"]').value.trim();
    const email = document.querySelector('input[name="email"]').value.trim();
    const submitBtn = document.getElementById('submitBtn');
    
    if (!name || !email) {
        e.preventDefault();
        alert('Please fill in required fields');
    } else {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    }
});

// Smooth scroll for anchor links
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

// Phone number formatting
document.querySelector('input[name="phone"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) value = value.slice(0, 10);
    
    if (value.length >= 6) {
        value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6);
    } else if (value.length >= 3) {
        value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
    }
    
    e.target.value = value;
});

// Set minimum date for visit date
const today = new Date().toISOString().split('T')[0];
document.querySelector('input[name="visit_date"]')?.setAttribute('min', today);

// FAQ accordion smooth scroll
document.querySelectorAll('.accordion-button').forEach(button => {
    button.addEventListener('click', function() {
        setTimeout(() => {
            this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 300);
    });
});

// Intersection Observer for animations
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.expect-card, .time-card, .step-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    observer.observe(card);
});
</script>

<?php
// Include footer
include 'footer.php';
?>