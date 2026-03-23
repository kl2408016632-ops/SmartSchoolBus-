<?php
/**
 * SelamatRide SmartSchoolBus - Delete User
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']);

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId === 0) {
    header('Location: ' . SITE_URL . '/admin/users.php?error=Invalid user ID');
    exit;
}

// Prevent deleting yourself
if ($userId === $_SESSION['user_id']) {
    header('Location: ' . SITE_URL . '/admin/users.php?error=You cannot delete your own account');
    exit;
}

// Fetch user to verify it exists
try {
    $stmt = $pdo->prepare("SELECT user_id, full_name, username FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: ' . SITE_URL . '/admin/users.php?error=User not found');
        exit;
    }
} catch (Exception $e) {
    error_log("Fetch User Error: " . $e->getMessage());
    header('Location: ' . SITE_URL . '/admin/users.php?error=Failed to load user');
    exit;
}

// Delete user
try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete user (related records will be handled by foreign key constraints)
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Log activity
    logActivity('delete', 'user', $userId, [
        'username' => $user['username'],
        'full_name' => $user['full_name']
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Redirect with success message
    header('Location: ' . SITE_URL . '/admin/users.php?success=deleted');
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Delete User Error: " . $e->getMessage());
    
    // Check if error is due to foreign key constraint
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $errorMsg = 'Cannot delete user because they have associated records. Please reassign or delete related data first.';
    } else {
        $errorMsg = 'Failed to delete user. Please try again.';
    }
    
    header('Location: ' . SITE_URL . '/admin/users.php?error=' . urlencode($errorMsg));
    exit;
}
