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

// Initialize attempt tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
    $_SESSION['blocked_until'] = 0;
    $_SESSION['captcha_code'] = '';
}

// Check if user is temporarily blocked
$is_blocked = false;
$remaining_time = 0;
if ($_SESSION['blocked_until'] > time()) {
    $is_blocked = true;
    $remaining_time = $_SESSION['blocked_until'] - time();
}

$error = '';
$show_captcha = $_SESSION['login_attempts'] >= 2; // Show CAPTCHA after 2 attempts

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
        $_SESSION['login_attempts']++;
    } else {
        // Check if user is blocked
        if ($is_blocked) {
            $error = 'Too many failed attempts. Please try again later.';
        } else {
            // Validate CAPTCHA if required
            if ($show_captcha) {
                if (empty($_POST['captcha']) || !isset($_SESSION['captcha_code']) || 
                    strtoupper($_POST['captcha']) !== strtoupper($_SESSION['captcha_code'])) {
                    $error = 'Invalid CAPTCHA code. Please try again.';
                    $_SESSION['login_attempts']++;
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
                        // Block user for 30 minutes after 4 attempts
                        $_SESSION['blocked_until'] = time() + 1800;
                        $is_blocked = true;
                        $remaining_time = 1800;
                        $error = 'Too many failed attempts. Your access is blocked for 30 minutes.';
                    } else {
                        $error = 'Invalid username or password';
                        // Show CAPTCHA after 2 attempts
                        $show_captcha = $_SESSION['login_attempts'] >= 2;
                    }
                }
            }
        }
    }
    
    // Regenerate CSRF token after POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generate CAPTCHA if needed
if ($show_captcha && empty($_SESSION['captcha_code'])) {
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

// IP-based security (optional enhancement)
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Login - StyleShop Administration Panel">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login - Monster Store</title>
    <link rel="icon" href="../../assets/images/logo/logo.jpg" type="image/x-icon">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }
        
        .login-section {
            width: 50%;
            padding: 40px;
        }
        
        .slider-section {
            width: 50%;
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            padding: 40px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 32px;
            font-weight: 700;
            color: #4361ee;
            margin-bottom: 10px;
        }
        
        .login-header h1 {
            font-size: 24px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #718096;
            font-size: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3748;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-input:focus {
            border-color: #4361ee;
            outline: none;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
        }
        
        .input-icon .form-input {
            padding-left: 45px;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #718096;
        }
        
        .btn-login {
            width: 100%;
            background: #4361ee;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            background: #3a56d4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 25px 0;
            position: relative;
        }
        
        .divider-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0 15px;
            color: #718096;
            font-size: 14px;
        }
        
        .security-notice {
            background: #f8fafc;
            border-left: 4px solid #4361ee;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .security-notice i {
            color: #4361ee;
            margin-right: 10px;
        }
        
        .error-message {
            background: #fff5f5;
            color: #e53e3e;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e53e3e;
            display: flex;
            align-items: center;
        }
        
        .error-message i {
            margin-right: 10px;
        }
        
        .warning-message {
            background: #fffbeb;
            color: #d97706;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #d97706;
            display: flex;
            align-items: center;
        }
        
        .warning-message i {
            margin-right: 10px;
        }
        
        .captcha-container {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .captcha-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
       .captcha-code {
    font-family: 'Courier New', monospace;
    font-size: 24px;
    font-weight: bold;
    letter-spacing: 5px;
    background: linear-gradient(45deg, #4361ee, #3a0ca3);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    padding: 15px;
    text-align: center;
    margin-bottom: 15px;
    border-radius: 6px;
    border: 1px dashed #cbd5e0;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    cursor: default;
}
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #718096;
            font-size: 14px;
        }
        
        .loader {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hidden {
            display: none;
        }
        
        /* Slider Styles */
        .slider-container {
            position: relative;
            height: 100%;
            overflow: hidden;
            border-radius: 12px;
        }
        
        .slider {
            display: flex;
            transition: transform 0.5s ease-in-out;
            height: 100%;
        }
        
        .slide {
            min-width: 100%;
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .slide-icon {
            font-size: 48px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.15);
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .slide h3 {
            font-size: 24px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .slide p {
            font-size: 16px;
            line-height: 1.6;
            max-width: 300px;
        }
        
        .slider-indicators {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 8px;
        }
        
        .indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .indicator.active {
            background: white;
            transform: scale(1.2);
        }
        
        .text-button {
            background: none;
            border: none;
            color: #4361ee;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .blocked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            z-index: 20;
            padding: 2rem;
        }
        
        .blocked-icon {
            font-size: 48px;
            color: #e53e3e;
            margin-bottom: 16px;
        }
        
        .countdown {
            font-size: 32px;
            font-weight: bold;
            color: #e53e3e;
            font-family: 'Courier New', monospace;
            margin: 16px 0;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                max-width: 450px;
            }
            
            .login-section, .slider-section {
                width: 100%;
            }
            
            .slider-section {
                order: -1;
                min-height: 300px;
            }
        }
        
        @media (max-width: 480px) {
            .login-section {
                padding: 25px;
            }
            
            .login-header h1 {
                font-size: 22px;
            }
            
            .form-input {
                padding: 12px 14px;
            }
        }
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
                <div class="logo">MONSTER STORE </div>
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
    <div class="captcha-code" id="captchaDisplay" oncontextmenu="return false" onmousedown="return false" onselectstart="return false">
        <?php echo $_SESSION['captcha_code']; ?>
    </div>
    <div class="form-group">
        <input type="text" id="captcha" name="captcha" class="form-input" placeholder="Enter CAPTCHA code" required
               autocomplete="off"
               pattern="[A-Z0-9]{6}"
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
                <p>&copy; 2025 Monster Store.| Secure Admin Portal by eTwin Technology</p>
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
            
            // Slider functionality
            const slider = document.getElementById('slider');
            const indicators = document.querySelectorAll('.indicator');
            let currentSlide = 0;
            
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
            
            // Auto slide
            setInterval(() => {
                let nextSlide = (currentSlide + 1) % indicators.length;
                showSlide(nextSlide);
            }, 5000);
            
            // Set up indicator clicks
            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    showSlide(index);
                });
            });
            
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
            
            // Refresh CAPTCHA
            if (refreshCaptchaBtn) {
                refreshCaptchaBtn.addEventListener('click', function() {
                    // This would need to be implemented with AJAX to refresh server-side
                    // For now, we'll just reload the page
                    window.location.reload();
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
            
            // Countdown timer for blocked state
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
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const captchaDisplay = document.getElementById('captchaDisplay');
    
    // Prevent right-click context menu
    captchaDisplay.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Prevent text selection
    captchaDisplay.addEventListener('mousedown', function(e) {
        if (e.button === 0) { // Left mouse button
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
});
</script>
</body>
</html>