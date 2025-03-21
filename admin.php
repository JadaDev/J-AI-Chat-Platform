<?php
require_once 'config.php';
require_once 'auth.php';

// Get all users (with pagination)
function getUsers($page = 1, $limit = 20, $search = '', $status = '', $role = '') {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $offset = ($page - 1) * $limit;
    $conn = connectDB();
    
    // Build the WHERE clause based on search and filters
    $whereClause = [];
    $whereParams = [];
    $paramTypes = "";
    
    if (!empty($search)) {
        $whereClause[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchTerm = "%$search%";
        $whereParams[] = $searchTerm;
        $whereParams[] = $searchTerm;
        $whereParams[] = $searchTerm;
        $whereParams[] = $searchTerm;
        $paramTypes .= "ssss";
    }
    
    if ($status === 'active') {
        $whereClause[] = "u.is_active = 1";
    } elseif ($status === 'inactive') {
        $whereClause[] = "u.is_active = 0";
    }
    
    if ($role === 'admin') {
        $whereClause[] = "u.user_role = 'admin'";
    } elseif ($role === 'user') {
        $whereClause[] = "u.user_role = 'user'";
    }
    
    $whereSQL = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";
    
    // Get total count with filters
    $countSQL = "SELECT COUNT(*) as total FROM users u $whereSQL";
    $countStmt = $conn->prepare($countSQL);
    
    if (!empty($whereParams)) {
        $countStmt->bind_param($paramTypes, ...$whereParams);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalUsers = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Build the query for paginated users
    $sql = "
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
            u.usage_count,
            (SELECT COUNT(*) FROM conversations WHERE user_id = u.user_id) as conversation_count,
            (SELECT SUM(tokens_used) FROM usage_statistics WHERE user_id = u.user_id) as total_tokens
        FROM users u
        $whereSQL
        ORDER BY u.created_at DESC
        LIMIT ?, ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Add all parameters (search, filters, and pagination)
    if (!empty($whereParams)) {
        $whereParams[] = $offset;
        $whereParams[] = $limit;
        $stmt->bind_param($paramTypes . "ii", ...$whereParams);
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['user_id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'profile_picture' => $row['profile_picture'],
            'role' => $row['user_role'],
            'preferred_model' => $row['preferred_model'],
            'created_at' => $row['created_at'],
            'last_login' => $row['last_login'],
            'is_active' => $row['is_active'] ? true : false,
            'usage_count' => $row['usage_count'],
            'conversation_count' => $row['conversation_count'],
            'total_tokens' => $row['total_tokens']
        ];
    }
    
    $totalPages = ceil($totalUsers / $limit);
    $stmt->close();
    $conn->close();
    
    return [
        'users' => $users,
        'pagination' => [
            'total_users' => $totalUsers,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'limit' => $limit
        ]
    ];
}

// Update user status (activate/deactivate)
function updateUserStatus($userId, $isActive) {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $conn = connectDB();
    
    // Check if the user exists
    $checkStmt = $conn->prepare("SELECT user_id, user_role FROM users WHERE user_id = ?");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $user = $result->fetch_assoc();
    
    // Prevent deactivating your own admin account
    if ($user['user_id'] == $_SESSION['user_id'] && $user['user_role'] == 'admin') {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Cannot deactivate your own admin account'];
    }
    
    $checkStmt->close();
    
    // Update user status
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
    $active = $isActive ? 1 : 0;
    $stmt->bind_param("ii", $active, $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'User status updated successfully'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to update user status: ' . $error];
}

// Change user role
function changeUserRole($userId, $newRole) {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    if ($newRole !== 'user' && $newRole !== 'admin') {
        return ['success' => false, 'message' => 'Invalid role'];
    }
    
    $conn = connectDB();
    
    // Check if the user exists
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Prevent changing your own role
    if ($userId == $_SESSION['user_id']) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Cannot change your own role'];
    }
    
    $checkStmt->close();
    
    // Update user role
    $stmt = $conn->prepare("UPDATE users SET user_role = ? WHERE user_id = ?");
    $stmt->bind_param("si", $newRole, $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'User role updated successfully'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to update user role: ' . $error];
}

// Get system statistics
function getSystemStatistics() {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $conn = connectDB();
    
    // Total users
    $userStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_users_7d,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_users_30d,
            SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS active_users_24h,
            SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS active_users_7d
        FROM users
    ");
    
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userStats = $userResult->fetch_assoc();
    $userStmt->close();
    
    // Conversations and messages stats
    $convStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_conversations,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_conversations_7d
        FROM conversations
    ");
    
    $convStmt->execute();
    $convResult = $convStmt->get_result();
    $convStats = $convResult->fetch_assoc();
    $convStmt->close();
    
    $msgStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_messages,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_messages_7d,
            SUM(tokens_used) AS total_tokens
        FROM messages
    ");
    
    $msgStmt->execute();
    $msgResult = $msgStmt->get_result();
    $msgStats = $msgResult->fetch_assoc();
    $msgStmt->close();
    
    // Model usage statistics
    $modelStmt = $conn->prepare("
        SELECT
            model,
            COUNT(*) AS conversation_count,
            (SELECT SUM(tokens_used) FROM messages m JOIN conversations c ON m.conversation_id = c.conversation_id WHERE c.model = conversations.model) AS tokens_used
        FROM conversations
        GROUP BY model
    ");
    
    $modelStmt->execute();
    $modelResult = $modelStmt->get_result();
    $modelStats = [];
    
    while ($row = $modelResult->fetch_assoc()) {
        $modelStats[] = [
            'model' => $row['model'],
            'conversation_count' => $row['conversation_count'],
            'tokens_used' => $row['tokens_used']
        ];
    }
    
    $modelStmt->close();
    
    // Daily usage for the past 30 days
    $dailyStmt = $conn->prepare("
        SELECT
            date,
            SUM(tokens_used) AS tokens_used,
            SUM(request_count) AS requests
        FROM usage_statistics
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY date
        ORDER BY date ASC
    ");
    
    $dailyStmt->execute();
    $dailyResult = $dailyStmt->get_result();
    $dailyStats = [];
    
    while ($row = $dailyResult->fetch_assoc()) {
        $dailyStats[] = [
            'date' => $row['date'],
            'tokens_used' => $row['tokens_used'],
            'requests' => $row['requests']
        ];
    }
    
    $dailyStmt->close();
    
    // API Keys count
    $keyStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_keys,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_keys
        FROM api_keys
    ");
    
    $keyStmt->execute();
    $keyResult = $keyStmt->get_result();
    $keyStats = $keyResult->fetch_assoc();
    $keyStmt->close();
    
    $conn->close();
    
    return [
        'success' => true,
        'users' => $userStats,
        'conversations' => $convStats,
        'messages' => $msgStats,
        'model_usage' => $modelStats,
        'daily_usage' => $dailyStats,
        'api_keys' => $keyStats
    ];
}

// Manage system settings
function updateSystemSettings($settings) {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $conn = connectDB();
    $updated = 0;
    $errors = [];
    $adminId = $_SESSION['user_id'];
    
    // Update each setting
    foreach ($settings as $name => $value) {
        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_name, setting_value, updated_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
        ");
        
        $stmt->bind_param("ssi", $name, $value, $adminId);
        
        if ($stmt->execute()) {
            $updated++;
            // Update global variables
            if ($name === 'site_name') {
                $GLOBALS['site_name'] = $value;
            } elseif ($name === 'default_model') {
                $GLOBALS['default_model'] = $value;
            } elseif ($name === 'max_tokens') {
                $GLOBALS['max_tokens'] = (int)$value;
            } elseif ($name === 'allow_registration') {
                $GLOBALS['allow_registration'] = $value === 'true';
            } elseif ($name === 'welcome_message') {
                $GLOBALS['welcome_message'] = $value;
            }
        } else {
            $errors[] = "Failed to update {$name}: " . $stmt->error;
        }
        
        $stmt->close();
    }
    
    $conn->close();
    
    if (empty($errors)) {
        return ['success' => true, 'message' => "{$updated} settings updated successfully"];
    } else {
        return [
            'success' => $updated > 0,
            'message' => $updated . " settings updated with " . count($errors) . " errors",
            'errors' => $errors
        ];
    }
}

// Manage API keys
function getAPIKeys() {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT
            k.*,
            u.username AS created_by_username
        FROM api_keys k
        LEFT JOIN users u ON k.created_by = u.user_id
        ORDER BY k.created_at DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $apiKeys = [];
    
    while ($row = $result->fetch_assoc()) {
        $apiKeys[] = [
            'id' => $row['key_id'],
            'service' => $row['service'],
            'api_key' => $row['api_key'],
            'is_active' => $row['is_active'] ? true : false,
            'created_at' => $row['created_at'],
            'created_by' => $row['created_by'],
            'created_by_username' => $row['created_by_username'],
            'usage_count' => $row['usage_count']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    return ['success' => true, 'api_keys' => $apiKeys];
}

// Add a new API key
function addAPIKey($service, $apiKey) {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    if (empty($service) || empty($apiKey)) {
        return ['success' => false, 'message' => 'Service and API key are required'];
    }
    
    $conn = connectDB();
    $adminId = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO api_keys (service, api_key, is_active, created_by) VALUES (?, ?, 1, ?)");
    $stmt->bind_param("ssi", $service, $apiKey, $adminId);
    
    if ($stmt->execute()) {
        $keyId = $conn->insert_id;
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'API key added successfully', 'key_id' => $keyId];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to add API key: ' . $error];
}

// Update API key status
function updateAPIKeyStatus($keyId, $isActive) {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE api_keys SET is_active = ? WHERE key_id = ?");
    $active = $isActive ? 1 : 0;
    $stmt->bind_param("ii", $active, $keyId);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'API key status updated successfully'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to update API key: ' . $error];
}

// Delete API key
function deleteAPIKey($keyId) {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Access denied'];
    }
    
    $conn = connectDB();
    $stmt = $conn->prepare("DELETE FROM api_keys WHERE key_id = ?");
    $stmt->bind_param("i", $keyId);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'API key deleted successfully'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to delete API key: ' . $error];
}
?>