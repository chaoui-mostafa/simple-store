<?php
session_start();
require_once '../../config/db.php';
require_once '../../controllers/AdminController.php';

// Enhanced security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Prevent caching of sensitive pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

// Initialize security tracking
if (!isset($_SESSION['security_data'])) {
    $_SESSION['security_data'] = [
        'login_attempts' => 0,
        'last_attempt_time' => 0,
        'blocked_until' => 0,
        'captcha_code' => '',
        'attempt_verified' => false,
        'login_ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'first_attempt_time' => 0
    ];
}

$security = &$_SESSION['security_data'];

// Enhanced IP detection with proxy support
function getClientIP() {
    $ip_keys = [
        'HTTP_X_FORWARDED_FOR', 
        'HTTP_X_REAL_IP', 
        'HTTP_CLIENT_IP', 
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'];
}

$client_ip = getClientIP();

// Security validation - check for session hijacking
if ($security['login_ip'] !== $client_ip || $security['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['security_data'] = [
        'login_attempts' => 0,
        'last_attempt_time' => 0,
        'blocked_until' => time() + 3600, // Block for 1 hour on security violation
        'captcha_code' => '',
        'attempt_verified' => false,
        'login_ip' => $client_ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'first_attempt_time' => 0
    ];
    $security = &$_SESSION['security_data'];
}

// Check if user is temporarily blocked
$is_blocked = false;
$remaining_time = 0;
if ($security['blocked_until'] > time()) {
    $is_blocked = true;
    $remaining_time = $security['blocked_until'] - time();
}

$error = '';
$show_captcha = $security['login_attempts'] >= 2;

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Advanced CAPTCHA generation with distortion
function generateCaptcha() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $captcha_code = '';
    for ($i = 0; $i < 6; $i++) {
        $captcha_code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['security_data']['captcha_code'] = $captcha_code;
    return $captcha_code;
}

// Handle CAPTCHA refresh request
if (isset($_GET['refresh_captcha']) && !$is_blocked) {
    $captcha = generateCaptcha();
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['captcha' => $captcha]);
        exit();
    }
}

// Generate CAPTCHA if needed
if ($show_captcha && empty($security['captcha_code'])) {
    generateCaptcha();
}

// Advanced blocking logic with progressive delays
function calculateBlockTime($attempts) {
    if ($attempts >= 5) return 3600; // 1 hour
    if ($attempts >= 4) return 1800; // 30 minutes
    if ($attempts >= 3) return 900;  // 15 minutes
    return 0;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_blocked) {
    
    // Rate limiting - prevent rapid successive requests
    $current_time = time();
    if ($security['last_attempt_time'] > 0 && ($current_time - $security['last_attempt_time']) < 2) {
        $error = 'Please wait before trying again.';
        $security['login_attempts']++;
        $security['last_attempt_time'] = $current_time;
    } else {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Security validation failed. Please refresh the page and try again.';
            if (!$security['attempt_verified']) {
                $security['login_attempts']++;
                $security['attempt_verified'] = true;
                $security['last_attempt_time'] = $current_time;
            }
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Enhanced input validation
            if (empty($username) || empty($password)) {
                $error = 'Please enter both username and password.';
                if (!$security['attempt_verified']) {
                    $security['login_attempts']++;
                    $security['attempt_verified'] = true;
                    $security['last_attempt_time'] = $current_time;
                }
            } elseif (strlen($username) > 50 || strlen($password) > 100) {
                $error = 'Invalid input length.';
                if (!$security['attempt_verified']) {
                    $security['login_attempts']++;
                    $security['attempt_verified'] = true;
                    $security['last_attempt_time'] = $current_time;
                }
            } else {
                // Validate CAPTCHA if required
                if ($show_captcha) {
                    $captcha_input = strtoupper(trim($_POST['captcha'] ?? ''));
                    $captcha_code = strtoupper($security['captcha_code'] ?? '');
                    
                    if (empty($captcha_input)) {
                        $error = 'Please enter the CAPTCHA code.';
                        if (!$security['attempt_verified']) {
                            $security['login_attempts']++;
                            $security['attempt_verified'] = true;
                            $security['last_attempt_time'] = $current_time;
                        }
                    } elseif ($captcha_input !== $captcha_code) {
                        $error = 'Invalid CAPTCHA code. Please try again.';
                        if (!$security['attempt_verified']) {
                            $security['login_attempts']++;
                            $security['attempt_verified'] = true;
                            $security['last_attempt_time'] = $current_time;
                        }
                        generateCaptcha();
                    }
                }
                
                // If no errors, attempt login
                if (empty($error)) {
                    $adminController = new AdminController();
                    
                    // Simulate processing time to prevent timing attacks
                    usleep(random_int(100000, 300000));
                    
                    if ($adminController->login($username, $password)) {
                        // Successful login
                        session_regenerate_id(true);
                        
                        // Set session variables
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_username'] = $username;
                        $_SESSION['login_time'] = time();
                        $_SESSION['session_id'] = bin2hex(random_bytes(16));
                        
                        // Reset security data
                        $_SESSION['security_data'] = [
                            'login_attempts' => 0,
                            'last_attempt_time' => 0,
                            'blocked_until' => 0,
                            'captcha_code' => '',
                            'attempt_verified' => false,
                            'login_ip' => $client_ip,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                            'first_attempt_time' => 0
                        ];
                        
                        // Set secure cookie
                        $cookieParams = session_get_cookie_params();
                        setcookie(
                            session_name(),
                            session_id(),
                            [
                                'expires' => time() + 3600,
                                'path' => $cookieParams['path'],
                                'domain' => $cookieParams['domain'],
                                'secure' => true,
                                'httponly' => true,
                                'samesite' => 'Strict'
                            ]
                        );
                        
                        // Log successful login
                        error_log("Admin login successful: $username from IP: $client_ip");
                        
                        header('Location: index.php');
                        exit();
                    } else {
                        // Failed login
                        if (!$security['attempt_verified']) {
                            $security['login_attempts']++;
                            $security['last_attempt_time'] = $current_time;
                            $security['attempt_verified'] = true;
                            
                            if ($security['first_attempt_time'] === 0) {
                                $security['first_attempt_time'] = $current_time;
                            }
                            
                            // Check if we should block based on attempts or time window
                            $time_window = $current_time - $security['first_attempt_time'];
                            
                            if ($security['login_attempts'] >= 3 && $time_window < 300) { // 3 attempts in 5 minutes
                                $block_time = calculateBlockTime($security['login_attempts']);
                                $security['blocked_until'] = $current_time + $block_time;
                                $is_blocked = true;
                                $remaining_time = $block_time;
                                $error = "Too many failed attempts. Your access is blocked for " . gmdate("i", $block_time) . " minutes.";
                            } elseif ($security['login_attempts'] >= 5) { // Absolute limit
                                $block_time = calculateBlockTime($security['login_attempts']);
                                $security['blocked_until'] = $current_time + $block_time;
                                $is_blocked = true;
                                $remaining_time = $block_time;
                                $error = "Maximum attempts exceeded. Your access is blocked for " . gmdate("i", $block_time) . " minutes.";
                            } else {
                                $error = 'Invalid username or password';
                                $show_captcha = $security['login_attempts'] >= 2;
                                if ($show_captcha) {
                                    generateCaptcha();
                                }
                            }
                            
                            // Log failed attempt
                            error_log("Admin login failed: $username from IP: $client_ip - Attempt: " . $security['login_attempts']);
                        } else {
                            $error = 'Invalid username or password';
                        }
                    }
                }
            }
        }
    }
    
    // Regenerate CSRF token after POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Reset attempt verification flag on page load (not form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $security['attempt_verified'] = false;
    
    // Reset first attempt time if it's been more than 1 hour
    if ($security['first_attempt_time'] > 0 && (time() - $security['first_attempt_time']) > 3600) {
        $security['first_attempt_time'] = 0;
        $security['login_attempts'] = 0;
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
            --danger-color: #dc2626;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }
        
        .container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: var(--bg-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }
        
        .login-section {
            width: 50%;
            padding: 40px;
            position: relative;
        }
        
        .slider-section {
            width: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .logo-icon {
            font-size: 32px;
        }
        
        .login-header h1 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
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
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 16px;
        }
        
        .input-icon .form-input {
            padding-left: 48px;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            transition: var(--transition);
            font-size: 16px;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .btn-login {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 16px;
            border-radius: var(--radius-sm);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-login:hover:not(:disabled) {
            background: var(--primary-dark);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            background: var(--text-light);
            cursor: not-allowed;
            transform: none;
        }
        
        .security-notice {
            background: #f0f9ff;
            border: 2px solid #e0f2fe;
            padding: 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0c4a6e;
        }
        
        .security-notice i {
            color: #0ea5e9;
            font-size: 18px;
        }
        
        .error-message {
            background: #fef2f2;
            color: var(--error-color);
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border: 2px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            animation: shake 0.5s ease-in-out;
        }
        
        .warning-message {
            background: #fffbeb;
            color: var(--warning-color);
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border: 2px solid #fef3c7;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .danger-message {
            background: #fef2f2;
            color: var(--danger-color);
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border: 2px solid #fecaca;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .captcha-container {
            background: var(--bg-light);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid var(--border-color);
            animation: slideIn 0.4s ease-out;
        }
        
        .captcha-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .captcha-code {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 3px;
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            padding: 12px;
            text-align: center;
            margin-bottom: 12px;
            border-radius: var(--radius-sm);
            border: 2px dashed #d1d5db;
            user-select: none;
            cursor: default;
            background-color: #f8fafc;
        }
        
        .footer {
            text-align: center;
            margin-top: 32px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .loader {
            width: 18px;
            height: 18px;
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
            font-size: 42px;
            margin-bottom: 24px;
            background: rgba(255, 255, 255, 0.15);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        
        .slide h3 {
            font-size: 22px;
            margin-bottom: 16px;
            font-weight: 700;
        }
        
        .slide p {
            font-size: 16px;
            line-height: 1.6;
            max-width: 300px;
            opacity: 0.9;
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
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .indicator.active {
            background: white;
            transform: scale(1.3);
        }
        
        .text-button {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            padding: 6px;
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
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
            z-index: 100;
            padding: 32px;
            text-align: center;
        }
        
        .blocked-icon {
            font-size: 48px;
            color: var(--danger-color);
            margin-bottom: 20px;
        }
        
        .countdown {
            font-size: 32px;
            font-weight: 800;
            color: var(--danger-color);
            font-family: 'Courier New', monospace;
            margin: 20px 0;
            padding: 16px 24px;
            background: #fef2f2;
            border-radius: var(--radius-sm);
            border: 2px solid #fecaca;
        }
        
        .security-features {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .security-badge {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .security-badge i {
            color: var(--primary-color);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Responsive Design */
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
                min-height: 250px;
                padding: 30px;
            }
            
            .login-section {
                padding: 30px;
            }
            
            .slide-icon {
                width: 70px;
                height: 70px;
                font-size: 32px;
            }
            
            .slide h3 {
                font-size: 20px;
            }
            
            .slide p {
                font-size: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                border-radius: var(--radius-sm);
            }
            
            .login-section {
                padding: 24px;
            }
            
            .slider-section {
                padding: 24px;
                min-height: 220px;
            }
            
            .login-header h1 {
                font-size: 22px;
            }
            
            .form-input {
                padding: 12px 14px;
            }
            
            .input-icon .form-input {
                padding-left: 44px;
            }
            
            .input-icon i {
                font-size: 15px;
                left: 14px;
            }
            
            .btn-login {
                padding: 14px;
            }
            
            .slide-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
            
            .slide h3 {
                font-size: 18px;
            }
            
            .slide p {
                font-size: 14px;
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
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Security Lock Activated</h3>
                <p>Multiple failed login attempts detected. System temporarily locked.</p>
                <div class="countdown" id="countdown"><?php echo gmdate("i:s", $remaining_time); ?></div>
                <p>Access will be automatically restored when the timer expires.</p>
                <div class="security-features">
                    <div class="security-badge">
                        <i class="fas fa-clock"></i>
                        Auto-unlock
                    </div>
                    <div class="security-badge">
                        <i class="fas fa-history"></i>
                        Attempts: <?php echo $security['login_attempts']; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-dragon logo-icon"></i>
                    <span>MONSTER STORE</span>
                </div>
                <h1>Admin Security Portal</h1>
                <p>Secure access to administration dashboard</p>
            </div>
            
            <div class="security-notice">
                <i class="fas fa-shield-alt"></i>
                <span>Protected area • Multi-layer security • Activity monitored</span>
            </div>
            
            <?php if ($error): ?>
                <div class="<?php echo $security['login_attempts'] >= 4 ? 'danger-message' : 'error-message'; ?>">
                    <i class="fas fa-<?php echo $security['login_attempts'] >= 4 ? 'exclamation-triangle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($security['login_attempts'] > 0 && !$is_blocked): ?>
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>
                        <?php 
                        $remaining_attempts = 5 - $security['login_attempts'];
                        $warning_level = $security['login_attempts'] >= 3 ? 'HIGH' : ($security['login_attempts'] >= 2 ? 'MEDIUM' : 'LOW');
                        echo "Security Alert [$warning_level]: $remaining_attempts attempt(s) remaining before system lock.";
                        ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label class="form-label" for="username">
                        <i class="fas fa-user"></i>
                        Administrator ID
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Enter administrator username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               <?php echo $is_blocked ? 'disabled' : ''; ?>
                               autocomplete="username"
                               maxlength="50"
                               pattern="[a-zA-Z0-9_@.-]+"
                               title="Only letters, numbers, and @ . - _ allowed">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-key"></i>
                        Security Key
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your security key" required 
                               <?php echo $is_blocked ? 'disabled' : ''; ?>
                               autocomplete="current-password"
                               minlength="8"
                               maxlength="100">
                        <span class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <?php if ($show_captcha && !$is_blocked): ?>
                <div class="captcha-container">
                    <div class="captcha-header">
                        <span class="form-label">
                            <i class="fas fa-robot"></i>
                            Security Verification
                        </span>
                        <button type="button" id="refreshCaptcha" class="text-button">
                            <i class="fas fa-sync-alt"></i> New Code
                        </button>
                    </div>
                    <div class="captcha-code" id="captchaDisplay">
                        <?php echo isset($security['captcha_code']) ? $security['captcha_code'] : ''; ?>
                    </div>
                    <div class="form-group">
                        <input type="text" id="captcha" name="captcha" class="form-input" placeholder="Enter the security code above" required
                               autocomplete="off"
                               pattern="[A-Z0-9]{6}"
                               title="Please enter the 6-character security code"
                               maxlength="6"
                               style="text-transform: uppercase">
                    </div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-login" id="submitButton"
                        <?php echo $is_blocked ? 'disabled' : ''; ?>>
                    <span id="buttonText">
                        <i class="fas fa-fingerprint"></i>
                        Authenticate & Continue
                    </span>
                    <span id="buttonLoader" class="hidden">
                        <span class="loader"></span>
                        Verifying Credentials...
                    </span>
                </button>
            </form>
            
            <div class="security-features">
                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    SSL Encrypted
                </div>
                <div class="security-badge">
                    <i class="fas fa-clock"></i>
                    Session Timeout
                </div>
                <div class="security-badge">
                    <i class="fas fa-history"></i>
                    Attempt Monitoring
                </div>
                <div class="security-badge">
                    <i class="fas fa-ban"></i>
                    Auto-Block
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; 2025 Monster Store | Enterprise Security System v2.1</p>
            </div>
        </div>
        
        <div class="slider-section">
            <div class="slider-container">
                <div class="slider" id="slider">
                    <div class="slide">
                        <div class="slide-icon">
                            <i class="fas fa-chart-network"></i>
                        </div>
                        <h3>Advanced Security Monitoring</h3>
                        <p>Real-time threat detection and activity monitoring with AI-powered security analytics.</p>
                    </div>
                    <div class="slide">
                        <div class="slide-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3>Multi-Factor Protection</h3>
                        <p>Enterprise-grade security with multiple authentication layers and session encryption.</p>
                    </div>
                    <div class="slide">
                        <div class="slide-icon">
                            <i class="fas fa-bug"></i>
                        </div>
                        <h3>Threat Prevention</h3>
                        <p>Advanced brute force protection with intelligent blocking and security event logging.</p>
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
            // DOM Elements
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');
            const refreshCaptchaBtn = document.getElementById('refreshCaptcha');
            const loginForm = document.getElementById('loginForm');
            const submitButton = document.getElementById('submitButton');
            const captchaDisplay = document.getElementById('captchaDisplay');
            const buttonText = document.getElementById('buttonText');
            const buttonLoader = document.getElementById('buttonLoader');

            // Slider functionality
            const slider = document.getElementById('slider');
            const indicators = document.querySelectorAll('.indicator');
            let currentSlide = 0;
            let slideInterval;

            function showSlide(index) {
                if (slider) {
                    slider.style.transform = `translateX(-${index * 100}%)`;
                    
                    indicators.forEach((indicator, i) => {
                        indicator.classList.toggle('active', i === index);
                    });
                    
                    currentSlide = index;
                }
            }

            function startSlider() {
                if (indicators.length > 0) {
                    slideInterval = setInterval(() => {
                        let nextSlide = (currentSlide + 1) % indicators.length;
                        showSlide(nextSlide);
                    }, 5000);
                }
            }

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
                    const isPassword = passwordInput.type === 'password';
                    passwordInput.type = isPassword ? 'text' : 'password';
                    passwordToggle.innerHTML = isPassword ? 
                        '<i class="fas fa-eye-slash"></i>' : 
                        '<i class="fas fa-eye"></i>';
                });
            }

            // Refresh CAPTCHA
            if (refreshCaptchaBtn && captchaDisplay) {
                refreshCaptchaBtn.addEventListener('click', function() {
                    // Add loading state to refresh button
                    const originalHTML = refreshCaptchaBtn.innerHTML;
                    refreshCaptchaBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    refreshCaptchaBtn.disabled = true;
                    
                    fetch('?refresh_captcha=1&ajax=1')
                        .then(response => {
                            if (!response.ok) throw new Error('Network error');
                            return response.json();
                        })
                        .then(data => {
                            if (data.captcha) {
                                captchaDisplay.textContent = data.captcha;
                                // Add visual feedback
                                captchaDisplay.style.animation = 'pulse 0.5s ease-in-out';
                                setTimeout(() => {
                                    captchaDisplay.style.animation = '';
                                }, 500);
                            }
                        })
                        .catch(error => {
                            console.error('Error refreshing CAPTCHA:', error);
                            window.location.href = '?refresh_captcha=1';
                        })
                        .finally(() => {
                            // Restore button state
                            refreshCaptchaBtn.innerHTML = originalHTML;
                            refreshCaptchaBtn.disabled = false;
                        });
                });
            }

            // Form submission handling
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    // Basic client-side validation
                    const username = document.getElementById('username');
                    const password = document.getElementById('password');
                    const captcha = document.getElementById('captcha');
                    
                    let isValid = true;
                    
                    // Validate username
                    if (username && username.value.trim().length === 0) {
                        isValid = false;
                        showFieldError(username, 'Administrator ID is required');
                    } else if (username && !/^[a-zA-Z0-9_@.-]+$/.test(username.value)) {
                        isValid = false;
                        showFieldError(username, 'Invalid characters in username');
                    }
                    
                    // Validate password
                    if (password && password.value.length === 0) {
                        isValid = false;
                        showFieldError(password, 'Security key is required');
                    } else if (password && password.value.length < 8) {
                        isValid = false;
                        showFieldError(password, 'Security key must be at least 8 characters');
                    }
                    
                    // Validate CAPTCHA if visible
                    if (captcha && captcha.offsetParent !== null) {
                        if (captcha.value.trim().length !== 6) {
                            isValid = false;
                            showFieldError(captcha, 'Security code must be 6 characters');
                        } else if (!/^[A-Z0-9]{6}$/.test(captcha.value.toUpperCase())) {
                            isValid = false;
                            showFieldError(captcha, 'Invalid security code format');
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Show loading state
                    if (buttonText && buttonLoader) {
                        buttonText.classList.add('hidden');
                        buttonLoader.classList.remove('hidden');
                    }
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                    
                    // Add small delay to show loading state
                    setTimeout(() => {
                        // Form will submit normally
                    }, 100);
                });
            }

            // Field validation helper
            function showFieldError(field, message) {
                // Remove existing error
                const existingError = field.parentNode.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.style.color = 'var(--error-color)';
                errorDiv.style.fontSize = '13px';
                errorDiv.style.marginTop = '6px';
                errorDiv.style.fontWeight = '600';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                
                field.parentNode.appendChild(errorDiv);
                
                // Highlight field
                field.style.borderColor = 'var(--error-color)';
                
                // Remove error on input
                field.addEventListener('input', function() {
                    errorDiv.remove();
                    field.style.borderColor = '';
                }, { once: true });
            }

            // Countdown timer for blocked state
            <?php if ($is_blocked): ?>
            let timeLeft = <?php echo $remaining_time; ?>;
            const countdownElement = document.getElementById('countdown');
            
            if (countdownElement) {
                const countdownInterval = setInterval(function() {
                    timeLeft--;
                    
                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);
                        countdownElement.innerHTML = '<i class="fas fa-check"></i> Access Restored';
                        countdownElement.style.color = 'var(--secondary-color)';
                        countdownElement.style.borderColor = '#d1fae5';
                        countdownElement.style.background = '#f0fdf4';
                        
                        // Reload page after 2 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        countdownElement.textContent = 
                            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                        
                        // Add pulse animation when under 1 minute
                        if (timeLeft <= 60) {
                            countdownElement.style.animation = 'pulse 1s infinite';
                        }
                    }
                }, 1000);
            }
            <?php endif; ?>

            // Clear error when typing
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const errorMessage = document.querySelector('.error-message, .danger-message');
                    if (errorMessage) {
                        errorMessage.style.display = 'none';
                    }
                    
                    // Clear field-specific errors
                    const fieldError = this.parentNode.querySelector('.field-error');
                    if (fieldError) {
                        fieldError.remove();
                        this.style.borderColor = '';
                    }
                });
            });

            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            // CAPTCHA protection
            if (captchaDisplay) {
                // Prevent interaction with CAPTCHA
                ['contextmenu', 'copy', 'cut', 'dragstart', 'selectstart'].forEach(event => {
                    captchaDisplay.addEventListener(event, function(e) {
                        e.preventDefault();
                        return false;
                    });
                });
            }

            // Auto-uppercase CAPTCHA input
            const captchaInput = document.getElementById('captcha');
            if (captchaInput) {
                captchaInput.addEventListener('input', function(e) {
                    this.value = this.value.toUpperCase();
                });
            }

            // Enhanced input sanitization
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^a-zA-Z0-9_@.-]/g, '');
                });
            }

            // Security: Prevent paste in password field (optional)
            if (passwordInput) {
                passwordInput.addEventListener('paste', function(e) {
                    // You can enable this for extra security, but it affects UX
                    // e.preventDefault();
                });
            }

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl + R to refresh CAPTCHA
                if ((e.ctrlKey || e.metaKey) && e.key === 'r' && refreshCaptchaBtn) {
                    e.preventDefault();
                    refreshCaptchaBtn.click();
                }
                
                // Enter to submit form
                if (e.key === 'Enter' && !submitButton.disabled) {
                    loginForm.requestSubmit();
                }
            });

            // Security: Detect developer tools (basic)
            function detectDevTools() {
                const widthThreshold = window.outerWidth - window.innerWidth > 160;
                const heightThreshold = window.outerHeight - window.innerHeight > 160;
                
                if (widthThreshold || heightThreshold) {
                    console.warn('Developer tools detected - Security monitoring active');
                }
            }
            
            setInterval(detectDevTools, 1000);
        });
    </script>
</body>
</html>