<?php
require_once 'config.php';
require_once 'auth.php';

// Create a new conversation
function createConversation($userId, $title = 'New Conversation', $model = null) {
    if (!$model) {
        $model = $GLOBALS['default_model'];
    }

    $conn = connectDB();
    $stmt = $conn->prepare("INSERT INTO conversations (user_id, title, model) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $title, $model);

    if ($stmt->execute()) {
        $conversationId = $conn->insert_id;


        if ($GLOBALS['welcome_message_enabled']) {
            // Add welcome message to new conversation
            $welcomeMsg = $GLOBALS['welcome_message'];
            $msgStmt = $conn->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'assistant', ?)");
            $msgStmt->bind_param("is", $conversationId, $welcomeMsg);
            $msgStmt->execute();
            $msgStmt->close();
        }

        $stmt->close();
        $conn->close();

        return [
            'success' => true,
            'conversation_id' => $conversationId,
            'title' => $title,
            'model' => $model
        ];
    }

    $stmt->close();
    $conn->close();

    return ['success' => false, 'message' => 'Failed to create conversation'];
}

// Get user conversations
function getUserConversations($userId) {
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT c.*, 
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id) as message_count,
            (SELECT content FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at ASC LIMIT 1) as first_message
        FROM conversations c 
        WHERE user_id = ? AND is_archived = FALSE 
        ORDER BY updated_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        // Generate a preview from the first message
        $preview = "";
        if (!empty($row['first_message'])) {
            $preview = substr($row['first_message'], 0, 100) . (strlen($row['first_message']) > 100 ? '...' : '');
        }
        
        $conversations[] = [
            'id' => $row['conversation_id'],
            'title' => $row['title'],
            'model' => $row['model'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'message_count' => $row['message_count'],
            'preview' => $preview
        ];
    }
    
    $stmt->close();
    $conn->close();
    return $conversations;
}

// Get conversation messages
function getConversationMessages($conversationId, $userId) {
    $conn = connectDB();
    
    // First check if conversation belongs to the user
    $checkStmt = $conn->prepare("SELECT user_id FROM conversations WHERE conversation_id = ?");
    $checkStmt->bind_param("i", $conversationId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Conversation not found'];
    }
    
    $conversation = $result->fetch_assoc();
    if ($conversation['user_id'] !== $userId) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $checkStmt->close();
    
    // Fetch messages
    $stmt = $conn->prepare("
        SELECT message_id, role, content, created_at, tokens_used 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['message_id'],
            'role' => $row['role'],
            'content' => $row['content'],
            'created_at' => $row['created_at'],
            'tokens_used' => $row['tokens_used']
        ];
    }
    
    // Get conversation details
    $detailsStmt = $conn->prepare("
        SELECT title, model, created_at, updated_at
        FROM conversations
        WHERE conversation_id = ?
    ");
    $detailsStmt->bind_param("i", $conversationId);
    $detailsStmt->execute();
    $detailsResult = $detailsStmt->get_result();
    $details = $detailsResult->fetch_assoc();
    
    $stmt->close();
    $detailsStmt->close();
    $conn->close();
    
    return [
        'success' => true,
        'conversation' => [
            'id' => $conversationId,
            'title' => $details['title'],
            'model' => $details['model'],
            'created_at' => $details['created_at'],
            'updated_at' => $details['updated_at']
        ],
        'messages' => $messages
    ];
}

// Send message to AI and store response
function sendMessage($userId, $conversationId, $content) {
    if (empty($content)) {
        return ['success' => false, 'message' => 'Message content is required'];
    }
    
    $conn = connectDB();
    
    // Check if conversation exists and belongs to user
    $checkStmt = $conn->prepare("SELECT model FROM conversations WHERE conversation_id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $conversationId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Conversation not found or access denied'];
    }
    
    $conversation = $result->fetch_assoc();
    $model = $conversation['model'];
    $checkStmt->close();
    
    // Insert user message
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)");
    $stmt->bind_param("is", $conversationId, $content);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Failed to save message: ' . $error];
    }
    
    $userMessageId = $conn->insert_id;
    $stmt->close();
    
    // Update conversation timestamp
    $updateStmt = $conn->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?");
    $updateStmt->bind_param("i", $conversationId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Get previous messages for context (limit to last 10)
    $contextStmt = $conn->prepare("
        SELECT role, content 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $contextStmt->bind_param("i", $conversationId);
    $contextStmt->execute();
    $contextResult = $contextStmt->get_result();
    
    $context = [];
    while ($row = $contextResult->fetch_assoc()) {
        array_unshift($context, [
            'role' => $row['role'],
            'parts' => [['text' => $row['content']]]
        ]);
    }
    $contextStmt->close();
    
    // Make API request to AI model
    try {
        $apiKey = getAPIKey();
        
        // Construct request body
        $requestBody = [
            'contents' => $context,
            'safety_settings' => [
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ],
            'generation_config' => [
                'temperature' => 0.7,
                'top_k' => 40,
                'top_p' => 0.95,
                'max_output_tokens' => $GLOBALS['max_tokens']
            ]
        ];
        
        $ch = curl_init("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('API request failed with status code ' . $httpCode);
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            if (isset($responseData['promptFeedback']['blockReason'])) {
                $aiResponse = "I apologize, but I cannot provide a response due to content safety restrictions. Please rephrase your query.";
            } else {
                throw new Exception('Invalid API response structure');
            }
        } else {
            $aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];
        }
        
        // Calculate tokens (approximate)
        $tokensUsed = (int)(mb_strlen($content) / 4) + (int)(mb_strlen($aiResponse) / 4);
        
        // Insert AI response
        $respStmt = $conn->prepare("INSERT INTO messages (conversation_id, role, content, tokens_used) VALUES (?, 'assistant', ?, ?)");
        $respStmt->bind_param("isi", $conversationId, $aiResponse, $tokensUsed);
        
        if (!$respStmt->execute()) {
            throw new Exception('Failed to save AI response: ' . $respStmt->error);
        }
        
        $aiMessageId = $conn->insert_id;
        $respStmt->close();
        
        // Update usage statistics
        $date = date('Y-m-d');
        
        $statsStmt = $conn->prepare("
            INSERT INTO usage_statistics (user_id, date, model_used, tokens_used, request_count)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                tokens_used = tokens_used + VALUES(tokens_used),
                request_count = request_count + 1
        ");
        $statsStmt->bind_param("issi", $userId, $date, $model, $tokensUsed);
        $statsStmt->execute();
        $statsStmt->close();
        
        // Update user usage count
        $userUpdateStmt = $conn->prepare("UPDATE users SET usage_count = usage_count + 1 WHERE user_id = ?");
        $userUpdateStmt->bind_param("i", $userId);
        $userUpdateStmt->execute();
        $userUpdateStmt->close();
        
        $conn->close();
        
        return [
            'success' => true,
            'user_message' => [
                'id' => $userMessageId,
                'content' => $content,
                'role' => 'user',
                'created_at' => date('Y-m-d H:i:s')
            ],
            'ai_message' => [
                'id' => $aiMessageId,
                'content' => $aiResponse,
                'role' => 'assistant',
                'created_at' => date('Y-m-d H:i:s'),
                'tokens_used' => $tokensUsed
            ]
        ];
        
    } catch (Exception $e) {
        $conn->close();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Update conversation title
function updateConversationTitle($userId, $conversationId, $title) {
    if (empty($title)) {
        return ['success' => false, 'message' => 'Title cannot be empty'];
    }
    
    $conn = connectDB();
    
    // Check if conversation belongs to user
    $checkStmt = $conn->prepare("SELECT user_id FROM conversations WHERE conversation_id = ?");
    $checkStmt->bind_param("i", $conversationId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Conversation not found'];
    }
    
    $conversation = $result->fetch_assoc();
    if ($conversation['user_id'] !== $userId) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $checkStmt->close();
    
    // Update title
    $stmt = $conn->prepare("UPDATE conversations SET title = ? WHERE conversation_id = ?");
    $stmt->bind_param("si", $title, $conversationId);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'Title updated successfully'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to update title: ' . $error];
}

// Delete conversation (or archive it)
function deleteConversation($userId, $conversationId, $permanently = false) {
    $conn = connectDB();
    
    // Check if conversation belongs to user
    $checkStmt = $conn->prepare("SELECT user_id FROM conversations WHERE conversation_id = ?");
    $checkStmt->bind_param("i", $conversationId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Conversation not found'];
    }
    
    $conversation = $result->fetch_assoc();
    if ($conversation['user_id'] !== $userId && !isAdmin()) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $checkStmt->close();
    
    if ($permanently) {
        // Delete conversation permanently
        $stmt = $conn->prepare("DELETE FROM conversations WHERE conversation_id = ?");
    } else {
        // Archive conversation
        $stmt = $conn->prepare("UPDATE conversations SET is_archived = TRUE WHERE conversation_id = ?");
    }
    
    $stmt->bind_param("i", $conversationId);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => $permanently ? 'Conversation deleted permanently' : 'Conversation archived'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to delete conversation: ' . $error];
}

// Get archived conversations
function getArchivedConversations($userId) {
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT c.*, 
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id) as message_count,
            (SELECT content FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at ASC LIMIT 1) as first_message
        FROM conversations c 
        WHERE user_id = ? AND is_archived = TRUE 
        ORDER BY updated_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        // Generate a preview from the first message
        $preview = "";
        if (!empty($row['first_message'])) {
            $preview = substr($row['first_message'], 0, 100) . (strlen($row['first_message']) > 100 ? '...' : '');
        }
        
        $conversations[] = [
            'id' => $row['conversation_id'],
            'title' => $row['title'],
            'model' => $row['model'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'message_count' => $row['message_count'],
            'preview' => $preview
        ];
    }
    
    $stmt->close();
    $conn->close();
    return $conversations;
}

// Restore archived conversation
function restoreConversation($userId, $conversationId) {
    $conn = connectDB();
    
    // Check if conversation belongs to user
    $checkStmt = $conn->prepare("SELECT user_id, is_archived FROM conversations WHERE conversation_id = ?");
    $checkStmt->bind_param("i", $conversationId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Conversation not found'];
    }
    
    $conversation = $result->fetch_assoc();
    if ($conversation['user_id'] !== $userId) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    if ($conversation['is_archived'] === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Conversation is not archived'];
    }
    
    $checkStmt->close();
    
    // Restore conversation
    $stmt = $conn->prepare("UPDATE conversations SET is_archived = FALSE WHERE conversation_id = ?");
    $stmt->bind_param("i", $conversationId);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'Conversation restored successfully'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to restore conversation: ' . $error];
}

// Export conversation as JSON or plain text
function exportConversation($userId, $conversationId, $format = 'json') {
    $conn = connectDB();
    
    // Check if conversation belongs to user
    $checkStmt = $conn->prepare("SELECT title, model, user_id FROM conversations WHERE conversation_id = ?");
    $checkStmt->bind_param("i", $conversationId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Conversation not found'];
    }
    
    $conversation = $result->fetch_assoc();
    if ($conversation['user_id'] !== $userId) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $checkStmt->close();
    
    // Get messages
    $msgStmt = $conn->prepare("SELECT role, content, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $msgStmt->bind_param("i", $conversationId);
    $msgStmt->execute();
    $result = $msgStmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'role' => $row['role'],
            'content' => $row['content'],
            'timestamp' => $row['created_at']
        ];
    }
    
    $msgStmt->close();
    $conn->close();
    
    // Format the export
    if ($format === 'json') {
        $export = [
            'title' => $conversation['title'],
            'model' => $conversation['model'],
            'exported_at' => date('Y-m-d H:i:s'),
            'messages' => $messages
        ];
        
        return [
            'success' => true,
            'format' => 'json',
            'filename' => 'conversation_' . $conversationId . '_' . date('Ymd') . '.json',
            'data' => json_encode($export, JSON_PRETTY_PRINT)
        ];
    } else { // Format as plain text
        $textContent = "Title: " . $conversation['title'] . "\n";
        $textContent .= "Model: " . $conversation['model'] . "\n";
        $textContent .= "Exported: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($messages as $message) {
            $role = $message['role'] === 'assistant' ? 'AI' : 'You';
            $textContent .= "[" . date('Y-m-d H:i:s', strtotime($message['timestamp'])) . "] " . $role . ":\n";
            $textContent .= $message['content'] . "\n\n";
        }
        
        return [
            'success' => true,
            'format' => 'text',
            'filename' => 'conversation_' . $conversationId . '_' . date('Ymd') . '.txt',
            'data' => $textContent
        ];
    }
}