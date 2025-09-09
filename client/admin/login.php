<?php
session_start();
require_once '../../config/db.php';
require_once '../../controllers/AdminController.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Initialize attempt tracking with persistent blocking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
    $_SESSION['blocked_until'] = 0;
    $_SESSION['captcha_code'] = '';
}

// Check if user is temporarily blocked - FIXED: Use persistent blocking
$is_blocked = false;
$remaining_time = 0;
if (isset($_SESSION['blocked_until']) && $_SESSION['blocked_until'] > time()) {
    $is_blocked = true;
    $remaining_time = $_SESSION['blocked_until'] - time();
}

$error = '';
$show_captcha = $_SESSION['login_attempts'] >= 2; // Show CAPTCHA after 2 attempts

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// IP-based security (moved before processing form to detect IP changes early)
$client_ip = $_SERVER['REMOTE_ADDR'];
if (!isset($_SESSION['login_ip'])) {
    $_SESSION['login_ip'] = $client_ip;
} elseif ($_SESSION['login_ip'] !== $client_ip) {
    // IP changed during login attempts - reset and block
    session_regenerate_id(true);
    $_SESSION['login_attempts'] = 0;
    $_SESSION['blocked_until'] = time() + 1800;
    $is_blocked = true;
    $remaining_time = 1800;
    $error = 'Security violation detected. Access blocked.';
    $_SESSION['login_ip'] = $client_ip; // Update to new IP
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_blocked) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
        $_SESSION['login_attempts']++;
    } else {
        // Validate CAPTCHA if required
        if ($show_captcha) {
            if (empty($_POST['captcha']) || !isset($_SESSION['captcha_code']) || 
                strtoupper(trim($_POST['captcha'])) !== strtoupper($_SESSION['captcha_code'])) {
                $error = 'Invalid CAPTCHA code. Please try again.';
                $_SESSION['login_attempts']++;
                // Regenerate CAPTCHA after failed attempt
                generateCaptcha();
            }
        }
        
        // If no CAPTCHA error, proceed with login
        if (empty($error)) {
            $adminController = new AdminController();
            
            if ($adminController->login($_POST['username'], $_POST['password'])) {
                // Successful login - reset attempts and regenerate session ID
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = 0;
                $_SESSION['blocked_until'] = 0;
                $_SESSION['captcha_code'] = '';
                
                // Set secure cookie parameters
                setcookie(session_name(), session_id(), [
                    'expires' => time() + 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                
                header('Location: index.php');
                exit();
            } else {
                // Failed login attempt
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                
                if ($_SESSION['login_attempts'] >= 4) {
                    // Block user for 30 minutes after 4 attempts - FIXED: This now persists
                    $_SESSION['blocked_until'] = time() + 1800;
                    $is_blocked = true;
                    $remaining_time = 1800;
                    $error = 'Too many failed attempts. Your access is blocked for 30 minutes.';
                } else {
                    $error = 'Invalid username or password';
                    // Show CAPTCHA after 2 attempts
                    $show_captcha = $_SESSION['login_attempts'] >= 2;
                    // Generate new CAPTCHA after failed attempt
                    if ($show_captcha) {
                        generateCaptcha();
                    }
                }
            }
        }
    }
    
    // Regenerate CSRF token after POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generate CAPTCHA if needed - FIXED: Proper CAPTCHA generation logic
if ($show_captcha && (empty($_SESSION['captcha_code']) || isset($_POST['refresh_captcha']))) {
    generateCaptcha();
}

// CAPTCHA generation function
function generateCaptcha() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789@#&';
    $captcha_code = '';
    for ($i = 0; $i < 6; $i++) {
        $captcha_code .= $chars[rand(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha_code'] = $captcha_code;
}

// Handle CAPTCHA refresh request
if (isset($_GET['refresh_captcha']) && $show_captcha && !$is_blocked) {
    generateCaptcha();
    // Return JSON response for AJAX requests
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['captcha' => $_SESSION['captcha_code']]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Login - Monster Store Administration Panel">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login - Monster Store</title>
    <link rel="icon" href="../../assets/images/logo/logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS remains the same */
    </style>
</head>
<body>
    <div class="container">
        <div class="login-section">
            <?php if ($is_blocked): ?>
            <div class="blocked-overlay">
                <div class="blocked-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h3>Access Temporarily Blocked</h3>
                <p>Too many failed login attempts. Please try again in:</p>
                <div class="countdown" id="countdown"><?php echo gmdate("i:s", $remaining_time); ?></div>
                <p class="footer">For security reasons, your access has been temporarily suspended.</p>
            </div>
            <?php endif; ?>
            
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-dragon logo-icon"></i>
                    <span>MONSTER STORE</span>
                </div>
                <h1>Admin Portal</h1>
                <p>Sign in to access your dashboard</p>
            </div>
            
            <div class="security-notice">
                <i class="fas fa-shield-alt"></i>
                <span>This area is restricted to authorized personnel only.</span>
            </div>
            
            <!-- PHP Error Message -->
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Attempts Warning -->
            <?php if ($_SESSION['login_attempts'] > 0 && !$is_blocked): ?>
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>
                        <?php 
                        $remaining_attempts = 4 - $_SESSION['login_attempts'];
                        echo "Warning: $remaining_attempts attempt(s) remaining before temporary block.";
                        ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Enter your username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               <?php echo $is_blocked ? 'disabled' : ''; ?>
                               autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required 
                               <?php echo $is_blocked ? 'disabled' : ''; ?>
                               autocomplete="current-password">
                        <span class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <!-- CAPTCHA Section -->
                <?php if ($show_captcha && !$is_blocked): ?>
                <div class="captcha-container">
                    <div class="captcha-header">
                        <span class="form-label">Security Verification</span>
                        <button type="button" id="refreshCaptcha" class="text-button">
                            <i class="fas fa-redo"></i> Refresh
                        </button>
                    </div>
                    <div class="captcha-code" id="captchaDisplay">
                        <?php echo $_SESSION['captcha_code']; ?>
                    </div>
                    <div class="form-group">
                        <input type="text" id="captcha" name="captcha" class="form-input" placeholder="Enter CAPTCHA code" required
                               autocomplete="off"
                               pattern="[A-Z0-9@#&]{6}"
                               title="Please enter the 6-character code shown above">
                    </div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-login" id="submitButton"
                        <?php echo $is_blocked ? 'disabled' : ''; ?>>
                    <span id="buttonText">Sign In</span>
                    <span id="buttonLoader" class="hidden">
                        <span class="loader"></span>
                        Processing...
                    </span>
                </button>
            </form>
            
            <div class="footer">
                <p>&copy; 2025 Monster Store | Secure Admin Portal by eTwin Technology</p>
            </div>
        </div>
        
        <div class="slider-section">
            <div class="slider-container">
                <div class="slider" id="slider">
                    <div class="slide">
                        <div class="slide-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Advanced Analytics</h3>
                        <p>Track sales performance and customer insights with our comprehensive dashboard.</p>
                    </div>
                    <div class="slide">
                        <div class="slide-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h3>Inventory Management</h3>
                        <p>Efficiently manage your product inventory and track stock levels in real-time.</p>
                    </div>
                    <div class="slide">
                        <div class="slide-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Customer Management</h3>
                        <p>Access customer profiles, order history, and preferences all in one place.</p>
                    </div>
                </div>
                <div class="slider-indicators" id="indicators">
                    <div class="indicator active"></div>
                    <div class="indicator"></div>
                    <div class="indicator"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');
            const refreshCaptchaBtn = document.getElementById('refreshCaptcha');
            const loginForm = document.getElementById('loginForm');
            const submitButton = document.getElementById('submitButton');
            const captchaDisplay = document.getElementById('captchaDisplay');
            
            // Slider functionality
            const slider = document.getElementById('slider');
            const indicators = document.querySelectorAll('.indicator');
            let currentSlide = 0;
            let slideInterval;
            
            // Set up slider
            function showSlide(index) {
                slider.style.transform = `translateX(-${index * 100}%)`;
                
                // Update indicators
                indicators.forEach((indicator, i) => {
                    if (i === index) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
                
                currentSlide = index;
            }
            
            // Start auto slide
            function startSlider() {
                slideInterval = setInterval(() => {
                    let nextSlide = (currentSlide + 1) % indicators.length;
                    showSlide(nextSlide);
                }, 4500);
            }
            
            // Stop auto slide
            function stopSlider() {
                clearInterval(slideInterval);
            }
            
            // Initialize slider
            startSlider();
            
            // Set up indicator clicks
            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    stopSlider();
                    showSlide(index);
                    startSlider();
                });
            });
            
            // Pause slider on hover
            if (slider) {
                slider.addEventListener('mouseenter', stopSlider);
                slider.addEventListener('mouseleave', startSlider);
            }
            
            // Toggle password visibility
            if (passwordToggle && passwordInput) {
                passwordToggle.addEventListener('click', function() {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        passwordToggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    } else {
                        passwordInput.type = 'password';
                        passwordToggle.innerHTML = '<i class="fas fa-eye"></i>';
                    }
                });
            }
            
            // Refresh CAPTCHA with AJAX - FIXED: Proper CAPTCHA refresh
            if (refreshCaptchaBtn) {
                refreshCaptchaBtn.addEventListener('click', function() {
                    fetch('?refresh_captcha=1&ajax=1')
                        .then(response => response.json())
                        .then(data => {
                            if (data.captcha) {
                                captchaDisplay.textContent = data.captcha;
                            }
                        })
                        .catch(error => {
                            console.error('Error refreshing CAPTCHA:', error);
                            // Fallback to page reload if AJAX fails
                            window.location.href = '?refresh_captcha=1';
                        });
                });
            }
            
            // Form submission
            if (loginForm && submitButton) {
                loginForm.addEventListener('submit', function(e) {
                    const buttonText = document.getElementById('buttonText');
                    const buttonLoader = document.getElementById('buttonLoader');
                    
                    if (buttonText && buttonLoader) {
                        buttonText.classList.add('hidden');
                        buttonLoader.classList.remove('hidden');
                        submitButton.disabled = true;
                    }
                    
                    // Form will submit normally
                });
            }
            
            // Countdown timer for blocked state - FIXED: Proper countdown
            <?php if ($is_blocked): ?>
            let timeLeft = <?php echo $remaining_time; ?>;
            const countdownElement = document.getElementById('countdown');
            
            const countdownInterval = setInterval(function() {
                timeLeft--;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    window.location.reload();
                } else {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }
            }, 1000);
            <?php endif; ?>
            
            // Clear error when typing
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const errorMessage = document.querySelector('.error-message');
                    if (errorMessage) {
                        errorMessage.style.display = 'none';
                    }
                });
            });
            
            // Add input validation
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.addEventListener('input', function(e) {
                    // Basic sanitization
                    e.target.value = e.target.value.replace(/[^a-zA-Z0-9_@.-]/g, '');
                });
            }
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // CAPTCHA protection
            if (captchaDisplay) {
                // Prevent right-click context menu
                captchaDisplay.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // Prevent text selection
                captchaDisplay.addEventListener('mousedown', function(e) {
                    if (e.button === 0) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // Prevent drag starting
                captchaDisplay.addEventListener('dragstart', function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // Add visual feedback when user tries to select
                captchaDisplay.addEventListener('mousedown', function() {
                    this.style.backgroundColor = 'rgba(0,0,0,0.05)';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 200);
                });
            }
        });
    </script>
</body>
</html>