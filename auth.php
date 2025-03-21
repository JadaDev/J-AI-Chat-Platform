<?php
require_once 'config.php';

// Start or resume session securely
function secureSessionStart() {
    // Only configure session if it's not already active
    if (session_status() == PHP_SESSION_NONE) {
        $session_name = 'ai_chat_session';
        $secure = SECURE_COOKIE;
        $httponly = true;
        
        // Force session to use cookies only
        if (ini_set('session.use_only_cookies', 1) === FALSE) {
            error_log("Could not set session.use_only_cookies");
            // Continue anyway rather than dying completely
        }
        
        // Get session cookie parameters
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params(
            SESSION_TIMEOUT,
            $cookieParams["path"],
            $cookieParams["domain"],
            $secure,
            $httponly
        );
        
        session_name($session_name);
        session_start();
        
        // Regenerate session ID to prevent session fixation
        if (!isset($_SESSION['created'])) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > SESSION_TIMEOUT) {
            // Session is older than timeout, regenerate ID
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// Register a new user
function registerUser($username, $email, $password, $firstName = '', $lastName = '') {
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    // Check if registration is allowed
    if (!$GLOBALS['allow_registration']) {
        return ['success' => false, 'message' => 'New user registration is currently disabled'];
    }
    
    $conn = connectDB();
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $email, $hashedPassword, $firstName, $lastName);
    
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        
        // Create a default conversation for the user
        $model = $GLOBALS['default_model'];
        $createConvStmt = $conn->prepare("INSERT INTO conversations (user_id, title, model) VALUES (?, 'Welcome', ?)");
        $createConvStmt->bind_param("is", $userId, $model);
        $createConvStmt->execute();
        $convId = $conn->insert_id;
        
        // Add welcome message
        $welcomeMsg = $GLOBALS['welcome_message'];
        $msgStmt = $conn->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'assistant', ?)");
        $msgStmt->bind_param("is", $convId, $welcomeMsg);
        $msgStmt->execute();
        
        $createConvStmt->close();
        $msgStmt->close();
        $stmt->close();
        $conn->close();
        
        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Registration failed: ' . $error];
    }
}

// Login user
function loginUser($username, $password) {
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required'];
    }
    
    $conn = connectDB();
    
    // Get user by username or email
    $stmt = $conn->prepare("SELECT user_id, username, email, password, user_role, is_active, preferred_model FROM users WHERE (username = ? OR email = ?)");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if (!$user['is_active']) {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'message' => 'Account is inactive. Please contact support.'];
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Update last login time
            $updateStmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP, usage_count = usage_count + 1 WHERE user_id = ?");
            $updateStmt->bind_param("i", $user['user_id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Set session variables
            secureSessionStart();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_role'] = $user['user_role'];
            $_SESSION['preferred_model'] = $user['preferred_model'];
            
            $stmt->close();
            $conn->close();
            
            return [
                'success' => true, 
                'message' => 'Login successful',
                'user' => [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['user_role'],
                    'preferred_model' => $user['preferred_model']
                ]
            ];
        }
    }
    
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Invalid username or password'];
}

// Check if user is logged in
function isLoggedIn() {
    secureSessionStart();
    return isset($_SESSION['user_id']);
}

// Check if user is an admin
function isAdmin() {
    secureSessionStart();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Logout user
function logoutUser() {
    secureSessionStart();
    
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    return true;
}

// Request password reset
function requestPasswordReset($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Valid email is required'];
    }
    
    $conn = connectDB();
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists for security
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'If your email is registered, you will receive reset instructions'];
    }
    
    $user = $result->fetch_assoc();
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', time() + 604800); // 7 days
    
    // Save token to database
    $updateStmt = $conn->prepare("UPDATE users SET token_reset = ?, token_expiry = ? WHERE user_id = ?");
    $updateStmt->bind_param("ssi", $token, $tokenExpiry, $user['user_id']);
    $updateStmt->execute();
    
    // Send email with reset link
    $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
    $to = $email;
    $subject = $GLOBALS['site_name'] . " - Password Reset";
    
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <h2>Reset Your Password</h2>
        <p>Hello {$user['username']},</p>
        <p>You requested a password reset for your account. Click the link below to set a new password:</p>
        <p><a href='{$resetLink}'>Reset Password</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request this reset, please ignore this email.</p>
        <p>Regards,<br>{$GLOBALS['site_name']} Team</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    mail($to, $subject, $message, $headers);
    
    $updateStmt->close();
    $stmt->close();
    $conn->close();
    
    return ['success' => true, 'message' => 'If your email is registered, you will receive reset instructions'];
}

// Reset password with token
function resetPassword($token, $newPassword) {
    if (empty($token) || empty($newPassword)) {
        return ['success' => false, 'message' => 'Invalid request'];
    }
    
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
    }
    
    $conn = connectDB();
    
    // Check if token exists and is valid
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE token_reset = ? AND token_expiry > CURRENT_TIMESTAMP");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Invalid or expired token'];
    }
    
    $user = $result->fetch_assoc();
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password and clear token
    $updateStmt = $conn->prepare("UPDATE users SET password = ?, token_reset = NULL, token_expiry = NULL WHERE user_id = ?");
    $updateStmt->bind_param("si", $hashedPassword, $user['user_id']);
    $updateStmt->execute();
    
    $updateStmt->close();
    $stmt->close();
    $conn->close();
    
    return ['success' => true, 'message' => 'Password has been reset successfully'];
}

// Update user profile
function updateProfile($userId, $data) {
    $conn = connectDB();
    
    // Initialize query parts
    $updates = [];
    $types = "";
    $values = [];
    
    // Build dynamic query based on provided data
    if (isset($data['first_name'])) {
        $updates[] = "first_name = ?";
        $types .= "s";
        $values[] = $data['first_name'];
    }
    
    if (isset($data['last_name'])) {
        $updates[] = "last_name = ?";
        $types .= "s";
        $values[] = $data['last_name'];
    }
    
    if (isset($data['email'])) {
        // Check if email is valid
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $conn->close();
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $checkStmt->bind_param("si", $data['email'], $userId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $checkStmt->close();
            $conn->close();
            return ['success' => false, 'message' => 'Email already in use'];
        }
        $checkStmt->close();
        
        $updates[] = "email = ?";
        $types .= "s";
        $values[] = $data['email'];
    }
    
    if (isset($data['preferred_model'])) {
        $updates[] = "preferred_model = ?";
        $types .= "s";
        $values[] = $data['preferred_model'];
    }
    
    if (isset($data['new_password']) && !empty($data['new_password'])) {
        // Verify current password
        if (!isset($data['current_password']) || empty($data['current_password'])) {
            $conn->close();
            return ['success' => false, 'message' => 'Current password is required to set a new password'];
        }
        
        // Get current password hash
        $pwdStmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $pwdStmt->bind_param("i", $userId);
        $pwdStmt->execute();
        $result = $pwdStmt->get_result();
        
        if ($result->num_rows === 0) {
            $pwdStmt->close();
            $conn->close();
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $user = $result->fetch_assoc();
        $pwdStmt->close();
        
        // Verify current password
        if (!password_verify($data['current_password'], $user['password'])) {
            $conn->close();
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Check new password strength
        if (strlen($data['new_password']) < 8) {
            $conn->close();
            return ['success' => false, 'message' => 'New password must be at least 8 characters long'];
        }
        
        // Hash new password
        $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $updates[] = "password = ?";
        $types .= "s";
        $values[] = $hashedPassword;
    }
    
    if (isset($data['profile_picture']) && $data['profile_picture']['error'] === 0) {
        // Process profile picture upload
        $targetDir = AVATAR_PATH . "/";
        $fileExtension = pathinfo($data['profile_picture']['name'], PATHINFO_EXTENSION);
        $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $fileExtension;
        $targetFile = $targetDir . $newFileName;
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($fileExtension), $allowedTypes)) {
            $conn->close();
            return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed'];
        }
        
        if ($data['profile_picture']['size'] > 5000000) {
            $conn->close();
            return ['success' => false, 'message' => 'File is too large (max 5MB)'];
        }
        
        if (move_uploaded_file($data['profile_picture']['tmp_name'], $targetFile)) {
            $updates[] = "profile_picture = ?";
            $types .= "s";
            $values[] = $newFileName;
        } else {
            $conn->close();
            return ['success' => false, 'message' => 'Failed to upload profile picture'];
        }
    }
    
    if (empty($updates)) {
        $conn->close();
        return ['success' => false, 'message' => 'No changes to update'];
    }
    
    // Add user_id to the parameter list
    $types .= "i";
    $values[] = $userId;
    
    // Prepare and execute update query
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        // Update session if needed
        if (isset($data['email']) || isset($data['preferred_model'])) {
            secureSessionStart();
            
            if (isset($data['email'])) {
                $_SESSION['email'] = $data['email'];
            }
            
            if (isset($data['preferred_model'])) {
                $_SESSION['preferred_model'] = $data['preferred_model'];
            }
        }
        
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => 'Profile updated successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Failed to update profile: ' . $error];
    }
}
?>