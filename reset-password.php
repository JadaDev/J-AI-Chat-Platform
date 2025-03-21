<?php
require_once 'config.php';
require_once 'auth.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Check if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check if token exists and is not expired
if (empty($token)) {
    header('Location: login.php?tab=reset');
    exit;
}

$conn = connectDB();
$stmt = $conn->prepare("SELECT user_id FROM users WHERE token_reset = ? AND token_expiry > CURRENT_TIMESTAMP");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

$isValidToken = $result->num_rows > 0;
$stmt->close();
$conn->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $result = resetPassword($token, $newPassword);
        
        if ($result['success']) {
            $success = 'Your password has been reset successfully. You can now login with your new password.';
            $isValidToken = false; // Hide the form after successful reset
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
    <title>Reset Password - <?php echo htmlspecialchars($GLOBALS['site_name']); ?></title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body data-theme="dark">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <img src="assets/img/logo.svg" alt="<?php echo htmlspecialchars($GLOBALS['site_name']); ?>">
                    <h1><?php echo htmlspecialchars($GLOBALS['site_name']); ?></h1>
                </div>
                <p class="subtitle">Reset your password</p>
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
                <div class="auth-redirect">
                    <p>Redirecting to login page in <span id="countdown">5</span> seconds...</p>
                    <a href="login.php" class="btn-link">Login now</a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($isValidToken): ?>
            <form method="post" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" class="auth-form">
                <input type="hidden" name="action" value="reset_password">
                
                <div class="form-group">
                    <label for="new-password">New Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="new-password" name="new_password" placeholder="Enter new password" required>
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
                        <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </div>
            </form>
            <?php elseif (empty($success)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                Invalid or expired token. Please request a new password reset link.
            </div>
            <div class="form-group text-center">
                <a href="login.php?tab=reset" class="btn-link">Request New Reset Link</a>
            </div>
            <?php endif; ?>
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
            const passwordInput = document.getElementById('new-password');
            if (passwordInput) {
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
            }
            
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
            
            // Countdown redirect
            const countdown = document.getElementById('countdown');
            if (countdown) {
                let seconds = parseInt(countdown.textContent);
                const interval = setInterval(() => {
                    seconds--;
                    countdown.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = 'login.php';
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>