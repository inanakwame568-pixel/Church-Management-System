<?php
// member_header.php - Header for member area (UPDATED)
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php'; // Now this just has helper functions

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require member login - this function is now in functions.php
requireMember();

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
$current_member_id = getCurrentMemberId(); // This function is now in functions.php
$current_user_name = getCurrentUserName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Member Portal - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            padding-top: 90px;
        }
        
        /* Member Navigation */
        .member-navbar {
            background: white;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            padding: 15px 0;
        }
        
        .member-navbar .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: #4361ee;
        }
        
        .member-navbar .nav-link {
            font-weight: 500;
            color: #64748b;
            margin: 0 10px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .member-navbar .nav-link:hover {
            color: #4361ee;
            background: #f1f5f9;
        }
        
        .member-navbar .nav-link.active {
            color: #4361ee;
            background: #e0e7ff;
            font-weight: 600;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #06b6d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        /* Main container */
        .member-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Cards */
        .member-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .member-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-color: #cbd5e1;
        }
        
        .card-header-custom {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 600;
        }
        
        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, #4361ee, #06b6d4);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        
        /* Quick stats */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #4361ee;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        /* Buttons */
        .btn-member-primary {
            background: #4361ee;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-member-primary:hover {
            background: #3046c0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-member-outline {
            background: transparent;
            border: 2px solid #4361ee;
            color: #4361ee;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-member-outline:hover {
            background: #4361ee;
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }
            
            .welcome-banner {
                padding: 25px;
            }
            
            .welcome-banner h1 {
                font-size: 1.8rem;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
    
    <?php if (isset($page_css)): ?>
        <?php echo $page_css; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Member Navigation -->
    <nav class="navbar navbar-expand-lg member-navbar fixed-top">
        <div class="container">
            <a class="navbar-brand" href="member_dashboard.php">
                <i class="fas fa-church me-2"></i>
                <?php echo APP_NAME; ?> <span class="fs-6 text-muted">| Member Portal</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#memberNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="memberNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'member_dashboard.php' ? 'active' : ''; ?>" 
                           href="member_dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'member_profile.php' ? 'active' : ''; ?>" 
                           href="member_profile.php">
                            <i class="fas fa-user me-1"></i>My Profile
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'member_donations.php' ? 'active' : ''; ?>" 
                           href="member_donations.php">
                            <i class="fas fa-hand-holding-heart me-1"></i>My Donations
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'member_events.php' ? 'active' : ''; ?>" 
                           href="member_events.php">
                            <i class="fas fa-calendar-alt me-1"></i>Events
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'member_groups.php' ? 'active' : ''; ?>" 
                           href="member_groups.php">
                            <i class="fas fa-users me-1"></i>Groups
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'member_announcements.php' ? 'active' : ''; ?>" 
                           href="member_announcements.php">
                            <i class="fas fa-bullhorn me-1"></i>Announcements
                        </a>
                    </li>
                    
                    <li class="nav-item ms-3">
                        <div class="d-flex align-items-center">
                            <div class="member-avatar me-2">
                                <?php 
                                $name = $current_user_name ?? 'M';
                                $initials = '';
                                $name_parts = explode(' ', $name);
                                foreach ($name_parts as $part) {
                                    if (!empty($part)) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                }
                                echo substr($initials, 0, 2) ?: 'M';
                                ?>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-dark dropdown-toggle p-0" type="button" data-bs-toggle="dropdown">
                                    <?php echo htmlspecialchars($current_user_name ?? 'Member'); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="member_profile.php">
                                        <i class="fas fa-user me-2"></i>My Profile
                                    </a></li>
                                    <li><a class="dropdown-item" href="member_settings.php">
                                        <i class="fas fa-cog me-2"></i>Settings
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                <i class="fas fa-info-circle me-2"></i>
                <?php 
                echo $_SESSION['flash_message'];
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <div class="member-container">