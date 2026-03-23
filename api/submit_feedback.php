<?php
/**
 * Submit Feedback API Endpoint
 * Handles AJAX feedback submission
 */
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// CSRF validation
$postedToken = $_POST['csrf_token'] ?? '';
$feedbackToken = $_SESSION['feedback_csrf'] ?? '';
$defaultToken = $_SESSION['csrf_token'] ?? '';

if (empty($postedToken) || ($postedToken !== $feedbackToken && $postedToken !== $defaultToken)) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

// Get and validate rating
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
if ($rating === null || $rating < 0 || $rating > 10) {
    echo json_encode(['success' => false, 'message' => 'Please select a rating from 0 to 10']);
    exit;
}

$feedbackMessage = trim($_POST['message'] ?? '');
$userRole = $_SESSION['role_name'] ?? null;
$sessionDuration = null;

if (isset($_SESSION['login_time'])) {
    $sessionDuration = time() - $_SESSION['login_time'];
}

try {
    // Get user IP address
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    // Get user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Insert feedback into database (anonymous - no user_id stored for privacy)
    $stmt = $pdo->prepare("
        INSERT INTO logout_feedback 
        (user_id, user_role, rating, message, ip_address, user_agent, session_duration, created_at) 
        VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userRole,
        $rating,
        !empty($feedbackMessage) ? $feedbackMessage : null,
        $ipAddress,
        $userAgent,
        $sessionDuration
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Thank you for your feedback!'
    ]);
    
} catch (Exception $e) {
    error_log("Feedback Submission Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while saving your feedback'
    ]);
}
