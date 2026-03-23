<?php
/**
 * SelamatRide SmartSchoolBus - View Notification Details
 * Admin View Single Staff Notification
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']); // Only admin can view notifications

$pageTitle = "Notification Details";
$currentPage = "notifications";

$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$notification = null;

// Fetch notification details
if ($notificationId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                n.notification_id,
                n.title,
                n.message,
                COALESCE(NULLIF(n.related_entity, ''), n.type) AS category,
                n.is_read,
                n.created_at,
                n.read_at,
                u.user_id as sender_id,
                u.full_name as sender_name,
                u.username as sender_username,
                u.email as sender_email,
                r.role_name as sender_role
            FROM notifications n
            JOIN users u ON n.sender_id = u.user_id
            JOIN roles r ON u.role_id = r.role_id
            WHERE n.notification_id = ?
        ");
        $stmt->execute([$notificationId]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            header('Location: ' . SITE_URL . '/admin/notifications.php?error=notfound');
            exit;
        }

        // Auto-mark as read if unread
        if (!$notification['is_read']) {
            $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?");
            $updateStmt->execute([$notificationId]);
            $notification['is_read'] = 1;
            $notification['read_at'] = date('Y-m-d H:i:s');
        }

    } catch (Exception $e) {
        error_log("Notification Fetch Error: " . $e->getMessage());
        header('Location: ' . SITE_URL . '/admin/notifications.php?error=database');
        exit;
    }
} else {
    header('Location: ' . SITE_URL . '/admin/notifications.php?error=invalid');
    exit;
}

// Handle actions (delete, mark unread)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'mark_unread') {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 0, read_at = NULL WHERE notification_id = ?");
                $stmt->execute([$notificationId]);
                header('Location: ' . SITE_URL . '/admin/notifications.php?success=marked_unread');
                exit;
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE notification_id = ?");
                $stmt->execute([$notificationId]);
                header('Location: ' . SITE_URL . '/admin/notifications.php?success=deleted');
                exit;
            }
        } catch (Exception $e) {
            error_log("Notification Action Error: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to perform action.';
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Format Malaysia time
date_default_timezone_set('Asia/Kuala_Lumpur');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php include 'includes/admin_styles.php'; ?>
    
    <style>
        .notification-container {
            max-width: 900px;
        }

        .notification-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .notification-header {
            padding: 32px;
            border-bottom: 1px solid var(--border-color);
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .category-badge {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .category-badge.rfid {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
            border: 1px solid rgba(251, 146, 60, 0.3);
        }

        .category-badge.bus {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .category-badge.student {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .category-badge.system {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .category-badge.other {
            background: rgba(107, 114, 128, 0.2);
            color: #9ca3af;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.unread {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-badge.read {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .notification-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .notification-timestamp {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .notification-timestamp i {
            color: var(--primary-color);
        }

        .notification-body {
            padding: 32px;
            border-bottom: 1px solid var(--border-color);
        }

        .message-content {
            font-size: 15px;
            line-height: 1.8;
            color: var(--text-primary);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .sender-info {
            padding: 32px;
            background: var(--content-bg);
        }

        .sender-info h3 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .sender-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .detail-value {
            font-size: 15px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .detail-value i {
            margin-right: 8px;
            color: var(--primary-color);
        }

        .action-bar {
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-group {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--content-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        @media (max-width: 768px) {
            .notification-header,
            .notification-body,
            .sender-info,
            .action-bar {
                padding: 20px;
            }

            .action-bar {
                flex-direction: column;
            }

            .btn-group {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .sender-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-envelope-open"></i> Notification Details</h1>
                <p>View full issue report from staff member</p>
            </div>
        </div>

        <div class="notification-container">
            <div class="notification-card">
                <!-- Notification Header -->
                <div class="notification-header">
                    <div class="notification-meta">
                        <span class="category-badge <?= strtolower($notification['category']) ?>">
                            <?= htmlspecialchars($notification['category']) ?>
                        </span>
                        <span class="status-badge <?= $notification['is_read'] ? 'read' : 'unread' ?>">
                            <?= $notification['is_read'] ? '<i class="fas fa-check"></i> Read' : '<i class="fas fa-exclamation"></i> Unread' ?>
                        </span>
                    </div>
                    <h1 class="notification-title">
                        <?= htmlspecialchars($notification['title']) ?>
                    </h1>
                    <div class="notification-timestamp">
                        <i class="fas fa-clock"></i>
                        <span>
                            <?= date('l, d F Y \a\t H:i', strtotime($notification['created_at'])) ?> (Malaysia Time)
                        </span>
                    </div>
                </div>

                <!-- Notification Body -->
                <div class="notification-body">
                    <div class="message-content">
                        <?= nl2br(htmlspecialchars($notification['message'])) ?>
                    </div>
                </div>

                <!-- Sender Information -->
                <div class="sender-info">
                    <h3><i class="fas fa-user"></i> Reported By</h3>
                    <div class="sender-details">
                        <div class="detail-item">
                            <span class="detail-label">Staff Name</span>
                            <span class="detail-value">
                                <i class="fas fa-user-tie"></i>
                                <?= htmlspecialchars($notification['sender_name']) ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Username</span>
                            <span class="detail-value">
                                <i class="fas fa-at"></i>
                                <?= htmlspecialchars($notification['sender_username']) ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($notification['sender_email']) ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Role</span>
                            <span class="detail-value">
                                <i class="fas fa-id-badge"></i>
                                <?= ucfirst(htmlspecialchars($notification['sender_role'])) ?>
                            </span>
                        </div>
                        <?php if ($notification['is_read'] && $notification['read_at']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Marked as Read</span>
                                <span class="detail-value">
                                    <i class="fas fa-check-circle"></i>
                                    <?= date('d M Y, H:i', strtotime($notification['read_at'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <span class="detail-label">Notification ID</span>
                            <span class="detail-value">
                                <i class="fas fa-hashtag"></i>
                                <?= $notification['notification_id'] ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <a href="<?= SITE_URL ?>/admin/notifications.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Notifications
                    </a>
                    <div class="btn-group">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="mark_unread">
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Mark as Unread
                            </button>
                        </form>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this notification? This action cannot be undone.')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
