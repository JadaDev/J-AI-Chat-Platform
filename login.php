<?php
require_once 'config.php';
require_once 'auth.php';

$error = '';
$success = '';

// Check if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = loginUser($username, $password);
    
    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $result['message'];
    }
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        $result = registerUser($username, $email, $password);
        
        if ($result['success']) {
            $success = 'Registration successful! You can now login.';
        } else {
            $error = $result['message'];
        }
    }
}

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email address required';
    } else {
        $result = requestPasswordReset($email);
        
        if ($result['success']) {
            $success = 'If your email is registered, you will receive reset instructions.';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($GLOBALS['site_name']); ?></title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css?v=1.0">
</head>
<body data-theme="dark">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <img src="assets/img/logo.svg" alt="<?php echo htmlspecialchars($GLOBALS['site_name']); ?>">
                    <h1><?php echo htmlspecialchars($GLOBALS['site_name']); ?></h1>
                </div>
                <p class="subtitle">Your personal AI assistant</p>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <div class="auth-tabs">
                <button class="tab-btn active" data-tab="login">Login</button>
                <button class="tab-btn" data-tab="register">Register</button>
                <button class="tab-btn" data-tab="reset">Reset Password</button>
                <div class="tab-slider"></div>
            </div>
            
            <div class="tab-content">
                <!-- Login Form -->
                <div class="tab-pane active" id="login-tab">
                    <form method="post" action="login.php" class="auth-form">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="form-group">
                            <label for="username">Username or Email</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="username" name="username" placeholder="Enter your username or email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="toggle-password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Registration Form -->
                <div class="tab-pane" id="register-tab">
                    <form method="post" action="login.php" class="auth-form">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="form-group">
                            <label for="reg-username">Username</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="reg-username" name="username" placeholder="Choose a username" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg-email">Email</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="reg-email" name="email" placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg-password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="reg-password" name="password" placeholder="Create a password" required>
                                <button type="button" class="toggle-password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter">
                                    <div class="meter-fill"></div>
                                </div>
                                <span class="strength-text">Password strength</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm-password">Confirm Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm your password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-user-plus"></i> Register
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Password Reset Form -->
                <div class="tab-pane" id="reset-tab">
                    <form method="post" action="login.php" class="auth-form">
                        <input type="hidden" name="action" value="reset">
                        
                        <div class="form-group">
                            <p class="form-info">Enter your email address and we'll send you instructions to reset your password.</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="reset-email">Email</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="reset-email" name="email" placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Reset Link
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="theme-toggle">
            <input type="checkbox" id="theme-toggle" class="theme-input">
            <label for="theme-toggle" class="theme-label">
                <i class="fas fa-sun"></i>
                <i class="fas fa-moon"></i>
                <span class="toggle-ball"></span>
            </label>
        </div>
    </div>
    
    <script>
        // Tab switching
        document.addEventListener('DOMContentLoaded', () => {
            // Theme toggling
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;
            
            // Initialize theme
            const storedTheme = localStorage.getItem('theme') || 'dark';
            body.setAttribute('data-theme', storedTheme);
            themeToggle.checked = storedTheme === 'light';
            
            themeToggle.addEventListener('change', () => {
                const currentTheme = body.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                body.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
            
            // Tab functionality
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabPanes = document.querySelectorAll('.tab-pane');
            const tabSlider = document.querySelector('.tab-slider');
            
            function setActiveTab(tabId) {
                // Update buttons
                tabBtns.forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.tab === tabId) {
                        btn.classList.add('active');
                    }
                });
                
                // Update tab content
                tabPanes.forEach(pane => {
                    pane.classList.remove('active');
                    if (pane.id === tabId + '-tab') {
                        pane.classList.add('active');
                    }
                });
                
                // Move slider
                const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
                const activeIndex = Array.from(tabBtns).indexOf(activeBtn);
                tabSlider.style.left = `calc(${(100 / tabBtns.length) * activeIndex}%)`;
                tabSlider.style.width = `calc(100% / ${tabBtns.length})`;
            }
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    setActiveTab(btn.dataset.tab);
                });
            });
            
            // Password toggle visibility
            const togglePasswordBtns = document.querySelectorAll('.toggle-password');
            togglePasswordBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const input = btn.parentElement.querySelector('input');
                    const icon = btn.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // Password strength meter
            const passwordInput = document.getElementById('reg-password');
            const meterFill = document.querySelector('.meter-fill');
            const strengthText = document.querySelector('.strength-text');
            
            passwordInput.addEventListener('input', () => {
                const password = passwordInput.value;
                const strength = calculatePasswordStrength(password);
                
                // Update meter fill
                meterFill.style.width = `${strength}%`;
                
                // Update color and text
                if (strength < 25) {
                    meterFill.style.backgroundColor = '#ff4d4d';
                    strengthText.textContent = 'Very Weak';
                } else if (strength < 50) {
                    meterFill.style.backgroundColor = '#ffaa00';
                    strengthText.textContent = 'Weak';
                } else if (strength < 75) {
                    meterFill.style.backgroundColor = '#ffdd00';
                    strengthText.textContent = 'Moderate';
                } else if (strength < 100) {
                    meterFill.style.backgroundColor = '#00cc44';
                    strengthText.textContent = 'Strong';
                } else {
                    meterFill.style.backgroundColor = '#00cc44';
                    strengthText.textContent = 'Very Strong';
                }
            });
            
            // Calculate password strength score
            function calculatePasswordStrength(password) {
                let score = 0;
                
                // No password
                if (!password) return 0;
                
                // Length contribution (up to 40 points)
                score += Math.min(password.length * 4, 40);
                
                // Complexity contributions
                if (/[A-Z]/.test(password)) score += 15; // Uppercase letters
                if (/[a-z]/.test(password)) score += 10; // Lowercase letters
                if (/[0-9]/.test(password)) score += 15; // Numbers
                if (/[^A-Za-z0-9]/.test(password)) score += 20; // Special characters
                
                // Variety bonus
                const uniqueChars = new Set(password).size;
                score += Math.min(uniqueChars * 2, 15);
                
                return Math.min(score, 100);
            }
            
            // Initialize active tab based on the query string or error state
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam && ['login', 'register', 'reset'].includes(tabParam)) {
                setActiveTab(tabParam);
            }
        });
    </script>
</body>
</html>