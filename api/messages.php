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

// Handle POST requests (send message)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'send') {
        // Send message to AI
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $content = $data['content'] ?? '';
        
        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
            exit;
        }
        
        if (empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Message content is required']);
            exit;
        }
        
        $result = sendMessage($userId, $conversationId, $content);
        echo json_encode($result);
        exit;
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

// Handle unsupported methods
echo json_encode(['success' => false, 'message' => 'Unsupported method']);
exit;