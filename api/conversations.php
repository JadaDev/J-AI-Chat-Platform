<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';
require_once '../chat_handler.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Handle GET requests (get conversation, export)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get') {
        // Get conversation messages
        $conversationId = (int)($_GET['id'] ?? 0);

        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
            exit;
        }

        $result = getConversationMessages($conversationId, $userId);
        echo json_encode($result);
        exit;
    } elseif ($action === 'list') {
        // Get user conversations list
        $conversations = getUserConversations($userId);
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        exit;
    } elseif ($action === 'archived') {
        // Get archived conversations
        $conversations = getArchivedConversations($userId);
        echo json_encode(['success' => true, 'archived_conversations' => $conversations]);
        exit;
    } elseif ($action === 'export') {
        // Export conversation
        $conversationId = (int)($_GET['id'] ?? 0);
        $format = $_GET['format'] ?? 'json';

        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
            exit;
        }

        if ($format !== 'json' && $format !== 'text') {
            $format = 'json';
        }

        $result = exportConversation($userId, $conversationId, $format);
        echo json_encode($result);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

// Handle POST requests (create, rename, delete, restore, delete_permanent, delete_all_permanent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'create') {
        // Create new conversation
        $title = $data['title'] ?? 'New Chat';
        $model = $data['model'] ?? $_SESSION['preferred_model'] ?? $GLOBALS['default_model'];

        $result = createConversation($userId, $title, $model);
        echo json_encode($result);
        exit;
    } elseif ($action === 'rename') {
        // Rename conversation
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $title = $data['title'] ?? '';

        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
            exit;
        }

        $result = updateConversationTitle($userId, $conversationId, $title);
        echo json_encode($result);
        exit;
    } elseif ($action === 'delete') {
        // Delete conversation (archive it)
        $conversationId = (int)($data['conversation_id'] ?? 0);

        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
            exit;
        }

        $result = deleteConversation($userId, $conversationId);
        echo json_encode($result);
        exit;
    } elseif ($action === 'restore') {
        // Restore archived conversation
        $conversationId = (int)($data['conversation_id'] ?? 0);

        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
            exit;
        }

        $result = restoreConversation($userId, $conversationId);
        echo json_encode($result);
        exit;
    } elseif ($action === 'delete_permanent') {
        // Delete conversation permanently
        $conversationId = (int)($data['conversation_id'] ?? 0);

        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
            exit;
        }

        $result = deleteConversation($userId, $conversationId, true);
        echo json_encode($result);
        exit;
    } elseif ($action === 'delete_all_permanent') {
        // Delete ALL conversations permanently
        $conversation_ids = $data['conversation_ids'] ?? [];

        // Validate that conversation_ids is an array
        if (!is_array($conversation_ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation IDs.']);
            exit;
        }

        $all_successful = true; // Flag to track if all deletions were successful

        // Loop through each conversation ID and delete it
        foreach ($conversation_ids as $conversation_id) {
            $conversation_id = (int)$conversation_id; // Sanitize the ID

            if ($conversation_id <= 0) {
                $all_successful = false; // Set flag to false if invalid ID found
                continue; // Skip to the next ID
            }

            // Call the deleteConversation function with the 'permanent' flag set to true
            $result = deleteConversation($userId, $conversation_id, true);

            if (!$result['success']) {
                $all_successful = false; // Set flag to false if deletion failed
            }
        }

        // Return a single success/failure message based on all deletions
        if ($all_successful) {
            echo json_encode(['success' => true, 'message' => 'All conversations deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'One or more conversations could not be deleted.']);
        }

        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

// Handle unsupported methods
echo json_encode(['success' => false, 'message' => 'Unsupported method']);
exit;
