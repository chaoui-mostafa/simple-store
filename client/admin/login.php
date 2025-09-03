<?php
session_start();
require_once '../../config/db.php';
require_once '../../controllers/AdminController.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminController = new AdminController();
    
    if ($adminController->login($_POST['username'], $_POST['password'])) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - StyleShop</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .form-input {
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
        }
        
        .form-input:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }
        
        .error-message {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .floating-label {
            position: relative;
            margin-bottom: 20px;
        }
        
        .floating-input {
            padding-top: 1.5rem;
        }
        
        .floating-label-text {
            position: absolute;
            top: 0.75rem;
            left: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
            transition: all 0.2s ease;
            pointer-events: none;
        }
        
        .floating-input:focus + .floating-label-text,
        .floating-input:not(:placeholder-shown) + .floating-label-text {
            top: 0.25rem;
            font-size: 0.75rem;
            color: #3B82F6;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
        }
        
        .decoration-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            overflow: hidden;
            line-height: 0;
        }
        
        .decoration-wave svg {
            position: relative;
            display: block;
            width: calc(100% + 1.3px);
            height: 89px;
        }
        
        .decoration-wave .shape-fill {
            fill: #FFFFFF;
        }
        
        /* Loading animation */
        .loader {
            border-top-color: #3B82F6;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="absolute top-6 left-6">
        <a href="../index.php" class="text-white text-lg font-semibold flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Store
        </a>
    </div>
    
    <div class="w-full max-w-md">
        <div class="login-card p-8 md:p-10">
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock text-white text-3xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800">Admin Login</h2>
                <p class="text-gray-600 mt-2">Enter your credentials to access the dashboard</p>
            </div>
            
            <!-- PHP Error Message -->
            <?php if ($error): ?>
                <div class="error-message bg-red-50 text-red-700 p-3 rounded-lg mb-6 flex items-start">
                    <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div class="floating-label">
                    <input type="text" id="username" name="username" placeholder=" " required 
                           class="floating-input form-input w-full px-4 py-3 rounded-lg focus:outline-none"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <label for="username" class="floating-label-text">Username</label>
                    <div class="absolute left-4 bottom-3 text-gray-400">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                
                <div class="floating-label relative">
                    <input type="password" id="password" name="password" placeholder=" " required 
                           class="floating-input form-input w-full px-4 py-3 rounded-lg focus:outline-none pr-12">
                    <label for="password" class="floating-label-text">Password</label>
                    <div class="absolute left-4 bottom-3 text-gray-400">
                        <i class="fas fa-lock"></i>
                    </div>
                    <span class="password-toggle" id="passwordToggle">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                
                <div class="flex items-center justify-between mt-2">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>
                    
                    <!-- <div class="text-sm">
                        <a href="#" class="font-medium text-blue-600 hover:text-blue-500">Forgot password?</a>
                    </div> -->
                </div>
                
                <button type="submit" class="btn-login w-full py-3 px-4 rounded-lg text-white font-semibold">
                    Sign in
                </button>
            </form>
            
          
        </div>
        
        <p class="mt-8 text-center text-white">
            &copy; 2023 StyleShop. All rights reserved.
        </p>
    </div>
    
    <div class="decoration-wave">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" class="shape-fill"></path>
        </svg>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');
            
            // Toggle password visibility
            passwordToggle.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordToggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    passwordInput.type = 'password';
                    passwordToggle.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
            
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
        });
    </script>
</body>
</html>