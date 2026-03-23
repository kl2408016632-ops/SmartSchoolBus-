<?php
session_start();
require_once '../config.php';
require_once '../includes/auth_middleware.php';

// Require admin role
requireRole(['admin']);

// Pagination settings
$itemsPerPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(n.title LIKE ? OR n.message LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($categoryFilter)) {
    $where[] = "COALESCE(NULLIF(n.related_entity, ''), n.type) = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter === 'read') {
    $where[] = "n.is_read = 1";
} elseif ($statusFilter === 'unread') {
    $where[] = "n.is_read = 0";
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count
FROM notifications";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get total count for pagination (with filters)
$countQuery = "SELECT COUNT(*) FROM notifications n 
               JOIN users u ON n.sender_id = u.user_id 
               {$whereClause}";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Get notifications
$query = "SELECT 
    n.*,
    COALESCE(NULLIF(n.related_entity, ''), n.type) AS category,
    u.full_name as sender_name,
    u.username as sender_username,
    u.email as sender_email
FROM notifications n
JOIN users u ON n.sender_id = u.user_id
{$whereClause}
ORDER BY n.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);
$params[] = $itemsPerPage;
$params[] = $offset;
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid request token";
        header('Location: notifications.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $notificationId = (int)($_POST['notification_id'] ?? 0);

    if ($notificationId > 0) {
        try {
            switch ($action) {
                case 'mark_read':
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?");
                    $stmt->execute([$notificationId]);
                    $_SESSION['success'] = "Notification marked as read";
                    break;

                case 'mark_unread':
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 0, read_at = NULL WHERE notification_id = ?");
                    $stmt->execute([$notificationId]);
                    $_SESSION['success'] = "Notification marked as unread";
                    break;

                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE notification_id = ?");
                    $stmt->execute([$notificationId]);
                    $_SESSION['success'] = "Notification deleted successfully";
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }

    header('Location: notifications.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Staff Notifications";
$currentPage = "notifications";
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
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <?php if (isset($_SESSION['success'])): ?>
        <div style="background: var(--success); color: white; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: var(--danger); color: white; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card">
            <div class="stat-card-content">
                <div class="stat-card-info">
                    <p class="stat-card-label">Total Notifications</p>
                    <h3 class="stat-card-value"><?= number_format($stats['total']) ?></h3>
                </div>
                <div class="stat-card-icon" style="background: rgba(59, 130, 246, 0.1);">
                    <i class="fas fa-bell" style="color: #3b82f6;"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-content">
                <div class="stat-card-info">
                    <p class="stat-card-label">Unread</p>
                    <h3 class="stat-card-value" style="color: var(--danger);"><?= number_format($stats['unread']) ?></h3>
                </div>
                <div class="stat-card-icon" style="background: rgba(239, 68, 68, 0.1);">
                    <i class="fas fa-exclamation-circle" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-content">
                <div class="stat-card-info">
                    <p class="stat-card-label">Read</p>
                    <h3 class="stat-card-value" style="color: var(--success);"><?= number_format($stats['read_count']) ?></h3>
                </div>
                <div class="stat-card-icon" style="background: rgba(34, 197, 94, 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="content-card" style="margin-bottom: 24px;">
        <form method="GET" action="notifications.php" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 14px;">
                    <i class="fas fa-search"></i> Search
                </label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search by title, message, or sender..." 
                       style="width: 100%; padding: 10px 12px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
            </div>

            <div>
                <label style="display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 14px;">
                    <i class="fas fa-tag"></i> Category
                </label>
                <select name="category" style="padding: 10px 12px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); min-width: 150px;">
                    <option value="">All Categories</option>
                    <option value="RFID" <?= $categoryFilter === 'RFID' ? 'selected' : '' ?>>RFID</option>
                    <option value="Bus" <?= $categoryFilter === 'Bus' ? 'selected' : '' ?>>Bus</option>
                    <option value="Student" <?= $categoryFilter === 'Student' ? 'selected' : '' ?>>Student</option>
                    <option value="System" <?= $categoryFilter === 'System' ? 'selected' : '' ?>>System</option>
                    <option value="Other" <?= $categoryFilter === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div>
                <label style="display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 14px;">
                    <i class="fas fa-filter"></i> Status
                </label>
                <select name="status" style="padding: 10px 12px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); min-width: 150px;">
                    <option value="">All Status</option>
                    <option value="unread" <?= $statusFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="height: 42px;">
                <i class="fas fa-search"></i> Filter
            </button>

            <?php if (!empty($search) || !empty($categoryFilter) || !empty($statusFilter)): ?>
                <a href="notifications.php" class="btn" style="height: 42px; background: var(--danger); display: inline-flex; align-items: center;">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Notifications Table -->
    <div class="table-container">
        <?php if (empty($notifications)): ?>
            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <i class="fas fa-inbox" style="font-size: 64px; margin-bottom: 16px; opacity: 0.3;"></i>
                <p style="font-size: 18px; margin-bottom: 8px;">No notifications found</p>
                <p style="font-size: 14px;">
                    <?php if (!empty($search) || !empty($categoryFilter) || !empty($statusFilter)): ?>
                        Try adjusting your filters
                    <?php else: ?>
                        Staff notifications will appear here
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Title & Message</th>
                        <th style="width: 100px;">Category</th>
                        <th style="width: 140px;">From</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 130px;">Date</th>
                        <th style="width: 80px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr style="<?= !$notification['is_read'] ? 'background: rgba(59, 130, 246, 0.03);' : '' ?>">
                            <td style="text-align: center;">
                                <?php if (!$notification['is_read']): ?>
                                    <div style="width: 8px; height: 8px; background: var(--danger); border-radius: 50%;"></div>
                                <?php else: ?>
                                    <i class="fas fa-check" style="color: var(--success); font-size: 11px;"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: <?= !$notification['is_read'] ? '600' : '500' ?>; color: var(--text-primary); margin-bottom: 4px;">
                                    <?= htmlspecialchars($notification['title']) ?>
                                </div>
                                <div style="font-size: 13px; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 400px;">
                                    <?= htmlspecialchars(substr($notification['message'], 0, 80)) ?><?= strlen($notification['message']) > 80 ? '...' : '' ?>
                                </div>
                            </td>
                            <td>
                                <span class="category-badge <?= strtolower($notification['category']) ?>">
                                    <?= htmlspecialchars($notification['category']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 14px; color: var(--text-primary);">
                                    <?= htmlspecialchars($notification['sender_name']) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($notification['is_read']): ?>
                                    <span style="color: var(--success); font-size: 12px;">
                                        <i class="fas fa-check-circle"></i> Read
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--danger); font-size: 12px; font-weight: 500;">
                                        Unread
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 13px; color: var(--text-secondary);">
                                    <?= date('d M Y', strtotime($notification['created_at'])) ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary); opacity: 0.7;">
                                    <?= date('H:i', strtotime($notification['created_at'])) ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div class="dropdown">
                                    <button class="dropdown-toggle" onclick="toggleDropdown(event, <?= $notification['notification_id'] ?>)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div id="dropdown-<?= $notification['notification_id'] ?>" class="dropdown-menu">
                                        <a href="view_notification.php?id=<?= $notification['notification_id'] ?>">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?= $notification['notification_id'] ?>">
                                                <button type="submit">
                                                    <i class="fas fa-check"></i> Mark as Read
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="mark_unread">
                                                <input type="hidden" name="notification_id" value="<?= $notification['notification_id'] ?>">
                                                <button type="submit">
                                                    <i class="fas fa-envelope"></i> Mark as Unread
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this notification?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="notification_id" value="<?= $notification['notification_id'] ?>">
                                            <button type="submit" class="delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                    <?php
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $baseUrl = 'notifications.php?' . http_build_query($queryParams) . ($queryParams ? '&' : '');
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?= $baseUrl ?>page=1" class="btn" style="padding: 8px 12px;">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="btn" style="padding: 8px 12px;">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="<?= $baseUrl ?>page=<?= $i ?>" 
                           class="btn" 
                           style="padding: 8px 12px; <?= $i === $page ? 'background: var(--primary-color); color: white;' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="btn" style="padding: 8px 12px;">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="btn" style="padding: 8px 12px;">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; margin-top: 12px; color: var(--text-secondary); font-size: 14px;">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $itemsPerPage, $totalItems) ?> of <?= number_format($totalItems) ?> notifications
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <style>
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-top: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--content-bg);
        }

        thead th {
            padding: 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: var(--content-bg);
        }

        tbody td {
            padding: 16px;
            font-size: 14px;
            color: var(--text-primary);
            position: relative;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: 8px 12px;
            background: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-primary);
        }

        .dropdown-toggle:hover {
            background: var(--border-color);
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 4px);
            right: 0;
            min-width: 180px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.4);
            display: none;
            z-index: 9999;
            max-height: 300px;
        }

        .dropdown-menu.active {
            display: block;
        }

        .dropdown-menu a {
            display: flex !important;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: var(--text-primary) !important;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
            border-bottom: 1px solid var(--border-color);
        }

        .dropdown-menu a:first-child {
            border-radius: 8px 8px 0 0;
        }

        .dropdown-menu a:last-child,
        .dropdown-menu form:last-child button {
            border-bottom: none;
            border-radius: 0 0 8px 8px;
        }

        .dropdown-menu a:hover,
        .dropdown-menu button:hover {
            background: var(--content-bg);
        }

        .dropdown-menu form {
            margin: 0;
            display: block;
        }

        .dropdown-menu button {
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 14px;
            display: flex !important;
            align-items: center;
            gap: 10px;
            color: var(--text-primary) !important;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }

        .dropdown-menu button.delete {
            color: var(--danger) !important;
            border-bottom: none;
        }

        .dropdown-menu button i {
            width: 16px;
            text-align: center;
        }
    </style>
</main>

<script>
function toggleDropdown(event, id) {
    event.stopPropagation();
    
    // Close all dropdowns
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('active');
    });
    
    // Toggle current
    const menu = document.getElementById('dropdown-' + id);
    menu.classList.toggle('active');
}

function confirmAction(action) {
    return confirm('Are you sure you want to ' + action + '?');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('active');
    });
});
</script>

<?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
