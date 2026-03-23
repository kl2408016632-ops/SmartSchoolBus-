<?php
/**
 * SelamatRide SmartSchoolBus
 * Homepage
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SelamatRide SmartSchoolBus - Secure RFID Student Boarding System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- HEADER / NAVIGATION -->
    <header class="header" id="header">
        <div class="nav-container">
            <a href="index.php" class="logo">SmartSchoolBus</a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="#about">About</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ul>
                <div class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </nav>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-content">
            <h1>Every Student. Verified. Safe. Every Journey.</h1>
            <p>RFID-powered boarding verification system. Real-time tracking. Complete peace of mind for schools and parents.</p>
            <div class="hero-buttons">
                <a href="login.php" class="btn-primary">Login</a>
                <a href="#about" class="btn-secondary">Learn More</a>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="section" id="about">
        <div class="container">
            <div class="section-header">
                <div class="section-label">ABOUT US</div>
                <h2 class="section-title">Building Safer School Journeys</h2>
            </div>
            
            <div class="about-grid">
                <div class="about-content">
                    <p>SelamatRide was born from a simple mission: ensure every child arrives safely, every day. We combine IoT innovation with school transport management to create a seamless, secure boarding verification system.</p>
                    <p>Our RFID-powered system eliminates manual attendance errors, provides real-time visibility, and gives schools complete control over student safety.</p>
                </div>
                
                <div class="trust-cards">
                    <div class="trust-card">
                        <h4>🛡️ 10,000+ Students Protected</h4>
                        <p>Trusted by schools across Malaysia</p>
                    </div>
                    <div class="trust-card">
                        <h4>🎯 99.9% Verification Accuracy</h4>
                        <p>Automated RFID precision</p>
                    </div>
                    <div class="trust-card">
                        <h4>⚡ Real-Time Monitoring</h4>
                        <p>Instant updates to all stakeholders</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SYSTEM EXPLANATION -->
    <section class="section" style="background: var(--light-gray);">
        <div class="container">
            <div class="section-header">
                <div class="section-label">HOW IT WORKS</div>
                <h2 class="section-title">Seamless RFID Boarding Verification</h2>
                <p class="section-subtitle">From hardware to dashboard – complete visibility in seconds</p>
            </div>
            
            <div class="process-flow">
                <div class="process-card">
                    <div class="process-icon">📇</div>
                    <h3>Tap RFID Card</h3>
                    <p>Student taps their unique RFID card on the bus reader. Instant identification.</p>
                </div>
                
                <div class="process-card">
                    <div class="process-icon">🔍</div>
                    <h3>Device Verification</h3>
                    <p>ESP32 device instantly verifies the card against registered students.</p>
                </div>
                
                <div class="process-card">
                    <div class="process-icon">☁️</div>
                    <h3>Real-Time Sync</h3>
                    <p>Attendance data securely transmitted to central database via API.</p>
                </div>
                
                <div class="process-card">
                    <div class="process-icon">📊</div>
                    <h3>Live Updates</h3>
                    <p>Parents, staff, and admins see boarding status instantly on their dashboards.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- SECURITY SECTION -->
    <section class="section security-section">
        <div class="container">
            <div class="section-header">
                <div class="section-label" style="color: #60a5fa;">ENTERPRISE SECURITY</div>
                <h2 class="section-title" style="color: var(--white);">Built for School-Grade Safety & Compliance</h2>
            </div>
            
            <div class="security-grid">
                <div class="security-visual">
                    <div class="security-shield">🔒</div>
                </div>
                
                <div class="security-features">
                    <div class="security-item">
                        <div class="security-icon">📇</div>
                        <div>
                            <h4>Unique RFID Identification</h4>
                            <p>Each student assigned a unique, non-transferable RFID tag</p>
                        </div>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-icon">🔐</div>
                        <div>
                            <h4>256-bit Encryption</h4>
                            <p>All data transmission secured with industry-standard encryption</p>
                        </div>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-icon">👥</div>
                        <div>
                            <h4>Granular Permissions</h4>
                            <p>Staff only see what they need. Admins have full control.</p>
                        </div>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-icon">📋</div>
                        <div>
                            <h4>Complete Activity Logs</h4>
                            <p>Every login, change, and record tracked with timestamp</p>
                        </div>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-icon">🔑</div>
                        <div>
                            <h4>Protected Authentication</h4>
                            <p>Password hashing + session management + auto-timeout</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT SECTION -->
    <section class="section" id="contact">
        <div class="container">
            <div class="section-header">
                <div class="section-label">GET IN TOUCH</div>
                <h2 class="section-title">Let's Talk About Your School's Safety</h2>
                <p class="section-subtitle">Reach out for demos, pricing, or technical support</p>
            </div>
            
            <div class="contact-card">
                <div class="contact-grid">
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <h4>Call Us</h4>
                        <p><a href="tel:+60129485240">+60-129485240</a></p>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">📍</div>
                        <h4>Visit Us</h4>
                        <p>Blok AH1-5 Jalan Persiaran Tecoma,<br>43900 Sepang, Selangor</p>
                    </div>
                    
                    <div class="contact-item">
                        <h4>Follow Us</h4>
                        <div class="social-links">
                            <a href="https://www.instagram.com/muhdihsan15._" target="_blank" class="social-icon" title="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="https://www.facebook.com/share/1ASytyKLgj/" target="_blank" class="social-icon" title="Facebook"><i class="fab fa-facebook"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">SelamatRide SmartSchoolBus</div>
            <ul class="footer-links">
                <li><a href="#about">About</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
            </ul>
            <div class="footer-copyright">© 2026 SelamatRide. All rights reserved.</div>
        </div>
    </footer>

    <script>
        // Sticky header on scroll
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
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
    </script>
</body>
</html>
