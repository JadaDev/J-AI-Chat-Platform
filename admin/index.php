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

// Get system stats
$stats = getSystemStatistics();

// Get users for the table (first page)
$users = getUsers(1, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($GLOBALS['site_name']); ?></title>
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
                <li class="active">
                    <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
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
            <h1>Dashboard</h1>
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
            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Users</h3>
                        <div class="stat-value"><?php echo number_format($stats['users']['total_users'] ?? 0); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo number_format($stats['users']['new_users_7d'] ?? 0); ?> new this week
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon conversations">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Conversations</h3>
                        <div class="stat-value"><?php echo number_format($stats['conversations']['total_conversations'] ?? 0); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo number_format($stats['conversations']['new_conversations_7d'] ?? 0); ?> new this week
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon messages">
                        <i class="fas fa-comment-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Messages</h3>
                        <div class="stat-value"><?php echo number_format($stats['messages']['total_messages'] ?? 0); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo number_format($stats['messages']['new_messages_7d'] ?? 0); ?> new this week
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon tokens">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Tokens Used</h3>
                        <div class="stat-value"><?php echo number_format($stats['messages']['total_tokens'] ?? 0); ?></div>
                        <div class="stat-meta">
                            Approx. cost: $<?php echo number_format(($stats['messages']['total_tokens'] ?? 0) * 0.00001, 2); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Charts -->
            <div class="charts-container">
                <div class="chart-card">
                    <div class="card-header">
                        <h3>User Activity (Last 30 Days)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="userActivityChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Model Usage Distribution</h3>
                    </div>
                    <div class="card-body chart-donut-container">
                        <canvas id="modelUsageChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent Users -->
            <div class="table-card">
                <div class="card-header">
                    <h3>Recent Users</h3>
                    <a href="users.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Conversations</th>
                                <th>Created</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users['users'] as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img src="../<?php echo !empty($user['profile_picture']) && $user['profile_picture'] !== 'default-avatar.png' ? 'uploads/avatars/' . $user['profile_picture'] : 'assets/img/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar-small">
                                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                                        <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge admin-badge">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['conversation_count']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view-btn" title="View" data-id="<?php echo $user['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($user['id'] != $userId): ?>
                                        <button class="action-btn toggle-btn <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?>" 
                                                title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-action="<?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?>">
                                            <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users['users'])): ?>
                            <tr>
                                <td colspan="7" class="empty-state">No users found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Charts data preparation
        <?php
        // Prepare data for user activity chart
        $activityDates = [];
        $tokens = [];
        $requests = [];
        
        // Ensure we have data for the last 30 days even if some days have no activity
        $endDate = new DateTime();
        $startDate = new DateTime();
        $startDate->modify('-29 days');
        
        // Create a map of dates to data
        $dateMap = [];
        foreach ($stats['daily_usage'] as $day) {
            $dateMap[$day['date']] = [
                'tokens' => $day['tokens_used'],
                'requests' => $day['requests']
            ];
        }
        
        // Fill in all dates in the range
        for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
            $dateString = $date->format('Y-m-d');
            $activityDates[] = $date->format('M d');
            
            if (isset($dateMap[$dateString])) {
                $tokens[] = $dateMap[$dateString]['tokens'];
                $requests[] = $dateMap[$dateString]['requests'];
            } else {
                $tokens[] = 0;
                $requests[] = 0;
            }
        }
        
        // Model usage data
        $modelLabels = [];
        $conversationCounts = [];
        $modelColors = [];
        
        // Color map for different models
        $colorMap = [
            'gemini-1.5-pro' => '#EA4335',
            'gemini-1.5-flash' => '#FBBC05',
            'gemini-2.0-flash' => '#7B1FA2'
        ];
        
        foreach ($stats['model_usage'] as $model) {
            $modelLabels[] = $model['model'];
            $conversationCounts[] = $model['conversation_count'];
            
            // Assign color from map or generate one
            if (isset($colorMap[$model['model']])) {
                $modelColors[] = $colorMap[$model['model']];
            } else {
                $modelColors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            }
        }
        ?>
        
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
        
        // User action handlers
        function viewUser(userId) {
            window.location.href = `user-detail.php?id=${userId}`;
        }
        
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
        
        // Initialize charts
        function initCharts() {
            // User activity chart
            const activityCtx = document.getElementById('userActivityChart').getContext('2d');
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($activityDates); ?>,
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
            
            // Model usage chart
            const modelCtx = document.getElementById('modelUsageChart').getContext('2d');
            new Chart(modelCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($modelLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($conversationCounts); ?>,
                        backgroundColor: <?php echo json_encode($modelColors); ?>,
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
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
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
            
            // User action buttons
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    viewUser(btn.dataset.id);
                });
            });
            
            document.querySelectorAll('.toggle-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    toggleUserStatus(btn.dataset.id, btn.dataset.action);
                });
            });
        });
    </script>
</body>
</html>