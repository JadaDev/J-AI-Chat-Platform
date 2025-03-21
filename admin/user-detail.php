<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../admin.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get user ID from URL
$viewUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($viewUserId <= 0) {
    header('Location: users.php');
    exit;
}

// Get current admin's data
$adminId = $_SESSION['user_id'];
$adminUsername = $_SESSION['username'];

// Fetch user data
$conn = connectDB();
$stmt = $conn->prepare("
    SELECT 
        u.user_id, 
        u.username, 
        u.email, 
        u.first_name, 
        u.last_name, 
        u.profile_picture, 
        u.user_role, 
        u.preferred_model, 
        u.created_at, 
        u.last_login, 
        u.is_active,
        u.usage_count
    FROM users u
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $viewUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: users.php');
    exit;
}

$user = $result->fetch_assoc();
$profileImage = !empty($user['profile_picture']) && $user['profile_picture'] !== 'default-avatar.png' ? '../uploads/avatars/' . $user['profile_picture'] : '../assets/img/default-avatar.png';
$stmt->close();

// Get user statistics
$statsStmt = $conn->prepare("
    SELECT 
        SUM(tokens_used) as total_tokens,
        SUM(request_count) as total_requests
    FROM usage_statistics 
    WHERE user_id = ?
");
$statsStmt->bind_param("i", $viewUserId);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();
$statsStmt->close();

// Get conversation stats
$convStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_conversations,
        SUM(CASE WHEN is_archived = TRUE THEN 1 ELSE 0 END) as archived_conversations
    FROM conversations 
    WHERE user_id = ?
");
$convStmt->bind_param("i", $viewUserId);
$convStmt->execute();
$convResult = $convStmt->get_result();
$convStats = $convResult->fetch_assoc();
$convStmt->close();

// Get recent user conversations
$conversationsStmt = $conn->prepare("
    SELECT 
        conversation_id, 
        title, 
        model, 
        created_at, 
        updated_at, 
        is_archived,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = conversations.conversation_id) as message_count
    FROM conversations 
    WHERE user_id = ? 
    ORDER BY updated_at DESC 
    LIMIT 5
");
$conversationsStmt->bind_param("i", $viewUserId);
$conversationsStmt->execute();
$conversationsResult = $conversationsStmt->get_result();
$conversations = [];
while ($row = $conversationsResult->fetch_assoc()) {
    $conversations[] = $row;
}
$conversationsStmt->close();

// Get usage history (last 30 days)
$usageStmt = $conn->prepare("
    SELECT 
        date,
        model_used,
        tokens_used,
        request_count
    FROM usage_statistics 
    WHERE user_id = ? 
    ORDER BY date DESC 
    LIMIT 30
");
$usageStmt->bind_param("i", $viewUserId);
$usageStmt->execute();
$usageResult = $usageStmt->get_result();
$usageHistory = [];
while ($row = $usageResult->fetch_assoc()) {
    $usageHistory[] = $row;
}
$usageStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li class="active">
                    <a href="users.php"><i class="fas fa-users"></i> User Management</a>
                </li>
                <li>
                    <a href="api-keys.php"><i class="fas fa-key"></i> API Keys</a>
                </li>
                <li>
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
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="users.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <?php if ($user['user_role'] === 'admin'): ?>
                <span class="badge admin-badge">Admin</span>
                <?php endif; ?>
            </div>
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
                    <span><?php echo htmlspecialchars($adminUsername); ?></span>
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </header>
        
        <div class="admin-content">
            <div class="profile-overview-card" style="margin-bottom: 30px; background-color: var(--bg-secondary); border-radius: 12px; box-shadow: var(--card-shadow);">
                <div style="display: flex; padding: 20px; gap: 30px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-color);">
                        <div>
                            <h2 style="margin-bottom: 10px;"><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <?php if (!empty($user['first_name']) || !empty($user['last_name'])): ?>
                            <p style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?>
                            </p>
                            <?php endif; ?>
                            <p style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                <i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                            </p>
                            <p style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-clock"></i> Last login: <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div style="flex-grow: 1;"></div>
                    
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <button id="edit-user-btn" class="btn btn-primary" data-id="<?php echo $user['user_id']; ?>">
                            <i class="fas fa-edit"></i> Edit User
                        </button>
                        
                        <?php if ($user['user_id'] !== $adminId): ?>
                        <button id="toggle-status-btn" class="btn <?php echo $user['is_active'] ? 'btn-danger' : 'btn-primary'; ?>" data-id="<?php echo $user['user_id']; ?>" data-action="<?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?>">
                            <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i> 
                            <?php echo $user['is_active'] ? 'Deactivate User' : 'Activate User'; ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon conversations">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Conversations</h3>
                        <div class="stat-value"><?php echo number_format($convStats['total_conversations'] ?? 0); ?></div>
                        <div class="stat-meta">
                            <?php echo number_format($convStats['archived_conversations'] ?? 0); ?> archived
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon messages">
                        <i class="fas fa-comment-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Requests</h3>
                        <div class="stat-value"><?php echo number_format($stats['total_requests'] ?? 0); ?></div>
                        <div class="stat-meta">
                            Interactions with AI
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon tokens">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Tokens Used</h3>
                        <div class="stat-value"><?php echo number_format($stats['total_tokens'] ?? 0); ?></div>
                        <div class="stat-meta">
                            Approx. cost: $<?php echo number_format(($stats['total_tokens'] ?? 0) * 0.00001, 2); ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Preferred Model</h3>
                        <div class="stat-value" style="font-size: 18px;"><?php echo htmlspecialchars($user['preferred_model']); ?></div>
                        <div class="stat-meta">
                            Status: <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="charts-container">
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Recent Usage History</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="usageChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Model Distribution</h3>
                    </div>
                    <div class="card-body chart-donut-container">
                        <canvas id="modelDistributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent Conversations -->
            <div class="table-card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3>Recent Conversations</h3>
                </div>
                <div class="card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Model</th>
                                <th>Messages</th>
                                <th>Created</th>
                                <th>Last Updated</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($conversations)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No conversations found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($conversations as $conversation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($conversation['title']); ?></td>
                                <td><?php echo htmlspecialchars($conversation['model']); ?></td>
                                <td><?php echo number_format($conversation['message_count']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($conversation['created_at'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($conversation['updated_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $conversation['is_archived'] ? 'inactive' : 'active'; ?>">
                                        <?php echo $conversation['is_archived'] ? 'Archived' : 'Active'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Edit User Modal -->
    <div id="edit-user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="user-form">
                    <input type="hidden" id="user-id" name="user_id" value="<?php echo $viewUserId; ?>">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input">
                        <small class="form-text">Leave blank to keep current password.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="user-role">Role</label>
                        <select id="user-role" name="role" class="form-select">
                            <option value="user" <?php echo $user['user_role'] === 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo $user['user_role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <div>
                            <label class="form-switch">
                                <input type="checkbox" id="is-active" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                <span class="switch-slider"></span>
                            </label>
                            <span class="form-text" style="margin-left: 10px;">Active</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancel-edit" class="btn btn-secondary">Cancel</button>
                <button id="save-user" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
    
    <script>
        // DOM Elements
        const sidebar = document.getElementById('admin-sidebar');
        const openSidebarBtn = document.getElementById('open-sidebar');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const themeToggle = document.getElementById('theme-toggle');
        const editUserBtn = document.getElementById('edit-user-btn');
        const editUserModal = document.getElementById('edit-user-modal');
        const toggleStatusBtn = document.getElementById('toggle-status-btn');
        const cancelEditBtn = document.getElementById('cancel-edit');
        const saveUserBtn = document.getElementById('save-user');
        const closeModalButtons = document.querySelectorAll('.close-modal');
        
        // Usage history data
        <?php
        // Prepare data for usage chart
        $dates = [];
        $tokens = [];
        $requests = [];
        
        // Sort the usage history by date (ascending)
        usort($usageHistory, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        foreach ($usageHistory as $usage) {
            $dates[] = date('M d', strtotime($usage['date']));
            $tokens[] = (int)$usage['tokens_used'];
            $requests[] = (int)$usage['request_count'];
        }
        
        // Prepare model distribution data
        $modelCounts = [];
        $modelColors = [
            'gemini-1.5-pro' => '#EA4335',
            'gemini-1.5-flash' => '#FBBC05',
            'gemini-2.0-flash' => '#7B1FA2'
        ];
        
        foreach ($usageHistory as $usage) {
            $model = $usage['model_used'];
            if (!isset($modelCounts[$model])) {
                $modelCounts[$model] = 0;
            }
            $modelCounts[$model] += (int)$usage['request_count'];
        }
        
        $modelLabels = array_keys($modelCounts);
        $modelData = array_values($modelCounts);
        $modelChartColors = [];
        
        foreach ($modelLabels as $model) {
            if (isset($modelColors[$model])) {
                $modelChartColors[] = $modelColors[$model];
            } else {
                $modelChartColors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            }
        }
        ?>
        
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
        
        // Modal handling
        function openModal(modal) {
            modal.classList.add('active');
        }
        
        function closeModal(modal) {
            modal.classList.remove('active');
        }
        
        // Toggle user status
        async function toggleUserStatus(userId, action) {
            if (!confirm(`Are you sure you want to ${action} this user?`)) {
                return;
            }
            
            try {
                const response = await fetch('../api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'updateUserStatus',
                        user_id: userId,
                        is_active: action === 'activate'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        }
        
        // Save user changes
        async function saveUser() {
            const form = document.getElementById('user-form');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const userId = document.getElementById('user-id').value;
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            const password = document.getElementById('password').value;
            const role = document.getElementById('user-role').value;
            const isActive = document.getElementById('is-active').checked;
            
            const formData = {
                user_id: userId,
                username,
                email,
                first_name: firstName,
                last_name: lastName,
                role,
                is_active: isActive
            };
            
            // Add password only if it's provided
            if (password) {
                formData.password = password;
            }
            
            try {
                const response = await fetch('../api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'updateUser',
                        ...formData
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal(editUserModal);
                    
                    // Reload page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        }
        
        // Initialize charts
        function initCharts() {
            // Usage history chart
            const usageCtx = document.getElementById('usageChart').getContext('2d');
            new Chart(usageCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [
                        {
                            label: 'Tokens Used',
                            data: <?php echo json_encode($tokens); ?>,
                            borderColor: '#4285F4',
                            backgroundColor: 'rgba(66, 133, 244, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Requests',
                            data: <?php echo json_encode($requests); ?>,
                            borderColor: '#34A853',
                            backgroundColor: 'rgba(52, 168, 83, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Tokens'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Requests'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
            
            // Model distribution chart
            const modelCtx = document.getElementById('modelDistributionChart').getContext('2d');
            new Chart(modelCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($modelLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($modelData); ?>,
                        backgroundColor: <?php echo json_encode($modelChartColors); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} requests (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            initCharts();
            
            // Sidebar toggle
            openSidebarBtn.addEventListener('click', toggleSidebar);
            closeSidebarBtn.addEventListener('click', toggleSidebar);
            
            // Theme toggle
            themeToggle.addEventListener('change', toggleTheme);
            
            // Edit user button
            if (editUserBtn) {
                editUserBtn.addEventListener('click', () => {
                    openModal(editUserModal);
                });
            }
            
            // Toggle status button
            if (toggleStatusBtn) {
                toggleStatusBtn.addEventListener('click', () => {
                    const userId = toggleStatusBtn.dataset.id;
                    const action = toggleStatusBtn.dataset.action;
                    toggleUserStatus(userId, action);
                });
            }
            
            // Cancel edit button
            cancelEditBtn.addEventListener('click', () => {
                closeModal(editUserModal);
            });
            
            // Save user button
            saveUserBtn.addEventListener('click', saveUser);
            
            // Close modal buttons
            closeModalButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    closeModal(btn.closest('.modal'));
                });
            });
            
            // Close modals when clicking outside
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    closeModal(e.target);
                }
            });
        });
    </script>
</body>
</html>
