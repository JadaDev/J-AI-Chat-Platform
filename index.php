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
$userRole = $_SESSION['user_role'];
$preferredModel = $_SESSION['preferred_model'];

// Connect to database
$conn = connectDB();

// Get user profile image
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profileImage = !empty($user['profile_picture']) ? 'uploads/avatars/' . $user['profile_picture'] : 'assets/img/default-avatar.png';
$stmt->close();

// Get all conversations for the sidebar
$stmt = $conn->prepare("
    SELECT conversation_id, title, model, updated_at
    FROM conversations
    WHERE user_id = ? AND is_archived = FALSE
    ORDER BY updated_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}
$stmt->close();

// Close connection
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="<?php echo htmlspecialchars($GLOBALS['site_name']); ?> - Your personal AI chat assistant">
    <title><?php echo htmlspecialchars($GLOBALS['site_name']); ?></title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=5.4">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.0.2/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.3.6/purify.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.4/katex.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.4/katex.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.4/contrib/auto-render.min.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
</head>
<body data-theme="dark">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="assets/img/logo.svg" alt="<?php echo htmlspecialchars($GLOBALS['site_name']); ?>">
                <span><?php echo htmlspecialchars($GLOBALS['site_name']); ?></span>
            </div>
            <button class="close-sidebar-btn" id="close-sidebar"><i class="fas fa-times"></i></button>
        </div>
        <div class="new-chat">
            <button id="new-chat-btn" class="new-chat-btn">
                <i class="fas fa-plus"></i> New Chat
            </button>
        </div>
        <div class="conversations-container">
            <div class="conversations-header">
                <h3>Recent Conversations</h3>
                <button id="delete-all-conversations-btn" class="delete-all-conversations-btn">
                    <i class="fas fa-trash-alt"></i> Delete All
                </button>
            </div>
            <div class="conversations-list" id="conversations-list">
                <?php foreach ($conversations as $conversation): ?>
                <div class="conversation-item" data-id="<?php echo htmlspecialchars($conversation['conversation_id']); ?>">
                    <div class="conversation-icon">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <div class="conversation-info">
                        <div class="conversation-title"><?php echo htmlspecialchars($conversation['title']); ?></div>
                        <div class="conversation-meta">
                            <span class="conversation-model"><?php echo htmlspecialchars($conversation['model']); ?></span>
                            <span class="conversation-date"><?php echo date('M d', strtotime($conversation['updated_at'])); ?></span>
                        </div>
                    </div>
                    <div class="conversation-actions">
                        <button class="rename-btn" title="Rename" data-id="<?php echo htmlspecialchars($conversation['conversation_id']); ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="delete-btn" title="Delete" data-id="<?php echo htmlspecialchars($conversation['conversation_id']); ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($conversations)): ?>
                <div class="empty-state">
                    <p>No conversations yet. Start a new chat!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="sidebar-footer">
            <div class="user-section">
                <div class="user-info">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="<?php echo htmlspecialchars($username); ?>" class="user-avatar">
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
                    </div>
                </div>
                <div class="user-menu">
                    <a href="profile.php" title="Profile"><i class="fas fa-user"></i></a>
                    <?php if (isAdmin()): ?>
                    <a href="admin/index.php" title="Admin Panel"><i class="fas fa-cog"></i></a>
                    <?php endif; ?>
                    <a href="archived.php" title="Archived Chats"><i class="fas fa-archive"></i></a>
                    <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="chat-container">
            <!-- Header -->
            <header class="chat-header">
                <button id="open-sidebar" class="open-sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="chat-title" id="chat-title">New Chat</div>
                <div class="chat-actions">
                    <select class="model-select" id="model-select">
                        <option value="gemini-1.5-pro" <?php echo $preferredModel === 'gemini-1.5-pro' ? 'selected' : ''; ?>>Gemini 1.5 Pro</option>
                        <option value="gemini-1.5-flash" <?php echo $preferredModel === 'gemini-1.5-flash' ? 'selected' : ''; ?>>Gemini 1.5 Flash</option>
                        <option value="gemini-2.0-flash" <?php echo $preferredModel === 'gemini-2.0-flash' ? 'selected' : ''; ?>>Gemini 2.0 Flash</option>
                    </select>
                    <button id="export-chat" class="action-button" title="Export Chat" disabled>
                        <i class="fas fa-download"></i>
                    </button>
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

            <!-- Messages Area -->
            <div class="messages-container" id="messages">
                <div class="welcome-screen" id="welcome-screen">
                    <div class="welcome-logo">
                        <img src="assets/img/logo-large.svg" alt="<?php echo htmlspecialchars($GLOBALS['site_name']); ?>">
                    </div>
                    <h1>Welcome to <?php echo htmlspecialchars($GLOBALS['site_name']); ?></h1>
                    <p>Select a conversation from the sidebar or start a new chat.</p>
                    <?php if (empty($conversations)): ?>
                    <div class="suggestion-chips">
                        <button class="suggestion-chip" data-text="Tell me a fun fact">Tell me a fun fact</button>
                        <button class="suggestion-chip" data-text="How can you help me with coding?">Help with coding</button>
                        <button class="suggestion-chip" data-text="Write a short poem about AI">Write a poem</button>
                        <button class="suggestion-chip" data-text="Explain quantum computing simply">Explain quantum computing</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="typing-indicator" id="typing-indicator" style="display: none;">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>

            <!-- Input Area -->
            <div class="input-container">
                <textarea id="message-input" class="message-input" placeholder="Type a message..." rows="1"></textarea>
                <button id="send-message" class="send-button">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </main>

    <!-- Modals -->
    <div id="rename-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Rename Conversation</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="new-title" class="modal-input" placeholder="Enter new title">
            </div>
            <div class="modal-footer">
                <button id="cancel-rename" class="modal-btn cancel-btn">Cancel</button>
                <button id="confirm-rename" class="modal-btn confirm-btn">Save</button>
            </div>
        </div>
    </div>

    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Conversation</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this conversation?</p>
                <p class="modal-warning">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button id="cancel-delete" class="modal-btn cancel-btn">Cancel</button>
                <button id="confirm-delete" class="modal-btn delete-btn">Delete</button>
            </div>
        </div>
    </div>
    <!-- Delete All Confirmation Modal -->
    <div id="delete-all-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete All Conversations</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete ALL conversations?</p>
                <p class="modal-warning">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button id="cancel-delete-all" class="modal-btn cancel-btn">Cancel</button>
                <button id="confirm-delete-all" class="modal-btn delete-btn">Delete All</button>
            </div>
        </div>
    </div>

    <div id="preview-modal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2>Code Preview</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body preview-container">
                <iframe id="preview-iframe" name="preview-iframe" sandbox="allow-scripts allow-same-origin"></iframe>
            </div>
        </div>
    </div>
    <!-- Hidden form for code preview -->
    <form id="preview-form" method="post" action="preview.php" target="preview-iframe" style="display: none;">
        <input type="hidden" id="preview-html" name="html" value="">
        <input type="hidden" id="preview-css" name="css" value="">
        <input type="hidden" id="preview-js" name="js" value="">
    </form>

    <!-- Scripts -->
    <script>
        // Current conversation state
        let currentConversationId = null;
        let currentModel = "<?php echo $preferredModel; ?>";
        let isScrolling = false;
        let isProcessingMessage = false;
        let isCreatingConversation = false;
        let appInitialized = false; // Track if app is fully initialized

        // DOM elements
        const sidebar = document.getElementById('sidebar');
        const openSidebarBtn = document.getElementById('open-sidebar');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const newChatBtn = document.getElementById('new-chat-btn');
        const conversationsList = document.getElementById('conversations-list');
        const messagesContainer = document.getElementById('messages');
        const welcomeScreen = document.getElementById('welcome-screen');
        const chatTitle = document.getElementById('chat-title');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-message');
        const modelSelect = document.getElementById('model-select');
        const typingIndicator = document.getElementById('typing-indicator');
        const themeToggle = document.getElementById('theme-toggle');
        const exportChatBtn = document.getElementById('export-chat');
        const deleteAllConversationsBtn = document.getElementById('delete-all-conversations-btn');

        // Modal elements
        const renameModal = document.getElementById('rename-modal');
        const newTitleInput = document.getElementById('new-title');
        const confirmRenameBtn = document.getElementById('confirm-rename');
        const cancelRenameBtn = document.getElementById('cancel-rename');
        const deleteModal = document.getElementById('delete-modal');
        const confirmDeleteBtn = document.getElementById('confirm-delete');
        const cancelDeleteBtn = document.getElementById('cancel-delete');
        const deleteAllModal = document.getElementById('delete-all-modal');
        const confirmDeleteAllBtn = document.getElementById('confirm-delete-all');
        const cancelDeleteAllBtn = document.getElementById('cancel-delete-all');
        const closeModalButtons = document.querySelectorAll('.close-modal');
        const previewModal = document.getElementById('preview-modal');
        const previewIframe = document.getElementById('preview-iframe');

        // Preview form elements
        const previewForm = document.getElementById('preview-form');
        const previewHtmlInput = document.getElementById('preview-html');
        const previewCssInput = document.getElementById('preview-css');
        const previewJsInput = document.getElementById('preview-js');

        // COMPLETELY REWORKED SCROLL HANDLING
        // This helper manages scroll position in a more reliable way
        const scrollHelper = {
            shouldScrollToBottom: true,
            lastScrollTop: 0,
            lastScrollHeight: 0,

            // Check if user has scrolled up
            checkScrollPosition: function() {
                const { scrollTop, scrollHeight, clientHeight } = messagesContainer;
                const isNearBottom = scrollTop + clientHeight >= scrollHeight - 100;
                this.shouldScrollToBottom = isNearBottom;
            },

            // Initialize scroll listener
            init: function() {
                messagesContainer.addEventListener('scroll', () => {
                    if (!isScrolling) {
                        this.checkScrollPosition();
                        this.lastScrollTop = messagesContainer.scrollTop;
                        this.lastScrollHeight = messagesContainer.scrollHeight;
                    }
                });

                // Watch for container resizes
                if (window.ResizeObserver) {
                    const resizeObserver = new ResizeObserver(() => {
                        if (this.shouldScrollToBottom) {
                            this.scrollToBottom(false);
                        }
                    });
                    resizeObserver.observe(messagesContainer);
                }
            },

            // Scroll to bottom with a lock to prevent interference
            scrollToBottom: function(smooth = false) {
                if (!messagesContainer) return;

                // Prevent scroll events from changing shouldScrollToBottom during programmatic scrolling
                isScrolling = true;

                // Use requestAnimationFrame for better performance
                requestAnimationFrame(() => {
                    messagesContainer.scrollTo({
                        top: messagesContainer.scrollHeight,
                        behavior: smooth ? 'smooth' : 'auto'
                    });

                    // Release the scroll lock after animation completes
                    setTimeout(() => {
                        isScrolling = false;
                        this.lastScrollHeight = messagesContainer.scrollHeight;
                        this.lastScrollTop = messagesContainer.scrollTop;
                    }, smooth ? 300 : 50);
                });
            },

            // Reset scroll state
            reset: function() {
                this.shouldScrollToBottom = true;
            }
        };

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

        // Reset to new chat state and show welcome screen
        function resetToNewChat() {
            try {
                // Reset state variables
                currentConversationId = null;

                // Set model to user's preferred model
                modelSelect.value = "<?php echo $preferredModel; ?>";
                currentModel = modelSelect.value;

                // Update UI elements
                chatTitle.textContent = 'New Chat';

                // Remove all content and re-add welcome screen
                messagesContainer.innerHTML = '';

                // Create a fresh copy of the welcome screen
                const welcomeScreenHTML = `
                    <div class="welcome-screen" id="welcome-screen">
                        <div class="welcome-logo">
                            <img src="assets/img/logo-large.svg" alt="<?php echo htmlspecialchars($GLOBALS['site_name']); ?>">
                        </div>
                        <h1>Welcome to <?php echo htmlspecialchars($GLOBALS['site_name']); ?></h1>
                        <p>Select a conversation from the sidebar or start a new chat.</p>
                        <?php if (empty($conversations)): ?>
                        <div class="suggestion-chips">
                            <button class="suggestion-chip" data-text="Tell me a fun fact">Tell me a fun fact</button>
                            <button class="suggestion-chip" data-text="How can you help me with coding?">Help with coding</button>
                            <button class="suggestion-chip" data-text="Write a short poem about AI">Write a poem</button>
                            <button class="suggestion-chip" data-text="Explain quantum computing simply">Explain quantum computing</button>
                        </div>
                        <?php endif; ?>
                    </div>
                `;
                messagesContainer.innerHTML = welcomeScreenHTML;

                // Reattach click events to the new suggestion chips
                const newChips = messagesContainer.querySelectorAll('.suggestion-chip');
                newChips.forEach(chip => {
                    chip.addEventListener('click', () => {
                        messageInput.value = chip.dataset.text;
                        sendMessage();
                    });
                });

                // Update other UI states
                exportChatBtn.disabled = true;

                // Remove active class from all conversations
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                });

                // Close sidebar on mobile
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('active');
                }

                // Reset scroll state
                scrollHelper.reset();
                scrollHelper.scrollToBottom();

            } catch (error) {
                console.error('Error in resetToNewChat:', error);

                // Fallback to a simpler reset if the rich one fails
                messagesContainer.innerHTML = '<div class="welcome-screen"><h1>Welcome</h1><p>Start a new conversation</p></div>';
                exportChatBtn.disabled = true;
            }
        }

        // Create a new conversation with improved error handling and retries
        async function createNewConversation() {
            if (isCreatingConversation) return null; // Prevent multiple simultaneous creations
            let retryCount = 0;
            const maxRetries = 2;

            while (retryCount <= maxRetries) {
                try {
                    isCreatingConversation = true;

                    // Use the selected model from the model-select dropdown
                    currentModel = modelSelect.value;

                    // Clear existing conversation
                    messagesContainer.innerHTML = '<div class="loading-spinner"></div>';

                    // Hide welcome screen if it exists
                    const welcomeScreenElement = document.getElementById('welcome-screen');
                    if (welcomeScreenElement) {
                        welcomeScreenElement.style.display = 'none';
                    }

                    const response = await fetch('api/conversations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'create',
                            title: 'New Chat',
                            model: currentModel
                        })
                    });

                    // Check for HTTP errors
                    if (!response.ok) {
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }

                    // Parse response text
                    const responseText = await response.text();

                    // Try to parse as JSON
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('Failed to parse API response:', responseText);
                        throw new Error('Invalid API response structure');
                    }

                    if (data.success) {
                        // Reset scroll state for new conversation
                        scrollHelper.reset();

                        // Add to conversations list
                        addConversationToList(data.conversation_id, 'New Chat', currentModel);

                        // Update current conversation ID
                        currentConversationId = data.conversation_id;

                        // Update UI
                        chatTitle.textContent = 'New Chat';
                        exportChatBtn.disabled = false;

                        // Create empty message list
                        messagesContainer.innerHTML = '';
                        const messageList = document.createElement('div');
                        messageList.className = 'message-list';
                        messagesContainer.appendChild(messageList);

                        // Close sidebar on mobile
                        if (window.innerWidth < 768) {
                            sidebar.classList.remove('active');
                        }

                        // Return the conversation ID for chaining
                        return data.conversation_id;
                    } else {
                        throw new Error(data.message || 'Failed to create conversation');
                    }

                } catch (error) {
                    console.error(`Error creating conversation (attempt ${retryCount + 1}):`, error);

                    if (retryCount < maxRetries) {
                        // Wait before retrying (exponential backoff)
                        await new Promise(resolve => setTimeout(resolve, 1000 * Math.pow(2, retryCount)));
                        retryCount++;
                        showNotification(`Retrying... (${retryCount}/${maxRetries})`, 'info');
                    } else {
                        showNotification('Failed to create new conversation after multiple attempts', 'error');
                        resetToNewChat(); // Reset to welcome screen on error
                        return null;
                    }
                } finally {
                    isCreatingConversation = false;
                }
            }

            return null; // If we get here, all retries failed
        }

        // Add conversation to sidebar list
        function addConversationToList(id, title, model) {
            try {
                // Check if we already have an empty state message
                const emptyState = conversationsList.querySelector('.empty-state');
                if (emptyState) {
                    conversationsList.removeChild(emptyState);
                }

                // Check if this conversation already exists in the list
                const existingItem = document.querySelector(`.conversation-item[data-id="${id}"]`);
                if (existingItem) {
                    // If it exists, just update it and move to top
                    const titleElement = existingItem.querySelector('.conversation-title');
                    if (titleElement) {
                        titleElement.textContent = escapeHTML(title);
                    }

                    // Move to top
                    conversationsList.insertBefore(existingItem, conversationsList.firstChild);
                    return;
                }

                const conversationItem = document.createElement('div');
                conversationItem.className = 'conversation-item active'; // Mark as active by default
                conversationItem.dataset.id = id;
                conversationItem.innerHTML = `
                    <div class="conversation-icon">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <div class="conversation-info">
                        <div class="conversation-title">${escapeHTML(title)}</div>
                        <div class="conversation-meta">
                            <span class="conversation-model">${escapeHTML(model)}</span>
                            <span class="conversation-date">Just now</span>
                        </div>
                    </div>
                    <div class="conversation-actions">
                        <button class="rename-btn" title="Rename" data-id="${id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="delete-btn" title="Delete" data-id="${id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;

                // Remove active class from all other conversations
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                });

                // Add click event for loading conversation
                conversationItem.addEventListener('click', (e) => {
                    if (!e.target.closest('.rename-btn') && !e.target.closest('.delete-btn')) {
                        loadConversation(id);
                    }
                });

                // Add rename and delete button events
                const renameBtn = conversationItem.querySelector('.rename-btn');
                renameBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const title = conversationItem.querySelector('.conversation-title').textContent;
                    showRenameModal(id, title);
                });

                const deleteBtn = conversationItem.querySelector('.delete-btn');
                deleteBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    showDeleteModal(id);
                });

                // Add to the beginning of the list
                conversationsList.insertBefore(conversationItem, conversationsList.firstChild);

            } catch (error) {
                console.error('Error adding conversation to list:', error);
                // Don't show notification to avoid overwhelming the user
            }
        }

        // Function to refresh the conversations list from the server
        async function refreshConversationsList() {
            try {
                const response = await fetch('api/conversations.php?action=list');
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}`);
                }

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse conversations list response:', responseText);
                    throw new Error('Invalid API response structure');
                }

                if (data.success && Array.isArray(data.conversations)) {
                    // Clear current list
                    conversationsList.innerHTML = '';

                    // Rebuild the list
                    if (data.conversations.length === 0) {
                        conversationsList.innerHTML = `
                            <div class="empty-state">
                                <p>No conversations yet. Start a new chat!</p>
                            </div>
                        `;
                    } else {
                        data.conversations.forEach(conv => {
                            addConversationToList(conv.conversation_id, conv.title, conv.model);
                        });
                    }

                    return true;
                } else {
                    throw new Error(data.message || 'Failed to refresh conversations');
                }

            } catch (error) {
                console.error('Error refreshing conversations list:', error);
                showNotification('Failed to refresh conversations list', 'error');
                return false;
            }
        }

        // Load conversation messages with improved error handling
        async function loadConversation(conversationId) {
            try {
                // Show loading indicator
                messagesContainer.innerHTML = '<div class="loading-spinner"></div>';

                // Hide welcome screen
                const welcomeScreenElement = document.getElementById('welcome-screen');
                if (welcomeScreenElement) {
                    welcomeScreenElement.style.display = 'none';
                }

                // Reset scroll tracking
                scrollHelper.reset();

                const response = await fetch(`api/conversations.php?action=get&id=${conversationId}`);
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse conversation data:', responseText);
                    throw new Error('Invalid API response structure');
                }

                if (data.success) {
                    currentConversationId = conversationId;

                    // Update UI
                    chatTitle.textContent = data.conversation.title;
                    exportChatBtn.disabled = false;

                    // Update model select
                    modelSelect.value = data.conversation.model;
                    currentModel = data.conversation.model;

                    // Highlight active conversation
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        item.classList.remove('active');
                        if (item.dataset.id === conversationId.toString()) {
                            item.classList.add('active');
                        }
                    });

                    // Clear previous messages and reset state
                    messagesContainer.innerHTML = '';

                    // Create message container that will help with scroll anchoring
                    const messageList = document.createElement('div');
                    messageList.className = 'message-list';
                    messagesContainer.appendChild(messageList);

                    // Add loading class for long chats
                    if (data.messages.length > 20) {
                        messageList.classList.add('loading-long-chat');
                    }

                    // Batch processing to prevent layout thrashing
                    if (data.messages.length > 0) {
                        // Build all messages off DOM first
                        const fragment = document.createDocumentFragment();
                        data.messages.forEach(message => {
                            const messageEl = createMessageElement(message.content, message.role === 'user', false);
                            fragment.appendChild(messageEl);
                        });

                        // Then append all at once
                        messageList.appendChild(fragment);

                        // Scroll to bottom after a slight delay to ensure rendering
                        setTimeout(() => {
                            scrollHelper.scrollToBottom();

                            // Remove loading class after scroll
                            if (data.messages.length > 20) {
                                setTimeout(() => {
                                    messageList.classList.remove('loading-long-chat');
                                }, 200);
                            }
                        }, 100);
                    }

                } else {
                    // Handle API error response
                    showNotification(data.message || 'Failed to load conversation', 'error');
                    messagesContainer.innerHTML = '<div class="error-message">Failed to load conversation</div>';

                    // If conversation not found, reset to new chat
                    if (data.message && data.message.includes('not found')) {
                        resetToNewChat();
                    }
                }

            } catch (error) {
                console.error('Error loading conversation:', error);
                messagesContainer.innerHTML = '<div class="error-message">Failed to load conversation</div>';
                showNotification('Failed to load conversation: ' + error.message, 'error');

                // If we encounter a serious error, reset to new chat
                setTimeout(() => resetToNewChat(), 1500);
            }
        }

                // Send a message - with auto-create conversation if needed
        async function sendMessage() {
            const content = messageInput.value.trim();
            if (!content || isProcessingMessage) return;

            // Set flag to prevent double-sending
            isProcessingMessage = true;

            // Clear input and reset its height
            messageInput.value = '';
            messageInput.style.height = 'auto';

            try {
                // If there's no current conversation, create one first
                if (!currentConversationId) {
                    showNotification("Creating a new conversation...", "info");
                    const newConversationId = await createNewConversation();
                    if (!newConversationId) {
                        throw new Error("Failed to create conversation");
                    }
                    currentConversationId = newConversationId;

                    // Wait briefly for the conversation to be fully created and initialized
                    await new Promise(resolve => setTimeout(resolve, 1500)); // Adjust delay if needed
                }

                // Double check we have a valid conversation ID before sending
                if (!currentConversationId) {
                    throw new Error("No active conversation to send message to");
                }

                // Reset scroll position for new messages
                scrollHelper.reset();

                // Add user message to chat
                addMessageToChat(content, true);

                // Show typing indicator
                typingIndicator.style.display = 'flex';

                const response = await fetch('api/messages.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'send',
                        conversation_id: currentConversationId,
                        content: content
                    })
                });

                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse message response:', responseText);
                    throw new Error('Invalid API response structure');
                }

                // Hide typing indicator
                typingIndicator.style.display = 'none';

                if (data.success) {
                    // Add AI response to chat
                    addMessageToChat(data.ai_message.content, false);

                    // Update conversation list
                    updateConversationDisplayInfo();
                } else {
                    // If we get an error about conversation not found, reset the chat
                    if (data.message && (data.message.includes('conversation not found') || data.message.includes('Conversation not found'))) {
                        resetToNewChat();
                        showNotification('Conversation no longer exists. Starting new chat.', 'warning');
                    } else {
                        addMessageToChat("Sorry, I encountered an error while processing your request. Please try again.", false);
                        showNotification(data.message || 'Unknown error', 'error');
                    }
                }

            } catch (error) {
                console.error('Error sending message:', error);
                typingIndicator.style.display = 'none';

                if (error.message.includes('Invalid API response structure')) {
                    addMessageToChat("I apologize, but there was a problem with the server response. Let's try starting a new chat.", false);
                    setTimeout(() => {
                        resetToNewChat();
                    }, 2000);
                } else {
                    addMessageToChat("I apologize, but there was an error. Please try again.", false);
                }

                showNotification('Failed to send message: ' + error.message, 'error');
            } finally {
                // Re-enable input
                messageInput.disabled = false;
                sendButton.disabled = false;
                messageInput.focus();

                // Reset processing flag
                isProcessingMessage = false;
            }
        }

        // Create a message element without adding to DOM
        function createMessageElement(content, isUser, animate = true) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', isUser ? 'user' : 'ai');

            if (animate) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(10px)';
                messageDiv.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            }

            if (isUser) {
                messageDiv.textContent = content;
            } else {
                // For AI messages, process markdown and add code features
                messageDiv.classList.add('markdown');

                try {
                    messageDiv.innerHTML = DOMPurify.sanitize(marked.parse(content));

                    // Enhanced code blocks with copy and preview buttons
                    enhanceCodeBlocks(messageDiv, content);

                    // Render math expressions
                    if (typeof renderMathInElement === 'function') {
                        setTimeout(() => {
                            try {
                                renderMathInElement(messageDiv, {
                                    delimiters: [
                                        { left: '$$', right: '$$', display: true },
                                        { left: '$', right: '$', display: false },
                                        { left: '\\(', right: '\\)', display: false },
                                        { left: '\\[', right: '\\]', display: true }
                                    ],
                                    throwOnError: false,
                                    output: 'html',
                                    fleqn: false
                                });
                            } catch (e) {
                                console.error('Error rendering math:', e);
                            }
                        }, 10);
                    }

                } catch (error) {
                    console.error('Error processing markdown:', error);
                    messageDiv.textContent = content; // Fallback to plain text
                }
            }

            return messageDiv;
        }

        // Add a message to the chat display with improved handling
        function addMessageToChat(content, isUser, animate = true) {
            try {
                // Find or create message-list container
                let messageList = messagesContainer.querySelector('.message-list');
                if (!messageList) {
                    messageList = document.createElement('div');
                    messageList.className = 'message-list';
                    messagesContainer.appendChild(messageList);
                }

                // Create the message element
                const messageDiv = createMessageElement(content, isUser, animate);

                // Append to the container
                messageList.appendChild(messageDiv);

                if (animate) {
                    // Force reflow/repaint before animating
                    void messageDiv.offsetWidth;

                    // Trigger animation after a small delay
                    setTimeout(() => {
                        messageDiv.style.opacity = '1';
                        messageDiv.style.transform = 'translateY(0)';
                    }, 10);
                }

                // Scroll to bottom
                scrollHelper.scrollToBottom(animate);

            } catch (error) {
                console.error('Error adding message to chat:', error);

                // Try a simpler approach if the rich one fails
                try {
                    const simpleMessage = document.createElement('div');
                    simpleMessage.classList.add('message', isUser ? 'user' : 'ai');
                    simpleMessage.textContent = content;
                    messagesContainer.appendChild(simpleMessage);
                    scrollHelper.scrollToBottom();
                } catch (e) {
                    console.error('Even simple message addition failed:', e);
                }
            }
        }

        // Enhance code blocks with copy/preview functionality
        function enhanceCodeBlocks(messageDiv, originalContent) {
            try {
                messageDiv.querySelectorAll('pre').forEach(pre => {
                    const code = pre.querySelector('code');
                    if (!code) return;

                    // Get language from class
                    const languageClass = code.className || '';
                    const language = languageClass.replace('language-', '').trim() || 'plaintext';

                    // Create header for the code block
                    const header = document.createElement('div');
                    header.className = 'code-block-header';

                    // Language indicator
                    const langSpan = document.createElement('span');
                    langSpan.className = 'code-language';
                    langSpan.textContent = language;

                    // Container for action buttons
                    const actions = document.createElement('div');
                    actions.className = 'code-actions';

                    // Copy button
                    const copyButton = document.createElement('button');
                    copyButton.className = 'code-btn copy-btn';
                    copyButton.innerHTML = '<i class="fas fa-copy"></i>';
                    copyButton.title = "Copy code";
                    copyButton.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const codeText = code.textContent;
                        navigator.clipboard.writeText(codeText)
                            .then(() => {
                                // Show feedback
                                copyButton.innerHTML = '<i class="fas fa-check"></i>';
                                setTimeout(() => {
                                    copyButton.innerHTML = '<i class="fas fa-copy"></i>';
                                }, 2000);
                            })
                            .catch(err => {
                                console.error('Could not copy text: ', err);
                                showNotification('Failed to copy code', 'error');
                            });
                    });

                    // Extract code content for specific languages
                    let htmlCode = '', cssCode = '', jsCode = '';

                    // Try to extract HTML, CSS and JS code safely
                    try {
                        // Extract HTML
                        const htmlMatches = originalContent.match(/```html\n([\s\S]*?)```/i);
                        if (htmlMatches && htmlMatches[1]) {
                            htmlCode = htmlMatches[1];
                        }

                        // Extract CSS
                        const cssMatches = originalContent.match(/```css\n([\s\S]*?)```/i);
                        if (cssMatches && cssMatches[1]) {
                            cssCode = cssMatches[1];
                        }

                        // Extract JavaScript
                        const jsMatches = originalContent.match(/```javascript\n([\s\S]*?)```/i) ||
                                          originalContent.match(/```js\n([\s\S]*?)```/i);
                        if (jsMatches && jsMatches[1]) {
                            jsCode = jsMatches[1];
                        }

                        // For the current code block, set its content based on language
                        if (language === 'html') {
                            htmlCode = code.textContent;
                        } else if (language === 'css') {
                            cssCode = code.textContent;
                        } else if (language === 'javascript' || language === 'js') {
                            jsCode = code.textContent;
                        }
                    } catch (error) {
                        console.error('Error extracting code content:', error);
                    }

                    // Preview button for HTML/CSS/JS
                    if (['html', 'css', 'javascript', 'js'].includes(language)) {
                        const previewButton = document.createElement('button');
                        previewButton.className = 'code-btn preview-btn';
                        previewButton.innerHTML = '<i class="fas fa-eye"></i>';
                        previewButton.title = "Preview code";
                        previewButton.addEventListener('click', (e) => {
                            e.stopPropagation();
                            showCodePreview(htmlCode, cssCode, jsCode);
                        });
                        actions.appendChild(previewButton);
                    }

                    // Append buttons and language indicator to the header
                    actions.appendChild(copyButton);
                    header.appendChild(langSpan);
                    header.appendChild(actions);

                    // Add header to pre element
                    pre.insertBefore(header, pre.firstChild);

                    // Apply syntax highlighting
                    try {
                        hljs.highlightBlock(code);
                    } catch (e) {
                        console.error("Highlighting error:", e);
                    }
                });

            } catch (error) {
                console.error('Error enhancing code blocks:', error);
                // Don't rethrow to avoid breaking the message display
            }
        }

        // Show code preview modal
        function showCodePreview(html, css, js) {
            try {
                // Set the form inputs
                previewHtmlInput.value = html || '';
                previewCssInput.value = css || '';
                previewJsInput.value = js || '';

                // Show the modal
                previewModal.classList.add('active');

                // Submit the form to load preview
                previewForm.submit();

            } catch (error) {
                console.error('Error showing code preview:', error);
                showNotification('Failed to show code preview', 'error');
            }
        }

        // Update conversation display after sending messages
        function updateConversationDisplayInfo() {
            try {
                const item = document.querySelector(`.conversation-item[data-id="${currentConversationId}"]`);
                if (item) {
                    const dateElement = item.querySelector('.conversation-date');
                    if (dateElement) {
                        dateElement.textContent = 'Just now';
                    }

                    // Move to the top of the list if not already
                    if (item !== conversationsList.firstChild) {
                        conversationsList.insertBefore(item, conversationsList.firstChild);
                    }
                }

            } catch (error) {
                console.error('Error updating conversation display:', error);
                // No need to notify user for this non-critical error
            }
        }

        // Rename conversation modal
        function showRenameModal(conversationId, currentTitle) {
            try {
                const modal = document.getElementById('rename-modal');
                const titleInput = document.getElementById('new-title');

                if (titleInput) {
                    titleInput.value = currentTitle || '';
                }

                if (modal) {
                    modal.dataset.conversationId = conversationId;
                    modal.classList.add('active');

                    // Focus and select the input
                    setTimeout(() => {
                        if (titleInput) {
                            titleInput.focus();
                            titleInput.select();
                        }
                    }, 100);
                }
            } catch (error) {
                console.error('Error showing rename modal:', error);
                showNotification('Could not open the rename dialog', 'error');
            }
        }

        // Delete conversation modal
        function showDeleteModal(conversationId) {
            try {
                const modal = document.getElementById('delete-modal');
                if (modal) {
                    modal.dataset.conversationId = conversationId;
                    modal.classList.add('active');
                }
            } catch (error) {
                console.error('Error showing delete modal:', error);
                showNotification('Could not open the delete dialog', 'error');
            }
        }

        // Rename conversation
        async function renameConversation(conversationId, newTitle) {
            if (!newTitle.trim()) {
                showNotification('Title cannot be empty', 'error');
                return;
            }

            try {
                const response = await fetch('api/conversations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'rename',
                        conversation_id: conversationId,
                        title: newTitle
                    })
                });

                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse rename response:', responseText);
                    throw new Error('Invalid API response structure');
                }

                if (data.success) {
                    // Update conversation item in list
                    const item = document.querySelector(`.conversation-item[data-id="${conversationId}"]`);
                    if (item) {
                        const titleElement = item.querySelector('.conversation-title');
                        if (titleElement) {
                            titleElement.textContent = newTitle;
                        }
                    }

                    // Update current chat title if this is the active conversation
                    if (currentConversationId === conversationId) {
                        chatTitle.textContent = newTitle;
                    }

                    showNotification('Conversation renamed successfully', 'success');
                } else {
                    showNotification(data.message || 'Failed to rename conversation', 'error');
                }

            } catch (error) {
                console.error('Error renaming conversation:', error);
                showNotification('Failed to rename conversation: ' + error.message, 'error');
            }
        }

        // Delete conversation with proper reset to new chat state
        async function deleteConversation(conversationId) {
            try {
                // Remember if this is the current conversation
                const isCurrentConversation = String(currentConversationId) === String(conversationId);
                const currentConversationBackup = currentConversationId;

                // If it's the current conversation, immediately reset the UI state first
                if (isCurrentConversation) {
                    // Immediately reset conversation ID to null to prevent conflicts
                    currentConversationId = null;

                    // Clear the messages area and show a temporary loading indicator
                    messagesContainer.innerHTML = '<div class="loading-spinner"></div><div class="temp-message">Deleting conversation...</div>';

                    // Update UI elements
                    chatTitle.textContent = 'New Chat';
                    exportChatBtn.disabled = true;

                    // Remove active class from all conversations
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        item.classList.remove('active');
                    });
                }

                // Make API call to delete the conversation
                const response = await fetch('api/conversations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        conversation_id: conversationId
                    })
                });

                // Check for network errors or non-200 responses
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                // Parse the response carefully
                let data;
                const responseText = await response.text();
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse API response:', responseText);
                    throw new Error('Invalid API response structure');
                }

                // Check if the response has the expected structure
                if (typeof data !== 'object' || data === null) {
                    throw new Error('Invalid API response structure');
                }

                if (data.success) {
                    // Successfully deleted - update UI

                    // Remove from list
                    const item = document.querySelector(`.conversation-item[data-id="${conversationId}"]`);
                    if (item) {
                        conversationsList.removeChild(item);
                    }

                    // Check if we need to show empty state in the sidebar
                    if (conversationsList.children.length === 0) {
                        conversationsList.innerHTML = `
                            <div class="empty-state">
                                <p>No conversations yet. Start a new chat!</p>
                            </div>
                        `;
                    }

                    // If we deleted the current conversation, complete the UI reset
                    if (isCurrentConversation) {
                        // Force a complete reset to welcome screen
                        completeResetToWelcomeScreen();
                    }

                    showNotification('Conversation deleted successfully', 'success');
                } else {
                    // Handle API error response
                    const errorMessage = data.message || 'Unknown error occurred';
                    showNotification(errorMessage, 'error');

                    // If this was the current conversation and deletion failed,
                    // revert the UI changes and try to load again
                    if (isCurrentConversation) {
                        currentConversationId = currentConversationBackup;
                        try {
                            loadConversation(currentConversationId);
                        } catch (e) {
                            // If loading fails, just reset completely
                            completeResetToWelcomeScreen();
                        }
                    }
                }

            } catch (error) {
                console.error('Error deleting conversation:', error);

                // For API structure errors, perform more thorough recovery
                if (error.message.includes('API response structure')) {
                    showNotification('API response error. Resetting application state.', 'error');
                    completeResetToWelcomeScreen();

                    // Refresh the conversations list after a delay
                    setTimeout(() => {
                        refreshConversationsList();
                    }, 1000);
                } else {
                    showNotification('Failed to delete conversation: ' + error.message, 'error');
                }
            }
        }

        // Helper function to ensure a complete reset to welcome screen
        function completeResetToWelcomeScreen() {
            // Clear any existing conversation state
            currentConversationId = null;

            // Reset model to preferred
            modelSelect.value = "<?php echo $preferredModel; ?>";
            currentModel = modelSelect.value;

            // Completely clear messages container first
            messagesContainer.innerHTML = '';

            // Construct fresh welcome screen HTML
            const welcomeScreenHTML = `
                <div class="welcome-screen" id="welcome-screen">
                    <div class="welcome-logo">
                        <img src="assets/img/logo-large.svg" alt="<?php echo htmlspecialchars($GLOBALS['site_name']); ?>">
                    </div>
                    <h1>Welcome to <?php echo htmlspecialchars($GLOBALS['site_name']); ?></h1>
                    <p>Select a conversation from the sidebar or start a new chat.</p>
                    <?php if (empty($conversations)): ?>
                    <div class="suggestion-chips">
                        <button class="suggestion-chip" data-text="Tell me a fun fact">Tell me a fun fact</button>
                        <button class="suggestion-chip" data-text="How can you help me with coding?">Help with coding</button>
                        <button class="suggestion-chip" data-text="Write a short poem about AI">Write a poem</button>
                        <button class="suggestion-chip" data-text="Explain quantum computing simply">Explain quantum computing</button>
                    </div>
                    <?php endif; ?>
                </div>
            `;

            // Set the HTML directly
            messagesContainer.innerHTML = welcomeScreenHTML;

            // Reattach event listeners for suggestion chips
            messagesContainer.querySelectorAll('.suggestion-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    messageInput.value = chip.dataset.text;
                    sendMessage();
                });
            });

            // Update UI elements
            chatTitle.textContent = 'New Chat';
            exportChatBtn.disabled = true;

            // Remove active class from all conversations
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });

            // Reset scroll position
            scrollHelper.reset();
            scrollHelper.scrollToBottom(false);

            // Close sidebar on mobile
            if (window.innerWidth < 768) {
                sidebar.classList.remove('active');
            }
        }

        // Export current conversation
        async function exportConversation(format = 'json') {
            if (!currentConversationId) return;

            try {
                const response = await fetch(`api/conversations.php?action=export&id=${currentConversationId}&format=${format}`);
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse export response:', responseText);
                    throw new Error('Invalid API response structure');
                }

                if (data.success) {
                    // Create and trigger download
                    const blob = new Blob([data.data], { type: format === 'json' ? 'application/json' : 'text/plain' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    showNotification(data.message || 'Failed to export conversation', 'error');
                }

            } catch (error) {
                console.error('Error exporting conversation:', error);
                showNotification('Failed to export conversation: ' + error.message, 'error');
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            try {
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
                        if (notification.parentNode) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }, 3000);

            } catch (error) {
                console.error('Error showing notification:', error, message);
                // Fall back to console if notification fails
                console.log(`${type.toUpperCase()}: ${message}`);
            }
        }

        // Helper functions
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Auto-resize message input
        function resizeMessageInput() {
            try {
                // Limit to max 5 rows
                const maxHeight = 150; // Approximately 5 rows
                messageInput.style.height = 'auto';
                const newHeight = Math.min(messageInput.scrollHeight, maxHeight);
                messageInput.style.height = newHeight + 'px';

                // If we've reached max height, enable scrolling on the textarea
                messageInput.style.overflowY = messageInput.scrollHeight > maxHeight ? 'auto' : 'hidden';
            } catch (error) {
                console.error('Error resizing input:', error);
                // No need for user notification
            }
        }

        // Add error handler to recover from unexpected errors
        window.addEventListener('error', function(event) {
            console.error('Global error caught:', event.error);

            // Only handle errors after app is initialized
            if (appInitialized) {
                showNotification('An error occurred. Attempting to recover...', 'error');

                // Try to reset the app state for recovery
                setTimeout(() => {
                    try {
                        if (currentConversationId) {
                            // Try to reload the current conversation
                            loadConversation(currentConversationId);
                        } else {
                            // Reset to welcome screen
                            resetToNewChat();
                        }
                    } catch (e) {
                        console.error('Recovery failed:', e);
                        // Last resort - force reload the page
                        showNotification('Recovery failed. The page will reload.', 'error');
                        setTimeout(() => window.location.reload(), 2000);
                    }
                }, 1000);
            }
        });

        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
            try {
                // Initialize theme
                initTheme();

                // Initialize scroll helper
                scrollHelper.init();

                // Sidebar toggle
                openSidebarBtn.addEventListener('click', toggleSidebar);
                closeSidebarBtn.addEventListener('click', toggleSidebar);

                // New chat button
                newChatBtn.addEventListener('click', () => {
                    try {
                        // Reset to preferred model before creating new conversation
                        modelSelect.value = "<?php echo $preferredModel; ?>";
                        currentModel = modelSelect.value;

                        // Just reset the UI without creating a new conversation yet
                        resetToNewChat();
                    } catch (error) {
                        console.error('Error in new chat button handler:', error);
                        showNotification('Failed to start new chat', 'error');
                    }
                });

                // Theme toggle
                themeToggle.addEventListener('change', toggleTheme);

                // Send message
                sendButton.addEventListener('click', sendMessage);
                messageInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });

                // Initialize suggestion chips for the welcome screen
                document.querySelectorAll('#welcome-screen .suggestion-chip').forEach(chip => {
                    chip.addEventListener('click', () => {
                        messageInput.value = chip.dataset.text;
                        sendMessage();
                    });
                });

                // Auto-resize message input
                messageInput.addEventListener('input', resizeMessageInput);

                // Model select
                modelSelect.addEventListener('change', (e) => {
                    currentModel = e.target.value;
                });

                // Export button
                exportChatBtn.addEventListener('click', () => {
                    exportConversation('text');
                });

                // Modal actions
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('modal')) {
                        e.target.classList.remove('active');
                    }
                });

                closeModalButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const modal = btn.closest('.modal');
                        modal.classList.remove('active');
                    });
                });

                // Process rename form
                confirmRenameBtn.addEventListener('click', () => {
                    const conversationId = renameModal.dataset.conversationId;
                    const newTitle = newTitleInput.value.trim();
                    renameConversation(conversationId, newTitle);
                    renameModal.classList.remove('active');
                });

                cancelRenameBtn.addEventListener('click', () => {
                    renameModal.classList.remove('active');
                });

                // Allow Enter key in rename input
                newTitleInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        confirmRenameBtn.click();
                    }
                });

                // Process delete confirmation
                confirmDeleteBtn.addEventListener('click', () => {
                    const conversationId = deleteModal.dataset.conversationId;
                    deleteConversation(conversationId);
                    deleteModal.classList.remove('active');
                });

                cancelDeleteBtn.addEventListener('click', () => {
                    deleteModal.classList.remove('active');
                });

                // Add click handlers to existing conversation items
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.addEventListener('click', (e) => {
                        if (!e.target.closest('.rename-btn') && !e.target.closest('.delete-btn')) {
                            loadConversation(item.dataset.id);
                        }
                    });

                    const renameBtn = item.querySelector('.rename-btn');
                    if (renameBtn) {
                        renameBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            const title = item.querySelector('.conversation-title').textContent;
                            showRenameModal(item.dataset.id, title);
                        });
                    }

                    const deleteBtn = item.querySelector('.delete-btn');
                    if (deleteBtn) {
                        deleteBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            showDeleteModal(item.dataset.id);
                        });
                    }
                });

                // Handle window resize events
                window.addEventListener('resize', () => {
                    if (scrollHelper.shouldScrollToBottom) {
                        scrollHelper.scrollToBottom();
                    }
                });

                // Mark app as fully initialized after initial setup
                appInitialized = true;

                // Delete All Conversations Button
                deleteAllConversationsBtn.addEventListener('click', () => {
                    showDeleteAllModal();
                });

                // Delete All Modal actions
                confirmDeleteAllBtn.addEventListener('click', () => {
                    deleteAllConversations();
                    deleteAllModal.classList.remove('active');
                });

                cancelDeleteAllBtn.addEventListener('click', () => {
                    deleteAllModal.classList.remove('active');
                });

                 // Initialize the welcome screen and suggestion chips
                resetToNewChat();

            } catch (error) {
                console.error('Error during app initialization:', error);
                showNotification('Error initializing application. Please try refreshing the page.', 'error');
            }
        });

        // Mobile sidebar backdrop
        const sidebarBackdrop = document.getElementById('sidebar-backdrop');

        // Update toggleSidebar function to handle backdrop
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            if (window.innerWidth <= 768) {
                sidebarBackdrop.classList.toggle('active');
            }
        }

        // Add click listener to close sidebar when backdrop is clicked
        sidebarBackdrop.addEventListener('click', toggleSidebar);

        // Handle window resize to adjust sidebar visibility
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebarBackdrop.classList.remove('active');
            }
        });

        // Delete All Conversations Modal
        function showDeleteAllModal() {
            try {
                const modal = document.getElementById('delete-all-modal');
                if (modal) {
                    modal.classList.add('active');
                }
            } catch (error) {
                console.error('Error showing delete all modal:', error);
                showNotification('Could not open the delete all dialog', 'error');
            }
        }

        // Delete All Conversations
        async function deleteAllConversations() {
            try {
                // Get all conversation IDs
                const conversationIds = Array.from(document.querySelectorAll('.conversation-item'))
                    .map(item => item.dataset.id);

                if (conversationIds.length === 0) {
                    showNotification('No conversations to delete.', 'info');
                    return;
                }

                // Delete each conversation
                for (const conversationId of conversationIds) {
                    await deleteConversation(conversationId);
                }

                showNotification('All conversations deleted successfully.', 'success');

                 // Force a complete reset to welcome screen after deleting all conversations
                completeResetToWelcomeScreen();
                refreshConversationsList();

            } catch (error) {
                console.error('Error deleting all conversations:', error);
                showNotification('Failed to delete all conversations: ' + error.message, 'error');
            }
        }
    </script>
    <!-- Sidebar backdrop for mobile -->
    <div id="sidebar-backdrop" class="sidebar-backdrop"></div>
</body>
</html>