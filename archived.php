<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'chat_handler.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['user_role'];

// Get archived conversations
$archivedConversations = getArchivedConversations($userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Conversations - <?php echo htmlspecialchars($GLOBALS['site_name']); ?></title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* (Keep your existing styles here - they are fine) */
        .archived-container {
            min-height: 100vh;
            width: 100%;
            padding-bottom: 40px;
        }

        .archived-header {
            background-color: var(--bg-secondary);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .archived-header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .archived-main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .archived-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .archived-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .archived-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .archived-card-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .archived-card-title {
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .archived-card-body {
            padding: 15px;
            min-height: 120px;
        }

        .archived-preview {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .archived-meta {
            color: var(--text-secondary);
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        .archived-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .archived-model {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            background-color: var(--bg-tertiary);
            font-size: 12px;
        }

        .archived-card-footer {
            padding: 12px 15px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .restore-btn {
            background-color: var(--accent-color);
            color: white;
        }

        .restore-btn:hover {
            background-color: var(--accent-hover);
        }

        .delete-btn {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }

        .delete-btn:hover {
            background-color: var(--error-color);
            color: white;
        }

        .back-button {
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .back-button:hover {
            color: var(--accent-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background-color: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .empty-state h2 {
            margin-bottom: 10px;
            font-weight: 600;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .notification.success {
            background-color: var(--success-color);
        }

        .notification.error {
            background-color: var(--error-color);
        }

        @media (max-width: 768px) {
            .archived-header-content {
                flex-direction: column;
                gap: 10px;
            }

            .archived-list {
                grid-template-columns: 1fr;
            }
        }

        .action-button {
            justify-content: space-between;
        }

        .delete-all-btn {
            background-color: var(--error-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .delete-all-btn:hover {
            background-color: darken(var(--error-color), 10%); /* Darken the color on hover */
        }
    </style>
</head>
<body data-theme="dark">
<div class="archived-container">
    <header class="archived-header">
        <div class="archived-header-content">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Chat
            </a>
            <h1>Archived Conversations</h1>

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

    <main class="archived-main">
        <?php if (empty($archivedConversations)): ?>
            <div class="empty-state">
                <i class="fas fa-archive"></i>
                <h2>No Archived Conversations</h2>
                <p>When you archive conversations, they'll appear here.</p>
                <a href="index.php" class="action-button">
                    <i class="fas fa-comment-dots"></i> Go to Active Conversations
                </a>
            </div>
        <?php else: ?>
            <div style="text-align: right; margin-bottom: 10px;">
                <button class="delete-all-btn" id="deleteAllButton">
                    <i class="fas fa-trash"></i> Delete All Permanently
                </button>
            </div>

            <div class="archived-list">
                <?php foreach ($archivedConversations as $conversation): ?>
                    <div class="archived-card" data-id="<?php echo $conversation['id']; ?>">
                        <div class="archived-card-header">
                            <div class="archived-card-title">
                                <i class="fas fa-archive"></i>
                                <?php echo htmlspecialchars($conversation['title']); ?>
                            </div>
                            <span class="archived-model"><?php echo htmlspecialchars($conversation['model']); ?></span>
                        </div>

                        <div class="archived-card-body">
                            <div class="archived-preview">
                                <?php echo htmlspecialchars($conversation['preview']); ?>
                            </div>

                            <div class="archived-meta">
                                <div class="archived-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('M d, Y', strtotime($conversation['updated_at'])); ?>
                                </div>

                                <div class="archived-messages">
                                    <i class="fas fa-comment"></i>
                                    <?php echo $conversation['message_count']; ?> messages
                                </div>
                            </div>
                        </div>

                        <div class="archived-card-footer">
                            <button class="action-btn restore-btn" data-id="<?php echo $conversation['id']; ?>">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                            <button class="action-btn delete-btn" data-id="<?php echo $conversation['id']; ?>">
                                <i class="fas fa-trash"></i> Delete Permanently
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
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

        // Restore conversation
        const restoreButtons = document.querySelectorAll('.restore-btn');
        restoreButtons.forEach(button => {
            button.addEventListener('click', async function () {
                const conversationId = this.dataset.id;

                try {
                    const response = await fetch('api/conversations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'restore',
                            conversation_id: conversationId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showNotification('Conversation restored successfully', 'success');

                        // Remove the card with animation
                        const card = this.closest('.archived-card');
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';

                        setTimeout(() => {
                            card.remove();

                            // Show empty state if no more conversations
                            if (document.querySelectorAll('.archived-card').length === 0) {
                                const archivedMain = document.querySelector('.archived-main');
                                archivedMain.innerHTML = `
                                    <div class="empty-state">
                                        <i class="fas fa-archive"></i>
                                        <h2>No Archived Conversations</h2>
                                        <p>When you archive conversations, they'll appear here.</p>
                                        <a href="index.php" class="action-button">
                                            <i class="fas fa-comment-dots"></i> Go to Active Conversations
                                        </a>
                                    </div>
                                `;
                            }
                        }, 300);
                    } else {
                        showNotification(data.message || 'Failed to restore conversation', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                }
            });
        });

        // Delete conversation permanently
        const deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', async function () {
                if (!confirm('Are you sure you want to delete this conversation permanently? This action cannot be undone.')) {
                    return;
                }

                const conversationId = this.dataset.id;

                try {
                    const response = await fetch('api/conversations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete_permanent',
                            conversation_id: conversationId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showNotification('Conversation deleted permanently', 'success');

                        // Remove the card with animation
                        const card = this.closest('.archived-card');
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';

                        setTimeout(() => {
                            card.remove();

                            // Show empty state if no more conversations
                            if (document.querySelectorAll('.archived-card').length === 0) {
                                const archivedMain = document.querySelector('.archived-main');
                                archivedMain.innerHTML = `
                                    <div class="empty-state">
                                        <i class="fas fa-archive"></i>
                                        <h2>No Archived Conversations</h2>
                                        <p>When you archive conversations, they'll appear here.</p>
                                        <a href="index.php" class="action-button">
                                            <i class="fas fa-comment-dots"></i> Go to Active Conversations
                                        </a>
                                    </div>
                                `;
                            }
                        }, 300);
                    } else {
                        showNotification(data.message || 'Failed to delete conversation', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                }
            });
        });

        // Delete All Conversations
        const deleteAllButton = document.getElementById('deleteAllButton');
        if (deleteAllButton) {
            deleteAllButton.addEventListener('click', async function () {
                if (!confirm('Are you sure you want to delete ALL archived conversations permanently? This action cannot be undone.')) {
                    return;
                }

                // Get all conversation IDs
                const conversationIds = Array.from(document.querySelectorAll('.archived-card'))
                    .map(card => card.dataset.id);

                try {
                    // Send a request to delete all conversations
                    const response = await fetch('api/conversations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete_all_permanent',
                            conversation_ids: conversationIds // Send the array of IDs
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showNotification('All archived conversations deleted permanently', 'success');

                        // Remove all cards with animation
                        const archivedList = document.querySelector('.archived-list');
                        const cards = document.querySelectorAll('.archived-card');

                        cards.forEach(card => {
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.9)';
                        });

                        setTimeout(() => {
                            archivedList.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-archive"></i>
                                    <h2>No Archived Conversations</h2>
                                    <p>When you archive conversations, they'll appear here.</p>
                                    <a href="index.php" class="action-button">
                                        <i class="fas fa-comment-dots"></i> Go to Active Conversations
                                    </a>
                                </div>
                            `;
                        }, 300);
                    } else {
                        showNotification(data.message || 'Failed to delete all conversations', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                }
            });
        }

        // Show notification
        function showNotification(message, type) {
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
    });
</script>
</body>
</html>