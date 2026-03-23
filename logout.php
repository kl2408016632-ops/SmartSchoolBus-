<?php
/**
 * SelamatRide SmartSchoolBus - Secure Logout Handler
 * Production-Grade Session Termination
 */
require_once 'config.php';

// Get current user info before destroying session
$userData = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role_name'] ?? null,
    'full_name' => $_SESSION['full_name'] ?? null,
    'login_time' => $_SESSION['login_time'] ?? null
];

// Destroy the current role-specific session using auth middleware
$auth->destroySession();

// Redirect to login
header('Location: ' . SITE_URL . '/index.php');
exit;

