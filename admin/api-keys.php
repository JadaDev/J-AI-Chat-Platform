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

// Get API keys
$apiKeys = getAPIKeys();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys Management - <?php echo htmlspecialchars($GLOBALS['site_name']); ?> Admin</title>
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
                <li class="active">
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
            <h1>API Keys Management</h1>
            <div class="admin-actions">
                <button id="add-key-btn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add API Key
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
            <div class="card-header" style="margin-bottom: 20px;">
                <p>API keys are used to authenticate requests to the AI services. You can add multiple keys for load balancing and fallback.</p>
            </div>
            
            <!-- API Keys Table -->
            <div class="table-card">
                <div class="card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>API Key</th>
                                <th>Created By</th>
                                <th>Created On</th>
                                <th>Usage Count</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($apiKeys['api_keys'])): ?>
                            <tr>
                                <td colspan="7" class="empty-state">No API keys found. Add your first API key to get started.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($apiKeys['api_keys'] as $key): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key['service']); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="masked-key">••••••••••••<?php echo substr(htmlspecialchars($key['api_key']), -4); ?></span>
                                        <button class="action-btn view-key-btn" title="Show" data-key="<?php echo htmlspecialchars($key['api_key']); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn copy-key-btn" title="Copy" data-key="<?php echo htmlspecialchars($key['api_key']); ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($key['created_by_username']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($key['created_at'])); ?></td>
                                <td><?php echo number_format($key['usage_count']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $key['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $key['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn toggle-key-btn <?php echo $key['is_active'] ? 'deactivate' : 'activate'; ?>" 
                                                title="<?php echo $key['is_active'] ? 'Deactivate' : 'Activate'; ?>" 
                                                data-id="<?php echo $key['id']; ?>" 
                                                data-action="<?php echo $key['is_active'] ? 'deactivate' : 'activate'; ?>">
                                            <i class="fas <?php echo $key['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                        </button>
                                        <button class="action-btn delete-key-btn" title="Delete" data-id="<?php echo $key['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- API Key Information -->
            <div class="admin-form" style="margin-top: 30px;">
                <div class="form-section">
                    <h3>API Key Information</h3>
                    <p>This application uses the Gemini API from Google AI. You can obtain API keys from the <a href="https://makersuite.google.com/app/apikey" target="_blank" style="color: var(--accent-color);">Google AI Studio</a>.</p>
                    <p style="margin-top: 10px;">When adding a key, use the service name 'google_ai' for Gemini models.</p>
                </div>
                
                <div class="form-section">
                    <h3>API Usage Guidelines</h3>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>Each API key has rate limits set by the provider</li>
                        <li>The system automatically uses keys with the lowest usage count for load balancing</li>
                        <li>Deactivated keys will not be used for API requests</li>
                        <li>Usage statistics help monitor consumption and costs</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add API Key Modal -->
    <div id="api-key-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add API Key</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="api-key-form">
                    <div class="form-group">
                        <label for="service">Service</label>
                        <select id="service" name="service" class="form-select" required>
                            <option value="google_ai">Google AI (Gemini)</option>
                            <option value="custom">Custom Service</option>
                        </select>
                    </div>
                    
                    <div id="custom-service-container" style="display: none;">
                        <div class="form-group">
                            <label for="custom-service">Custom Service Name</label>
                            <input type="text" id="custom-service" name="custom_service" class="form-input" placeholder="Enter service name">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="api-key">API Key</label>
                        <input type="text" id="api-key" name="api_key" class="form-input" placeholder="Enter API key" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancel-key" class="btn btn-secondary">Cancel</button>
                <button id="save-key" class="btn btn-primary">Save API Key</button>
            </div>
        </div>
    </div>
    
    <script>
        // DOM Elements
        const sidebar = document.getElementById('admin-sidebar');
        const openSidebarBtn = document.getElementById('open-sidebar');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const themeToggle = document.getElementById('theme-toggle');
        const addKeyBtn = document.getElementById('add-key-btn');
        const apiKeyModal = document.getElementById('api-key-modal');
        const serviceSelect = document.getElementById('service');
        const customServiceContainer = document.getElementById('custom-service-container');
        const customServiceInput = document.getElementById('custom-service');
        const apiKeyInput = document.getElementById('api-key');
        const cancelKeyBtn = document.getElementById('cancel-key');
        const saveKeyBtn = document.getElementById('save-key');
        const viewKeyBtns = document.querySelectorAll('.view-key-btn');
        const copyKeyBtns = document.querySelectorAll('.copy-key-btn');
        const toggleKeyBtns = document.querySelectorAll('.toggle-key-btn');
        const deleteKeyBtns = document.querySelectorAll('.delete-key-btn');
        
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
        
        // Reset API Key form
        function resetApiKeyForm() {
            document.getElementById('api-key-form').reset();
            serviceSelect.value = 'google_ai';
            customServiceContainer.style.display = 'none';
            customServiceInput.required = false;
        }
        
        // View API Key
        function viewApiKey(keyElement, apiKey) {
            const maskedKey = keyElement.querySelector('.masked-key');
            const viewBtn = keyElement.querySelector('.view-key-btn');
            
            if (maskedKey.textContent.includes('•')) {
                maskedKey.textContent = apiKey;
                viewBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                maskedKey.textContent = '••••••••••••' + apiKey.substr(-4);
                viewBtn.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }
        
        // Copy API Key
        function copyApiKey(apiKey) {
            navigator.clipboard.writeText(apiKey).then(() => {
                showNotification('API key copied to clipboard', 'success');
            }).catch(err => {
                console.error('Failed to copy API key:', err);
                showNotification('Failed to copy API key', 'error');
            });
        }
        
        // Toggle API Key Status
        async function toggleApiKeyStatus(keyId, action) {
            try {
                const response = await fetch('../api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'updateAPIKeyStatus',
                        key_id: keyId,
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
        
        // Delete API Key
        async function deleteApiKey(keyId) {
            if (!confirm('Are you sure you want to delete this API key? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await fetch('../api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'deleteAPIKey',
                        key_id: keyId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page to reflect changes
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
        
        // Save API Key
        async function saveApiKey() {
            const form = document.getElementById('api-key-form');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            let service = serviceSelect.value;
            if (service === 'custom') {
                service = customServiceInput.value;
            }
            
            const apiKey = apiKeyInput.value;
            
            try {
                const response = await fetch('../api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'addAPIKey',
                        service: service,
                        api_key: apiKey
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal(apiKeyModal);
                    
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
            
            // Service select change
            serviceSelect.addEventListener('change', () => {
                if (serviceSelect.value === 'custom') {
                    customServiceContainer.style.display = 'block';
                    customServiceInput.required = true;
                } else {
                    customServiceContainer.style.display = 'none';
                    customServiceInput.required = false;
                }
            });
            
            // Add API Key button
            addKeyBtn.addEventListener('click', () => {
                resetApiKeyForm();
                openModal(apiKeyModal);
            });
            
            // Cancel API Key button
            cancelKeyBtn.addEventListener('click', () => {
                closeModal(apiKeyModal);
            });
            
            // Save API Key button
            saveKeyBtn.addEventListener('click', saveApiKey);
            
            // View API Key buttons
            viewKeyBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const keyElement = e.target.closest('td');
                    const apiKey = btn.dataset.key;
                    viewApiKey(keyElement, apiKey);
                });
            });
            
            // Copy API Key buttons
            copyKeyBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const apiKey = btn.dataset.key;
                    copyApiKey(apiKey);
                });
            });
            
            // Toggle API Key Status buttons
            toggleKeyBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const keyId = btn.dataset.id;
                    const action = btn.dataset.action;
                    toggleApiKeyStatus(keyId, action);
                });
            });
            
            // Delete API Key buttons
            deleteKeyBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const keyId = btn.dataset.id;
                    deleteApiKey(keyId);
                });
            });
            
            // Close modal when clicking outside
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    closeModal(e.target);
                }
            });
            
            // Close modal buttons
            document.querySelectorAll('.close-modal').forEach(btn => {
                btn.addEventListener('click', () => {
                    closeModal(btn.closest('.modal'));
                });
            });
        });
    </script>
</body>
</html>