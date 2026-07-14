<?php
// footer.php - Common footer for all pages
?>
    </div> <!-- Close main-container -->

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-church me-2"></i><?php echo APP_NAME ?? 'ChurchMS'; ?></h5>
                    <p class="mt-3" style="opacity: 0.8;">
                        Connecting faith, community, and technology to serve you better.
                    </p>
                    <div class="social-links">
                        <a href="facebook.com/eastwoodanabaministriesofficialpage" class="me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="twitter.com/eastwoodanaba" class="me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="instagram.com/eastwoodanabaministries" class="me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="www.youtube.com/@EastwoodAnaba" class="me-3"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled mt-3">
                        <li class="mb-2"><a href="index.php"><i class="fas fa-chevron-right me-2 small"></i>Home</a></li>
                        <li class="mb-2"><a href="about.php"><i class="fas fa-chevron-right me-2 small"></i>About Us</a></li>
                        <li class="mb-2"><a href="events.php"><i class="fas fa-chevron-right me-2 small"></i>Events</a></li>
                        <li class="mb-2"><a href="contact.php"><i class="fas fa-chevron-right me-2 small"></i>Contact</a></li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li class="mb-2"><a href="register.php"><i class="fas fa-chevron-right me-2 small"></i>Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5>Contact Info</h5>
                    <ul class="list-unstyled mt-3">
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            Opposite Police Headquaters, Bolga
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone me-2"></i>
                            0553 358 568
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2"></i>
                            info@desertpastures.org
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-clock me-2"></i>
                            Mon-Fri: 9am - 5pm
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="bg-light opacity-25">
            
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0 small" style="opacity: 0.7;">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME ?? 'Church Management System'; ?>. 
                        All rights reserved. | 
                        <a href="privacy.php" class="text-white">Privacy Policy</a> | 
                        <a href="terms.php" class="text-white">Terms of Service</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (optional) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Auto-hide flash messages after 5 seconds
        setTimeout(function() {
            const flashMessage = document.querySelector('.flash-message');
            if (flashMessage) {
                flashMessage.style.transition = 'opacity 0.5s';
                flashMessage.style.opacity = '0';
                setTimeout(() => flashMessage.remove(), 500);
            }
        }, 5000);
        
        // Add active class to current nav item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage || (currentPage === '' && href === 'index.php')) {
                    link.classList.add('active');
                }
            });
        });
        
        // Smooth scrolling for anchor links
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
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.backdropFilter = 'blur(10px)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.15)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            }
        });
    </script>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($page_js)): ?>
        <?php echo $page_js; ?>
    <?php endif; ?>
</body>
</html>