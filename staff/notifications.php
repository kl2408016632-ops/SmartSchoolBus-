<?php
/**
 * Staff Notifications View Page
 * Shows all notifications sent by the logged-in staff member
 */
session_start();
require_once '../config.php';
require_once '../includes/auth_middleware.php';

// Require staff role
requireRole(['staff']);

$userId = $_SESSION['user_id'];

// Pagination
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE sender_id = ?");
$countStmt->execute([$userId]);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Get notifications
$stmt = $pdo->prepare("
    SELECT 
        notification_id,
        title,
        message,
            COALESCE(NULLIF(related_entity, ''), type) AS category,
        is_read,
        created_at
    FROM notifications
    WHERE sender_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $itemsPerPage, $offset]);
$notifications = $stmt->fetchAll();

$pageTitle = "My Notifications";
$currentPage = "notifications";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --primary: #3b82f6;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --border: #334155;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 24px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #2d3b4e;
        }

        .stats-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .stats-card h3 {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-card .count {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
        }

        .notifications-grid {
            display: grid;
            gap: 16px;
        }

        .notification-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
        }

        .notification-card:hover {
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .notification-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .category-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .category-badge.rfid {
            background: rgba(249, 115, 22, 0.2);
            color: #fb923c;
        }

        .category-badge.bus {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .category-badge.student {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .category-badge.system {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .category-badge.other {
            background: rgba(148, 163, 184, 0.2);
            color: #94a3b8;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.read {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .status-badge.unread {
            background: rgba(148, 163, 184, 0.2);
            color: #94a3b8;
        }

        .notification-message {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-top: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        .pagination .active {
            background: var(--primary);
            border-color: var(--primary);
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-bell"></i> My Notifications</h1>
                <p style="color: var(--text-secondary); margin-top: 4px;">Track all your submitted issues and complaints</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="notifications_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Issue Report
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-card">
            <h3><i class="fas fa-chart-bar"></i> Statistics</h3>
            <div class="count"><?= number_format($totalItems) ?></div>
            <p style="color: var(--text-secondary); margin-top: 4px;">Total notifications submitted</p>
        </div>

        <!-- Notifications List -->
        <div class="notifications-grid">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3 style="margin-bottom: 8px;">No Notifications Yet</h3>
                    <p>You haven't submitted any issue reports</p>
                    <a href="notifications_create.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Submit Your First Report
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card">
                        <div class="notification-header">
                            <div style="flex: 1;">
                                <div class="notification-title">
                                    <?= htmlspecialchars($notification['title']) ?>
                                </div>
                                <div class="notification-meta">
                                    <span class="category-badge <?= strtolower($notification['category']) ?>">
                                        <?= htmlspecialchars($notification['category']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?= date('M d, Y', strtotime($notification['created_at'])) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= date('h:i A', strtotime($notification['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            <span class="status-badge <?= $notification['is_read'] ? 'read' : 'unread' ?>">
                                <i class="fas fa-<?= $notification['is_read'] ? 'check-circle' : 'clock' ?>"></i>
                                <?= $notification['is_read'] ? 'Reviewed by Admin' : 'Pending Review' ?>
                            </span>
                        </div>
                        <div class="notification-message">
                            <?= nl2br(htmlspecialchars($notification['message'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?= $page - 1 ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?= $totalPages ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
