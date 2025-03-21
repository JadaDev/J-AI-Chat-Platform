<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../admin.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get system settings
$conn = connectDB();
$stmt = $conn->prepare("SELECT setting_name, setting_value, updated_at, updated_by FROM system_settings ORDER BY setting_name");
$stmt->execute();
$result = $stmt->get_result();

$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_name']] = [
        'value' => $row['setting_value'],
        'updated_at' => $row['updated_at'],
        'updated_by' => $row['updated_by']
    ];
}
$stmt->close();
$conn->close();

// Process form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = [
        'site_name' => $_POST['site_name'] ?? '',
        'default_model' => $_POST['default_model'] ?? '',
        'max_tokens' => $_POST['max_tokens'] ?? '',
        'allow_registration' => isset($_POST['allow_registration']) ? 'true' : 'false',
        'welcome_message' => $_POST['welcome_message'] ?? ''
    ];
    
    $result = updateSystemSettings($newSettings);
    
    if ($result['success']) {
        $success = $result['message'];
        // Update global variables
        $GLOBALS['site_name'] = $newSettings['site_name'];
        $GLOBALS['default_model'] = $newSettings['default_model'];
        $GLOBALS['max_tokens'] = (int)$newSettings['max_tokens'];
        $GLOBALS['allow_registration'] = $newSettings['allow_registration'] === 'true';
        $GLOBALS['welcome_message'] = $newSettings['welcome_message'];
        
        // Update settings for display
        foreach ($newSettings as $key => $value) {
            $settings[$key]['value'] = $value;
            $settings[$key]['updated_at'] = date('Y-m-d H:i:s');
            $settings[$key]['updated_by'] = $userId;
        }
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo htmlspecialchars($GLOBALS['site_name']); ?> Admin</title>
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body data-theme="dark">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="../assets/img/logo.svg" alt="<?php echo htmlspecialchars($GLOBALS['site_name']); ?>">
                <span>Admin Panel</span>
            </div>
            <button class="close-sidebar-btn" id="close-sidebar"><i class="fas fa-times"></i></button>
        </div>
        
        <nav class="admin-nav">
            <ul>
                <li>
                    <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
                    <a href="users.php"><i class="fas fa-users"></i> User Management</a>
                </li>
                <li>
                    <a href="api-keys.php"><i class="fas fa-key"></i> API Keys</a>
                </li>
                <li class="active">
                    <a href="settings.php"><i class="fas fa-cog"></i> System Settings</a>
                </li>
                <li>
                    <a href="../index.php"><i class="fas fa-comment-dots"></i> Chat App</a>
                </li>
                <li>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="admin-main">
        <header class="admin-header">
            <button id="open-sidebar" class="open-sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <h1>System Settings</h1>
            <div class="admin-actions">
                <div class="theme-toggle">
                    <input type="checkbox" id="theme-toggle" class="theme-input">
                    <label for="theme-toggle" class="theme-label">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                        <span class="toggle-ball"></span>
                    </label>
                </div>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </header>
        
        <div class="admin-content">
            <?php if ($success): ?>
            <div class="alert success" style="margin-bottom: 20px; padding: 15px; border-radius: 8px; background-color: rgba(34, 197, 94, 0.15); color: var(--success-color); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert error" style="margin-bottom: 20px; padding: 15px; border-radius: 8px; background-color: rgba(239, 68, 68, 0.15); color: var(--error-color); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="settings.php" class="admin-form">
                <div class="form-section">
                    <h3>General Settings</h3>
                    
                    <div class="form-group">
                        <label for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" class="form-input" value="<?php echo htmlspecialchars($settings['site_name']['value'] ?? $GLOBALS['site_name']); ?>" required>
                        <small class="form-text">The name of your site, displayed in titles and headers.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>User Registration</label>
                        <div>
                            <label class="form-switch">
                                <input type="checkbox" id="allow_registration" name="allow_registration" <?php echo ($settings['allow_registration']['value'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                <span class="switch-slider"></span>
                            </label>
                            <span class="form-text" style="margin-left: 10px;">Allow new user registrations</span>
                        </div>
                        <small class="form-text">When disabled, only existing users can log in.</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>AI Configuration</h3>
                    
                    <div class="form-group">
                        <label for="default_model">Default AI Model</label>
                        <select id="default_model" name="default_model" class="form-select" required>
                            <option value="gemini-1.5-pro" <?php echo ($settings['default_model']['value'] ?? '') === 'gemini-1.5-pro' ? 'selected' : ''; ?>>Gemini 1.5 Pro</option>
                            <option value="gemini-1.5-flash" <?php echo ($settings['default_model']['value'] ?? '') === 'gemini-1.5-flash' ? 'selected' : ''; ?>>Gemini 1.5 Flash</option>
                            <option value="gemini-2.0-flash" <?php echo ($settings['default_model']['value'] ?? '') === 'gemini-2.0-flash' ? 'selected' : ''; ?>>Gemini 2.0 Flash</option>
                        </select>
                        <small class="form-text">The default AI model to use for new conversations.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_tokens">Maximum Tokens</label>
                        <input type="number" id="max_tokens" name="max_tokens" class="form-input" value="<?php echo htmlspecialchars($settings['max_tokens']['value'] ?? '20480'); ?>" min="256" max="128000" required>
                        <small class="form-text">Maximum number of tokens per response. Higher values allow longer responses but consume more resources.</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Chat Settings</h3>
                    
                    <div class="form-group">
                        <label for="welcome_message">Welcome Message</label>
                        <textarea id="welcome_message" name="welcome_message" class="form-textarea" rows="4" required><?php echo htmlspecialchars($settings['welcome_message']['value'] ?? "Hello! I'm your AI Chat Assistant. How can I help you today?"); ?></textarea>
                        <small class="form-text">The message shown to users when starting a new conversation.</small>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
            
            <div class="admin-form" style="margin-top: 30px;">
                <div class="form-section">
                    <h3>Settings History</h3>
                    <p>History of setting changes made by administrators.</p>
                    
                    <div style="margin-top: 15px; overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Setting</th>
                                    <th>Value</th>
                                    <th>Last Updated</th>
                                    <th>Updated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($settings as $name => $setting): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($name); ?></td>
                                    <td>
                                        <?php
                                        // Format display based on setting type
                                        $value = $setting['value'];
                                        if ($name === 'allow_registration') {
                                            echo $value === 'true' ? '<span class="status-badge active">Enabled</span>' : '<span class="status-badge inactive">Disabled</span>';
                                        } elseif ($name === 'welcome_message') {
                                            echo strlen($value) > 50 ? htmlspecialchars(substr($value, 0, 50)) . '...' : htmlspecialchars($value);
                                        } else {
                                            echo htmlspecialchars($value);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($setting['updated_at'])); ?></td>
                                    <td>
                                        <?php
                                        if ($setting['updated_by']) {
                                            $conn = connectDB();
                                            $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
                                            $stmt->bind_param("i", $setting['updated_by']);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result->num_rows > 0) {
                                                $user = $result->fetch_assoc();
                                                echo htmlspecialchars($user['username']);
                                            } else {
                                                echo 'Unknown';
                                            }
                                            $stmt->close();
                                            $conn->close();
                                        } else {
                                            echo 'System';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // DOM Elements
        const sidebar = document.getElementById('admin-sidebar');
        const openSidebarBtn = document.getElementById('open-sidebar');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const themeToggle = document.getElementById('theme-toggle');
        
        // Theme handling
        function initTheme() {
            const storedTheme = localStorage.getItem('theme') || 'dark';
            document.body.setAttribute('data-theme', storedTheme);
            themeToggle.checked = storedTheme === 'light';
        }
        
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }
        
        // Sidebar toggle
        function toggleSidebar() {
            sidebar.classList.toggle('active');
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            
            // Sidebar toggle
            openSidebarBtn.addEventListener('click', toggleSidebar);
            closeSidebarBtn.addEventListener('click', toggleSidebar);
            
            // Theme toggle
            themeToggle.addEventListener('change', toggleTheme);
            
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
            
            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const maxTokens = document.getElementById('max_tokens').value;
                if (maxTokens < 256 || maxTokens > 32000) {
                    e.preventDefault();
                    alert('Maximum tokens must be between 256 and 32000');
                    return false;
                }
            });
        });
    </script>
</body>
</html>