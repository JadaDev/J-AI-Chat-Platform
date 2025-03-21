<?php
require_once 'config.php';
require_once 'auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];
$preferredModel = $_SESSION['preferred_model'];

$error = '';
$success = '';

// Connect to database
$conn = connectDB();

// Get user details
$stmt = $conn->prepare("SELECT first_name, last_name, profile_picture, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$firstName = $user['first_name'] ?? '';
$lastName = $user['last_name'] ?? '';
$profileImage = !empty($user['profile_picture']) ? 'uploads/avatars/' . $user['profile_picture'] : 'assets/img/default-avatar.png';
$createdAt = $user['created_at'] ?? '';

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [];
    
    // Update basic profile information
    if (isset($_POST['update_profile'])) {
        $updateData['first_name'] = $_POST['first_name'] ?? '';
        $updateData['last_name'] = $_POST['last_name'] ?? '';
        $updateData['email'] = $_POST['email'] ?? '';
        $updateData['preferred_model'] = $_POST['preferred_model'] ?? '';
        
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
            $updateData['profile_picture'] = $_FILES['profile_picture'];
        }
        
        $result = updateProfile($userId, $updateData);
        
        if ($result['success']) {
            $success = $result['message'];
            // Update local variables to reflect changes
            $email = $_POST['email'];
            $firstName = $_POST['first_name'];
            $lastName = $_POST['last_name'];
            $preferredModel = $_POST['preferred_model'];
            
            // Refresh the page if profile image was updated (to show new image)
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                header("Location: profile.php?updated=1");
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
    
    // Update password
    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters long';
        } else {
            $updateData = [
                'current_password' => $currentPassword,
                'new_password' => $newPassword
            ];
            
            $result = updateProfile($userId, $updateData);
            
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get usage statistics
$statsStmt = $conn->prepare("
    SELECT 
        SUM(tokens_used) AS total_tokens,
        SUM(request_count) AS total_requests,
        MAX(date) AS last_activity_date
    FROM usage_statistics 
    WHERE user_id = ?
");
$statsStmt->bind_param("i", $userId);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();
$statsStmt->close();

// Get conversation stats
$convStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_conversations,
        SUM(CASE WHEN is_archived = TRUE THEN 1 ELSE 0 END) AS archived_conversations
    FROM conversations 
    WHERE user_id = ?
");
$convStmt->bind_param("i", $userId);
$convStmt->execute();
$convResult = $convStmt->get_result();
$convStats = $convResult->fetch_assoc();
$convStmt->close();

// Close connection
$conn->close();

// Check if redirected from successful update
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $success = 'Profile updated successfully';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($GLOBALS['site_name']); ?></title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/profile.css">
</head>
<body data-theme="dark">
    <div class="profile-container">
        <header class="profile-header">
            <div class="profile-header-content">
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Chat
                </a>
                <h1>My Profile</h1>
                <div class="theme-toggle">
                    <input type="checkbox" id="theme-toggle" class="theme-input">
                    <label for="theme-toggle" class="theme-label">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                        <span class="toggle-ball"></span>
                    </label>
                </div>
            </div>
        </header>
        
        <main class="profile-main">
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
            
            <div class="profile-grid">
                <!-- Profile Overview -->
                <div class="profile-card">
                    <div class="card-header">
                        <h2>Profile Overview</h2>
                    </div>
                    <div class="card-body">
                        <div class="profile-overview">
                            <div class="profile-avatar-container">
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="<?php echo htmlspecialchars($username); ?>" class="profile-avatar">
                                <button class="change-avatar-btn" id="trigger-avatar-upload">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <div class="profile-info">
                                <h3><?php echo htmlspecialchars($username); ?></h3>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email); ?></p>
                                <p><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></p>
                                <p><i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($createdAt)); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Usage Statistics -->
                <div class="profile-card">
                    <div class="card-header">
                        <h2>Usage Statistics</h2>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="stat-details">
                                    <span class="stat-value"><?php echo number_format($convStats['total_conversations'] ?? 0); ?></span>
                                    <span class="stat-label">Conversations</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-archive"></i>
                                </div>
                                <div class="stat-details">
                                    <span class="stat-value"><?php echo number_format($convStats['archived_conversations'] ?? 0); ?></span>
                                    <span class="stat-label">Archived</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-microchip"></i>
                                </div>
                                <div class="stat-details">
                                    <span class="stat-value"><?php echo number_format($stats['total_tokens'] ?? 0); ?></span>
                                    <span class="stat-label">Tokens Used</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="stat-details">
                                    <span class="stat-value"><?php echo number_format($stats['total_requests'] ?? 0); ?></span>
                                    <span class="stat-label">Requests</span>
                                </div>
                            </div>
                        </div>
                        <div class="last-activity">
                            <p><i class="fas fa-clock"></i> Last activity: 
                                <?php 
                                echo $stats['last_activity_date'] ? 
                                    date('F j, Y', strtotime($stats['last_activity_date'])) : 
                                    'No activity yet'; 
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Profile Form -->
                <div class="profile-card">
                    <div class="card-header">
                        <h2>Edit Profile</h2>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="form-group">
                                <label for="profile_picture">Profile Picture</label>
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="hidden-file-input">
                                <div class="file-input-container">
                                    <span class="file-name">No file selected</span>
                                    <button type="button" class="browse-btn" id="browse-btn">Browse</button>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="preferred_model">Default AI Model</label>
                                <select id="preferred_model" name="preferred_model">
                                    <option value="gemini-1.5-pro" <?php echo $preferredModel === 'gemini-1.5-pro' ? 'selected' : ''; ?>>Gemini 1.5 Pro</option>
                                    <option value="gemini-1.5-flash" <?php echo $preferredModel === 'gemini-1.5-flash' ? 'selected' : ''; ?>>Gemini 1.5 Flash</option>
                                    <option value="gemini-2.0-flash" <?php echo $preferredModel === 'gemini-2.0-flash' ? 'selected' : ''; ?>>Gemini 2.0 Flash</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Form -->
                <div class="profile-card">
                    <div class="card-header">
                        <h2>Change Password</h2>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="post">
                            <input type="hidden" name="update_password" value="1">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div class="password-input-container">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="toggle-password-btn">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="password-input-container">
                                    <input type="password" id="new_password" name="new_password" required>
                                    <button type="button" class="toggle-password-btn">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength">
                                    <div class="strength-bar">
                                        <div class="strength-fill"></div>
                                    </div>
                                    <span class="strength-text">Password strength</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="password-input-container">
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="toggle-password-btn">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-lock"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle functionality
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
            
            // File upload functionality
            const fileInput = document.getElementById('profile_picture');
            const fileNameDisplay = document.querySelector('.file-name');
            const browseBtn = document.getElementById('browse-btn');
            const avatarTrigger = document.getElementById('trigger-avatar-upload');
            
            browseBtn.addEventListener('click', () => {
                fileInput.click();
            });
            
            avatarTrigger.addEventListener('click', () => {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    fileNameDisplay.textContent = fileInput.files[0].name;
                } else {
                    fileNameDisplay.textContent = 'No file selected';
                }
            });
            
            // Password visibility toggle
            const togglePasswordBtns = document.querySelectorAll('.toggle-password-btn');
            togglePasswordBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    const icon = this.querySelector('i');
                    
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
            const newPasswordInput = document.getElementById('new_password');
            const strengthFill = document.querySelector('.strength-fill');
            const strengthText = document.querySelector('.strength-text');
            
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                // Update strength bar
                strengthFill.style.width = `${strength}%`;
                
                // Update color and text
                if (strength < 25) {
                    strengthFill.style.backgroundColor = '#ff4d4d';
                    strengthText.textContent = 'Very Weak';
                } else if (strength < 50) {
                    strengthFill.style.backgroundColor = '#ffaa00';
                    strengthText.textContent = 'Weak';
                } else if (strength < 75) {
                    strengthFill.style.backgroundColor = '#ffdd00';
                    strengthText.textContent = 'Moderate';
                } else if (strength < 100) {
                    strengthFill.style.backgroundColor = '#00cc44';
                    strengthText.textContent = 'Strong';
                } else {
                    strengthFill.style.backgroundColor = '#00cc44';
                    strengthText.textContent = 'Very Strong';
                }
            });
            
            // Calculate password strength
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
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>