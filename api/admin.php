<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';
require_once '../admin.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required or insufficient permissions']);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'updateUserStatus') {
        // Update user status
        $userId = (int)($data['user_id'] ?? 0);
        $isActive = $data['is_active'] ?? false;
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        $result = updateUserStatus($userId, $isActive);
        echo json_encode($result);
        exit;
    }
    elseif ($action === 'changeUserRole') {
        // Change user role
        $userId = (int)($data['user_id'] ?? 0);
        $newRole = $data['role'] ?? '';
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        if ($newRole !== 'user' && $newRole !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }
        
        $result = changeUserRole($userId, $newRole);
        echo json_encode($result);
        exit;
    }
    elseif ($action === 'addUser') {
        // Add new user
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $role = $data['role'] ?? 'user';
        $isActive = $data['is_active'] ?? true;
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username, email, and password are required']);
            exit;
        }
        
        // Register the user
        $result = registerUser($username, $email, $password, $firstName, $lastName);
        
        if ($result['success']) {
            $userId = $result['user_id'];
            
            // Set role if not user
            if ($role !== 'user') {
                changeUserRole($userId, $role);
            }
            
            // Set status if not active
            if (!$isActive) {
                updateUserStatus($userId, false);
            }
            
            echo json_encode(['success' => true, 'message' => 'User added successfully', 'user_id' => $userId]);
        } else {
            echo json_encode($result);
        }
        
        exit;
    }
    elseif ($action === 'updateUser') {
        // Update existing user
        $userId = (int)($data['user_id'] ?? 0);
        $userData = [
            'username' => $data['username'] ?? '',
            'email' => $data['email'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'role' => $data['role'] ?? 'user',
            'is_active' => $data['is_active'] ?? true
        ];
        
        // Add password if provided
        if (!empty($data['password'])) {
            $userData['password'] = $data['password'];
        }
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        // Update user
        $conn = connectDB();
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update basic user info
            $fields = [];
            $values = [];
            $types = '';
            
            if (!empty($userData['username'])) {
                // Check if username is unique
                $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                $checkStmt->bind_param("si", $userData['username'], $userId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    throw new Exception('Username already in use');
                }
                
                $fields[] = "username = ?";
                $values[] = $userData['username'];
                $types .= "s";
            }
            
            if (!empty($userData['email'])) {
                // Check if email is unique
                $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $checkStmt->bind_param("si", $userData['email'], $userId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    throw new Exception('Email already in use');
                }
                
                $fields[] = "email = ?";
                $values[] = $userData['email'];
                $types .= "s";
            }
            
            if (isset($userData['first_name'])) {
                $fields[] = "first_name = ?";
                $values[] = $userData['first_name'];
                $types .= "s";
            }
            
            if (isset($userData['last_name'])) {
                $fields[] = "last_name = ?";
                $values[] = $userData['last_name'];
                $types .= "s";
            }
            
            if (!empty($userData['password'])) {
                $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
                $fields[] = "password = ?";
                $values[] = $hashedPassword;
                $types .= "s";
            }
            
            if (!empty($fields)) {
                $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE user_id = ?";
                $types .= "i";
                $values[] = $userId;
                
                $updateStmt = $conn->prepare($sql);
                $updateStmt->bind_param($types, ...$values);
                $updateStmt->execute();
            }
            
            // Update role if needed
            if (isset($userData['role']) && $userData['role'] !== '') {
                $roleStmt = $conn->prepare("UPDATE users SET user_role = ? WHERE user_id = ?");
                $roleStmt->bind_param("si", $userData['role'], $userId);
                $roleStmt->execute();
            }
            
            // Update active status if needed
            if (isset($userData['is_active'])) {
                $active = $userData['is_active'] ? 1 : 0;
                $activeStmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                $activeStmt->bind_param("ii", $active, $userId);
                $activeStmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        $conn->close();
        exit;
    }
    elseif ($action === 'updateSettings') {
        // Update system settings
        $settings = $data['settings'] ?? [];
        
        if (empty($settings)) {
            echo json_encode(['success' => false, 'message' => 'No settings provided']);
            exit;
        }
        
        $result = updateSystemSettings($settings);
        echo json_encode($result);
        exit;
    }
    elseif ($action === 'addAPIKey') {
        // Add new API key
        $service = $data['service'] ?? '';
        $apiKey = $data['api_key'] ?? '';
        
        if (empty($service) || empty($apiKey)) {
            echo json_encode(['success' => false, 'message' => 'Service and API key are required']);
            exit;
        }
        
        $result = addAPIKey($service, $apiKey);
        echo json_encode($result);
        exit;
    }
    elseif ($action === 'updateAPIKeyStatus') {
        // Update API key status
        $keyId = (int)($data['key_id'] ?? 0);
        $isActive = $data['is_active'] ?? false;
        
        if ($keyId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid API key ID']);
            exit;
        }
        
        $result = updateAPIKeyStatus($keyId, $isActive);
        echo json_encode($result);
        exit;
    }
    elseif ($action === 'deleteAPIKey') {
        // Delete API key
        $keyId = (int)($data['key_id'] ?? 0);
        
        if ($keyId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid API key ID']);
            exit;
        }
        
        $result = deleteAPIKey($keyId);
        echo json_encode($result);
        exit;
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'getStats') {
        // Get system statistics
        $stats = getSystemStatistics();
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }
    elseif ($action === 'getUsers') {
        // Get users with pagination
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $role = $_GET['role'] ?? '';
        
        // Apply search and filters
        $conn = connectDB();
        
        // Build query conditions
        $conditions = [];
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $searchTerm = "%$search%";
            $conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ssss";
        }
        
        if ($status === 'active') {
            $conditions[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $conditions[] = "is_active = 0";
        }
        
        if ($role === 'admin') {
            $conditions[] = "user_role = 'admin'";
        } elseif ($role === 'user') {
            $conditions[] = "user_role = 'user'";
        }
        
        // Combine conditions
        $whereClause = empty($conditions) ? "" : "WHERE " . implode(" AND ", $conditions);
        
        // Count total results
        $countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
        
        if (!empty($params)) {
            $countStmt = $conn->prepare($countQuery);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalUsers = $countResult->fetch_assoc()['total'];
            $countStmt->close();
        } else {
            $countResult = $conn->query($countQuery);
            $totalUsers = $countResult->fetch_assoc()['total'];
        }
        
        // Calculate pagination
        $totalPages = ceil($totalUsers / $limit);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;
        
        // Get paginated users
        $query = "
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
            $whereClause
            ORDER BY u.created_at DESC
            LIMIT ?, ?
        ";
        
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => $row['user_id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
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
        
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'total' => $totalUsers,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'role' => $role
            ]
        ]);
        
        exit;
    }
    elseif ($action === 'getUser') {
        // Get specific user details
        $userId = (int)($_GET['id'] ?? 0);
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        $conn = connectDB();
        $stmt = $conn->prepare("
            SELECT 
                user_id, 
                username, 
                email, 
                first_name, 
                last_name, 
                profile_picture, 
                user_role, 
                preferred_model, 
                created_at, 
                last_login, 
                is_active,
                usage_count
            FROM users 
            WHERE user_id = ?
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Get additional user stats
        $statsStmt = $conn->prepare("
            SELECT 
                SUM(tokens_used) as total_tokens,
                SUM(request_count) as total_requests
            FROM usage_statistics 
            WHERE user_id = ?
        ");
        
        $statsStmt->bind_param("i", $userId);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $stats = $statsResult->fetch_assoc();
        
        $convStmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_conversations,
                SUM(CASE WHEN is_archived = TRUE THEN 1 ELSE 0 END) as archived_conversations
            FROM conversations 
            WHERE user_id = ?
        ");
        
        $convStmt->bind_param("i", $userId);
        $convStmt->execute();
        $convResult = $convStmt->get_result();
        $convStats = $convResult->fetch_assoc();
        
        $userData = [
            'id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'profile_picture' => $user['profile_picture'],
            'role' => $user['user_role'],
            'preferred_model' => $user['preferred_model'],
            'created_at' => $user['created_at'],
            'last_login' => $user['last_login'],
            'is_active' => $user['is_active'] ? true : false,
            'usage_count' => $user['usage_count'],
            'stats' => [
                'total_tokens' => $stats['total_tokens'] ?? 0,
                'total_requests' => $stats['total_requests'] ?? 0,
                'total_conversations' => $convStats['total_conversations'] ?? 0,
                'archived_conversations' => $convStats['archived_conversations'] ?? 0
            ]
        ];
        
        $stmt->close();
        $statsStmt->close();
        $convStmt->close();
        $conn->close();
        
        echo json_encode(['success' => true, 'user' => $userData]);
        exit;
    }
    elseif ($action === 'getAPIKeys') {
        // Get API keys
        $result = getAPIKeys();
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
?>