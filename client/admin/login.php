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
    <meta name="description" content="Admin Login - Monster Store Administration Panel">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login - Monster Store</title>
    <link rel="icon" href="../../assets/images/logo/logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #c7d2fe;
            --secondary-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
            --radius: 12px;
            --radius-sm: 8px;
        }
        
        body {
            background: var(--bg-light);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }
        
        .container {
            display: flex;
            width: 100%;
            max-width: 900px;
            background: var(--bg-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }
        
        .login-section {
            width: 50%;
            padding: 32px;
            position: relative;
        }
        
        .slider-section {
            width: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 32px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .logo-icon {
            font-size: 28px;
        }
        
        .login-header h1 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 6px;
            font-weight: 600;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 13px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 15px;
            transition: var(--transition);
            background: var(--bg-white);
        }
        
        .form-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 15px;
        }
        
        .input-icon .form-input {
            padding-left: 40px;
        }
        
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            transition: var(--transition);
            font-size: 15px;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .btn-login {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-md);
        }
        
        .btn-login:active {
            transform: translateY(1px);
        }
        
        .security-notice {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0c4a6e;
        }
        
        .security-notice i {
            color: #0ea5e9;
        }
        
        .error-message {
            background: #fef2f2;
            color: var(--error-color);
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            animation: shake 0.4s ease-in-out;
        }
        
        .warning-message {
            background: #fffbeb;
            color: var(--warning-color);
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            border: 1px solid #fef3c7;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        
        .captcha-container {
            background: var(--bg-light);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            animation: slideIn 0.3s ease-out;
        }
        
        .captcha-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .captcha-code {
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 4px;
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            padding: 12px;
            text-align: center;
            margin-bottom: 12px;
            border-radius: var(--radius-sm);
            border: 1px dashed #d1d5db;
            user-select: none;
            cursor: default;
        }
        
        .footer {
            text-align: center;
            margin-top: 24px;
            color: peru;
            font-size: 13px;
        }
        
        .loader {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .hidden {
            display: none;
        }
        
        /* Slider Styles */
        .slider-container {
            position: relative;
            height: 100%;
            overflow: hidden;
            border-radius: var(--radius-sm);
        }
        
        .slider {
            display: flex;
            transition: transform 0.4s ease-in-out;
            height: 100%;
        }
        
        .slide {
            min-width: 100%;
            padding: 16px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .slide-icon {
            font-size: 36px;
            margin-bottom: 16px;
            background: rgba(255, 255, 255, 0.15);
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .slide h3 {
            font-size: 18px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .slide p {
            font-size: 14px;
            line-height: 1.5;
            max-width: 260px;
            opacity: 0.9;
        }
        
        .slider-indicators {
            position: absolute;
            bottom: 16px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 6px;
        }
        
        .indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .indicator.active {
            background: white;
            transform: scale(1.2);
        }
        
        .text-button {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
            padding: 4px;
        }
        
        .text-button:hover {
            color: var(--primary-dark);
        }
        
        .blocked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
            z-index: 20;
            padding: 24px;
            text-align: center;
        }
        
        .blocked-icon {
            font-size: 36px;
            color: var(--error-color);
            margin-bottom: 12px;
        }
        
        .countdown {
            font-size: 24px;
            font-weight: bold;
            color: var(--error-color);
            font-family: 'Courier New', monospace;
            margin: 12px 0;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-12px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                max-width: 400px;
            }
            
            .login-section, .slider-section {
                width: 100%;
            }
            
            .slider-section {
                order: -1;
                min-height: 200px;
                padding: 24px;
            }
            
            .login-section {
                padding: 24px;
            }
            
            .slide-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
                margin-bottom: 16px;
            }
            
            .slide h3 {
                font-size: 16px;
            }
            
            .slide p {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                border-radius: var(--radius-sm);
            }
            
            .login-section {
                padding: 20px;
            }
            
            .slider-section {
                padding: 20px;
                min-height: 180px;
            }
            
            .login-header h1 {
                font-size: 18px;
            }
            
            .form-input {
                padding: 10px 12px;
                font-size: 14px;
            }
            
            .input-icon .form-input {
                padding-left: 36px;
            }
            
            .input-icon i {
                font-size: 14px;
                left: 12px;
            }
            
            .btn-login {
                padding: 10px;
                font-size: 14px;
            }
            
            .slide-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .slide h3 {
                font-size: 15px;
            }
            
            .slide p {
                font-size: 12px;
                max-width: 220px;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #f3f4f6;
                --text-secondary: #d1d5db;
                --text-light: #9ca3af;
                --bg-light: #111827;
                --bg-white: #1f2937;
                --border-color: #374151;
            }
            
            .login-section {
                background: var(--bg-white);
            }
            
            .form-input {
                background: #374151;
                color: var(--text-primary);
                border-color: var(--border-color);
            }
            
            .security-notice {
                background: #1e3a8a;
                border-color: #1d4ed8;
                color: #dbeafe;
            }
            
            .error-message {
                background: #7f1d1d;
                border-color: #b91c1c;
                color: #fecaca;
            }
            
            .warning-message {
                background: #78350f;
                border-color: #b45309;
                color: #fde68a;
            }
            
            .captcha-container {
                background: var(--bg-light);
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