<?php
/**
 * SelamatRide SmartSchoolBus
 * Configuration File - Production Grade
 * 
 * @version 2.0
 */

// Define app started constant
define('APP_STARTED', true);

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartschoolbus_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Timezone Configuration
date_default_timezone_set('Asia/Kuala_Lumpur');

// Site Configuration
define('SITE_URL', 'http://localhost/SmartSchoolBus');
define('SITE_NAME', 'SelamatRide SmartSchoolBus');
define('SYSTEM_VERSION', '2.0.0');

// Security Configuration
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);

// Pagination
define('RECORDS_PER_PAGE', 20);

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch(PDOException $e) {
    // Log error without exposing details
    error_log("Database connection failed: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

// Load Authentication Middleware
require_once __DIR__ . '/includes/auth_middleware.php';
$auth = new AuthMiddleware($pdo);

// Helper Functions for backward compatibility
function isLoggedIn() {
    global $auth;
    return $auth->isAuthenticated();
}

function requireLogin() {
    global $auth;
    $auth->requireAuth();
}

function requireRole($allowed_roles) {
    global $auth;
    $auth->requireRole($allowed_roles);
}

function sanitize($data) {
    global $auth;
    return $auth->sanitize($data);
}

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function getCurrentUser() {
    global $auth;
    return $auth->getCurrentUser();
}

function logActivity($action, $entity_type, $entity_id = null, $details = []) {
    global $auth;
    $auth->logActivity($action, $entity_type, $entity_id, $details);
}

function csrfToken() {
    global $auth;
    return $auth->generateCSRFToken();
}

function csrfField() {
    $token = csrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}
