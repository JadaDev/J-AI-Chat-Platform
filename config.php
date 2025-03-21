<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'J_AI_Chat');
define('DB_USER', 'root');  // Change to your database user
define('DB_PASS', '');      // Change to your database password

// Application configuration
define('SITE_URL', 'http://localhost/chat');  // Change to your site URL
define('APP_NAME', 'J-AI Chat Platform');
define('APP_VERSION', '1.0.0');

// Security settings
define('SECURE_COOKIE', false);  // Set to true if using HTTPS
define('SESSION_TIMEOUT', 604800); // 7 days in seconds

// File paths
define('ROOT_PATH', dirname(__FILE__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('AVATAR_PATH', UPLOAD_PATH . '/avatars');

// API Configuration
define('DEFAULT_MODEL', 'gemini-2.0-flash');
define('DEFAULT_API_KEY', ''); // Replace with your API key

// Ensure upload directories exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(AVATAR_PATH)) {
    mkdir(AVATAR_PATH, 0755, true);
}

// Database connection function
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Get a system setting
function getSetting($settingName, $default = null) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = ?");
    $stmt->bind_param("s", $settingName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row['setting_value'];
    }
    
    $stmt->close();
    $conn->close();
    return $default;
}

// Initialize application settings
$GLOBALS['site_name'] = getSetting('site_name', APP_NAME);
$GLOBALS['default_model'] = getSetting('default_model', DEFAULT_MODEL);
$GLOBALS['max_tokens'] = (int)getSetting('max_tokens', 20480);
$GLOBALS['allow_registration'] = getSetting('allow_registration', 'true') === 'true';
$GLOBALS['welcome_message'] = getSetting('welcome_message', 'Hello! How can I assist you today?');
$GLOBALS['welcome_message_enabled'] = getSetting('welcome_message_enabled', 'true') === 'true';

// Get API key (with fallback)
function getAPIKey() {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT api_key FROM api_keys WHERE service = 'google_ai' AND is_active = TRUE ORDER BY usage_count ASC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $apiKey = $row['api_key'];
        
        // Update usage count
        $updateStmt = $conn->prepare("UPDATE api_keys SET usage_count = usage_count + 1 WHERE api_key = ?");
        $updateStmt->bind_param("s", $apiKey);
        $updateStmt->execute();
        $updateStmt->close();
        
        $stmt->close();
        $conn->close();
        return $apiKey;
    }
    
    $stmt->close();
    $conn->close();
    return DEFAULT_API_KEY;
}