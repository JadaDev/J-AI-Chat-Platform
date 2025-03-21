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

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$users = getUsers($page, $limit, $search, $statusFilter, $roleFilter);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo htmlspecialchars($GLOBALS['site_name']); ?> Admin</title>
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
            <h1>User Management</h1>
            <div class="admin-actions">
                <button id="add-user-btn" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add User
                </button>
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
            <!-- Search and Filter -->
            <div class="admin-form">
                <div class="form-section">
                    <form id="search-filter-form" method="get" action="users.php">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Search Users</label>
                                <div class="search-input-group">
                                    <input type="text" id="search-input" name="search" class="form-input" placeholder="Username, email or name..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button type="submit" id="search-btn" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Filter</label>
                                <div class="filter-input-group">
                                    <select id="status-filter" name="status" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                    <select id="role-filter" name="role" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Roles</option>
                                        <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Users Table -->
            <div class="table-card">
                <div class="card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Conversations</th>
                                <th>Tokens Used</th>
                                <th>Created</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
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
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td><?php echo $user['conversation_count']; ?></td>
                                <td><?php echo number_format($user['total_tokens'] ?? 0); ?></td>
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
                                        <button class="action-btn edit-btn" title="Edit" data-id="<?php echo $user['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $userId): ?>
                                        <button class="action-btn role-btn"
                                                title="<?php echo $user['role'] === 'admin' ? 'Make User' : 'Make Admin'; ?>"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-current-role="<?php echo $user['role']; ?>">
                                            <i class="fas <?php echo $user['role'] === 'admin' ? 'fa-user' : 'fa-user-tie'; ?>"></i>
                                        </button>
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
                                <td colspan="9" class="empty-state">No users found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if ($users['pagination']['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <div class="page-item">
                        <a href="?page=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status='.urlencode($statusFilter) : ''; ?><?php echo !empty($roleFilter) ? '&role='.urlencode($roleFilter) : ''; ?>" class="page-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </div>
                    <div class="page-item">
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status='.urlencode($statusFilter) : ''; ?><?php echo !empty($roleFilter) ? '&role='.urlencode($roleFilter) : ''; ?>" class="page-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($start + 4, $users['pagination']['total_pages']);
                    $start = max(1, $end - 4);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <div class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status='.urlencode($statusFilter) : ''; ?><?php echo !empty($roleFilter) ? '&role='.urlencode($roleFilter) : ''; ?>" class="page-link"><?php echo $i; ?></a>
                    </div>
                    <?php endfor; ?>
                    <?php if ($page < $users['pagination']['total_pages']): ?>
                    <div class="page-item">
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status='.urlencode($statusFilter) : ''; ?><?php echo !empty($roleFilter) ? '&role='.urlencode($roleFilter) : ''; ?>" class="page-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </div>
                    <div class="page-item">
                        <a href="?page=<?php echo $users['pagination']['total_pages']; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status='.urlencode($statusFilter) : ''; ?><?php echo !empty($roleFilter) ? '&role='.urlencode($roleFilter) : ''; ?>" class="page-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <!-- Add/Edit User Modal -->
    <div id="user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add New User</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="user-form">
                    <input type="hidden" id="user-id" name="user_id" value="0">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-input" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input">
                        <small class="form-text" id="password-help">Leave blank to keep current password (when editing).</small>
                    </div>
                    <div class="form-group">
                        <label for="user-role">Role</label>
                        <select id="user-role" name="role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <div>
                            <label class="form-switch">
                                <input type="checkbox" id="is-active" name="is_active" checked>
                                <span class="switch-slider"></span>
                            </label>
                            <span class="form-text" style="margin-left: 10px;">Active</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancel-user" class="btn btn-secondary">Cancel</button>
                <button id="save-user" class="btn btn-primary">Save User</button>
            </div>
        </div>
    </div>
    <script>
        // DOM Elements
        const sidebar = document.getElementById('admin-sidebar');
        const openSidebarBtn = document.getElementById('open-sidebar');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const themeToggle = document.getElementById('theme-toggle');
        const addUserBtn = document.getElementById('add-user-btn');
        const userModal = document.getElementById('user-modal');
        const modalTitle = document.getElementById('modal-title');
        const userForm = document.getElementById('user-form');
        const userId = document.getElementById('user-id');
        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const firstName = document.getElementById('first_name');
        const lastName = document.getElementById('last_name');
        const password = document.getElementById('password');
        const passwordHelp = document.getElementById('password-help');
        const userRole = document.getElementById('user-role');
        const isActive = document.getElementById('is-active');
        const cancelUserBtn = document.getElementById('cancel-user');
        const saveUserBtn = document.getElementById('save-user');
        const closeModalButtons = document.querySelectorAll('.close-modal');
        const searchFilterForm = document.getElementById('search-filter-form');
        const searchInput = document.getElementById('search-input');
        const statusFilter = document.getElementById('status-filter');
        const roleFilter = document.getElementById('role-filter');
        
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
        
        function resetUserForm() {
            userForm.reset();
            userId.value = '0';
            modalTitle.textContent = 'Add New User';
            password.required = true;
            passwordHelp.style.display = 'none';
            isActive.checked = true;
        }
        
        async function fetchUserData(id) {
            try {
                const response = await fetch(`../api/admin.php?action=getUser&id=${id}`);
                const data = await response.json();
                if (data.success) {
                    return data.user;
                } else {
                    showNotification(data.message, 'error');
                    return null;
                }
            } catch (error) {
                console.error('Error fetching user data:', error);
                showNotification('Failed to fetch user data', 'error');
                return null;
            }
        }
        
        async function openEditUserModal(id) {
            resetUserForm();
            const user = await fetchUserData(id);
            if (!user) return;
            userId.value = user.id;
            username.value = user.username;
            email.value = user.email;
            firstName.value = user.first_name || '';
            lastName.value = user.last_name || '';
            userRole.value = user.role;
            isActive.checked = user.is_active;
            password.required = false;
            passwordHelp.style.display = 'block';
            modalTitle.textContent = 'Edit User';
            openModal(userModal);
        }
        
        async function saveUser() {
            // Basic validation
            if (!userForm.checkValidity()) {
                userForm.reportValidity();
                return;
            }
            
            const formData = {
                user_id: userId.value,
                username: username.value,
                email: email.value,
                first_name: firstName.value,
                last_name: lastName.value,
                role: userRole.value,
                is_active: isActive.checked
            };
            
            // Add password only if it's provided
            if (password.value) {
                formData.password = password.value;
            }
            
            try {
                const response = await fetch('../api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: formData.user_id === '0' ? 'addUser' : 'updateUser',
                        ...formData
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal(userModal);
                    // Reload page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error saving user:', error);
                showNotification('An error occurred while saving the user', 'error');
            }
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
        
        async function changeUserRole(userId, currentRole) {
            const newRole = currentRole === 'admin' ? 'user' : 'admin';
            if (!confirm(`Are you sure you want to change this user's role to ${newRole}?`)) {
                return;
            }
            
            try {
                const response = await fetch('../api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'changeUserRole',
                        user_id: userId,
                        role: newRole
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page to show updated role
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
        
        function viewUser(userId) {
            window.location.href = `user-detail.php?id=${userId}`;
        }
        
        // Apply filter when select elements change
        function applyFilters() {
            searchFilterForm.submit();
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
            
            // Sidebar toggle
            openSidebarBtn.addEventListener('click', toggleSidebar);
            closeSidebarBtn.addEventListener('click', toggleSidebar);
            
            // Theme toggle
            themeToggle.addEventListener('change', toggleTheme);
            
            // Close modals
            closeModalButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    closeModal(btn.closest('.modal'));
                });
            });
            
            // Close modal when clicking outside
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    closeModal(e.target);
                }
            });
            
            // Add user button
            addUserBtn.addEventListener('click', () => {
                resetUserForm();
                openModal(userModal);
            });
            
            // Cancel user button
            cancelUserBtn.addEventListener('click', () => {
                closeModal(userModal);
            });
            
            // Save user button
            saveUserBtn.addEventListener('click', saveUser);
            
            // Edit user buttons
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    openEditUserModal(btn.dataset.id);
                });
            });
            
            // View user buttons
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    viewUser(btn.dataset.id);
                });
            });
            
            // Toggle user status buttons
            document.querySelectorAll('.toggle-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    toggleUserStatus(btn.dataset.id, btn.dataset.action);
                });
            });
            
            // Change role buttons
            document.querySelectorAll('.role-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    changeUserRole(btn.dataset.id, btn.dataset.currentRole);
                });
            });
            
            // Filter change events
            statusFilter.addEventListener('change', applyFilters);
            
            // Status filter already has automatic form submission
            // Role filter already has automatic form submission through the onchange attribute
        });
    </script>
</body>
</html>