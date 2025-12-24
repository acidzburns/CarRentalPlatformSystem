<?php
/**
 * Home/Landing Page
 * Entry point for the car rental system
 */

require_once 'config/session.php';

// Check if user is logged in and redirect to appropriate dashboard
//if (is_logged_in()) {
//    redirect_to_dashboard();
//}

// Check for logout message
$message = "";
if (isset($_GET['message']) && $_GET['message'] == 'logged_out') {
    $message = "You have been successfully logged out.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental Platform - Home</title>
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M5 17H4a2 2 0 01-2-2V9a2 2 0 012-2h1m15 10h1a2 2 0 002-2V9a2 2 0 00-2-2h-1m-5 10a2 2 0 100-4 2 2 0 000 4zm-8 0a2 2 0 100-4 2 2 0 000 4z"/>
                    <path d="M5 7l1.5-3h11L19 7"/>
                </svg>
                <span>CarRental</span>
            </div>
            
            <div class="nav-links">
                <a href="#about">About</a>
                <a href="#services">Services</a>
                <a href="#contact">Reach Us</a>
                
                <div class="dropdown">
                    <a href="#" class="dropbtn">Login</a>
                    <div class="dropdown-content">
                        <a href="auth/renter_login.php">Customer Login</a>
                        <a href="auth/lessor_login.php">Lessor Login</a>
                        <a href="auth/admin_login.php">Admin Login</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <h1>Your Trusted Car Rental Platform</h1>
            <p>Rent cars easily or list your vehicle for rental. Safe, convenient, and reliable.</p>
            
            <div class="hero-buttons">
                <a href="auth/renter_login.php" class="btn btn-primary">Rent a Car</a>
                <a href="auth/lessor_login.php" class="btn btn-secondary">List Your Car</a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="features">
        <h2>Why Choose Us?</h2>
        
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
                    </svg>
                </div>
                <h3>Verified Users</h3>
                <p>All users and vehicle owners are verified for your safety and security.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <h3>Secure Booking</h3>
                <p>Your bookings and payments are protected with industry-standard security.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <h3>24/7 Support</h3>
                <p>Our customer support team is always ready to help you anytime.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <h3>Earn Money</h3>
                <p>List your car and start earning passive income today.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <h3>Quality Vehicles</h3>
                <p>Browse from a wide selection of well-maintained, quality vehicles.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                        <path d="M5 17H4a2 2 0 01-2-2V9a2 2 0 012-2h1m15 10h1a2 2 0 002-2V9a2 2 0 00-2-2h-1"/>
                        <circle cx="9" cy="17" r="2"/>
                        <circle cx="15" cy="17" r="2"/>
                        <path d="M5 9l1.5-4.5h11L19 9"/>
                    </svg>
                </div>
                <h3>Easy Process</h3>
                <p>Simple booking process - search, book, and drive in minutes.</p>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="about-container">
            <h2>About Our Platform</h2>
            <div class="about-content">
                <p>Our Car Rental Platform is designed to digitalize the vehicle rental process, making it convenient, fast, and reliable for everyone. We connect car renters with vehicle owners through a secure and user-friendly platform.</p>
                
                <h3>What We Offer</h3>
                <p>We provide a comprehensive digital solution that allows customers to browse and book cars online, enables lessors to list and manage their vehicles efficiently, and gives administrators full control over verification and system monitoring.</p>
                
                <h3>Why Choose Digital</h3>
                <p>Traditional car rental processes through WhatsApp, calls, or physical visits often lead to miscommunication, double booking, and delays. Our platform solves these problems by automating bookings, providing real-time availability, and maintaining accurate records.</p>
                
                <h3>Our Benefits</h3>
                <ul>
                    <li>Instant online booking with real-time car availability</li>
                    <li>Centralized dashboard for lessors to track earnings and manage listings</li>
                    <li>Complete visibility for admins with user verification and financial reports</li>
                    <li>Reduced time on manual communication and eliminated booking errors</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="services-container">
            <h2>Our Services</h2>
            <div class="services-grid">
                <div class="service-card">
                    <h3>For Customers</h3>
                    <ul>
                        <li>Browse verified vehicles</li>
                        <li>Real-time availability checking</li>
                        <li>Secure online booking</li>
                        <li>Instant booking confirmation</li>
                        <li>Booking history tracking</li>
                        <li>24/7 customer support</li>
                    </ul>
                </div>
                
                <div class="service-card">
                    <h3>For Lessors</h3>
                    <ul>
                        <li>List multiple vehicles</li>
                        <li>Manage vehicle availability</li>
                        <li>Track earnings and bookings</li>
                        <li>Set your own pricing</li>
                        <li>Automated booking management</li>
                        <li>Performance analytics</li>
                    </ul>
                </div>
                
                <div class="service-card">
                    <h3>Platform Features</h3>
                    <ul>
                        <li>User verification system</li>
                        <li>Secure payment processing</li>
                        <li>Comprehensive admin dashboard</li>
                        <li>System monitoring tools</li>
                        <li>Financial reporting</li>
                        <li>Data accuracy and transparency</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="contact-container">
            <h2>Reach Us</h2>
            <p>Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
            
            <form class="contact-form" action="mailto:carrentalplatformsystem@gmail.com" method="post" enctype="text/plain">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
            
            <div class="contact-info">
                <p><strong>Email:</strong> carrentalplatformsystem@gmail.com</p>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta">
        <h2>Ready to Get Started?</h2>
        <p>Join thousands of satisfied customers and car owners on our platform today.</p>
        
        <div class="cta-buttons">
            <a href="auth/renter_register.php" class="btn btn-primary">Browse Cars</a>
            <a href="auth/lessor_register.php" class="btn btn-secondary">Become a Lessor</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 Car Rental Platform. All rights reserved.</p>
    </footer>
</body>
</html>