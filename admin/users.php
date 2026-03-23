<?php
/**
 * SelamatRide SmartSchoolBus - User Management
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']);

$pageTitle = "User Management";
$currentPage = "users";

// Handle success/error messages
$message = '';
$messageType = '';

if (isset($_GET['success'])) {
    $messageType = 'success';
    switch ($_GET['success']) {
        case 'created':
            $message = 'User created successfully!';
            break;
        case 'updated':
            $message = 'User updated successfully!';
            break;
        case 'deleted':
            $message = 'User deleted successfully!';
            break;
    }
}

if (isset($_GET['error'])) {
    $messageType = 'error';
    $message = htmlspecialchars($_GET['error']);
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = "WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR r.role_name LIKE ?";
    $searchParam = "%{$search}%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Count total records
try {
    $countSql = "SELECT COUNT(*) as total FROM users u JOIN roles r ON u.role_id = r.role_id {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $perPage);
} catch (Exception $e) {
    error_log("Count Error: " . $e->getMessage());
    $totalRecords = 0;
    $totalPages = 0;
}

// Fetch users with pagination
try {
    $sql = "
        SELECT 
            u.*,
            r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        {$whereClause}
        ORDER BY u.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("User Management Error: " . $e->getMessage());
    $users = [];
}
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
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .search-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .search-input {
            flex: 1;
            max-width: 400px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
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
        }

        .user-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            object-fit: cover;
        }

        .user-avatar.has-image {
            background: none;
            border: 2px solid var(--border-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .role-badge.admin {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .role-badge.staff {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .role-badge.driver {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
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

        .btn-search {
            background: var(--content-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-search:hover {
            background: var(--border-color);
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-toggle:hover {
            background: var(--border-color);
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 4px);
            right: 0;
            min-width: 150px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
        }

        .dropdown-menu.active {
            display: block;
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }

        .dropdown-menu a:hover {
            background: var(--content-bg);
        }

        .dropdown-menu a.delete {
            color: var(--danger);
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid var(--border-color);
        }

        .pagination-info {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .pagination-buttons {
            display: flex;
            gap: 8px;
        }

        .page-btn {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .page-btn:hover:not(:disabled) {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-edit {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
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
                <h1>User Management</h1>
                <p>Manage system users, roles, and permissions</p>
            </div>
            <a href="<?= SITE_URL ?>/admin/create_user.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New User
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <form method="GET" action="" class="search-bar">
            <input 
                type="text" 
                name="search" 
                class="search-input" 
                placeholder="Search by name, username, email, or role..." 
                value="<?= htmlspecialchars($search) ?>"
            >
            <button type="submit" class="btn btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search): ?>
                <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-search">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- Users Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Avatar</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 48px; color: var(--text-secondary);">
                                <?= $search ? 'No users found matching your search.' : 'No users found' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rowNumber = $offset + 1;
                        foreach ($users as $user): ?>
                            <tr>
                                <td><strong>#<?= $rowNumber++ ?></strong></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($user['avatar_url']) && file_exists(__DIR__ . '/../' . $user['avatar_url'])): ?>
                                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($user['avatar_url']) ?>?v=<?= time() ?>" 
                                             class="user-avatar has-image" 
                                             alt="<?= htmlspecialchars($user['full_name']) ?>">
                                    <?php else: ?>
                                        <div class="user-avatar" style="margin: 0 auto;">
                                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="role-badge <?= strtolower($user['role_name']) ?>">
                                        <?= ucfirst($user['role_name']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $user['status'] ?>">
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <?= date('M d, Y h:i A', strtotime($user['last_login'])) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                            Actions <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="<?= SITE_URL ?>/admin/edit_user.php?id=<?= $user['user_id'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="<?= SITE_URL ?>/admin/delete_user.php?id=<?= $user['user_id'] ?>" 
                                               class="delete" 
                                               onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars($user['full_name']) ?>?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalRecords) ?> of <?= $totalRecords ?> users
                    </div>
                    <div class="pagination-buttons">
                        <?php
                        $searchQuery = $search ? '&search=' . urlencode($search) : '';
                        
                        // Previous button
                        if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $searchQuery ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <button class="page-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                        <?php endif; ?>

                        <?php
                        // Page numbers
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?page=<?= $i ?><?= $searchQuery ?>" 
                               class="page-btn <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        // Next button
                        if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $searchQuery ?>" class="page-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <button class="page-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
    
    <script>
        // Dropdown toggle with smart positioning
        function toggleDropdown(btn) {
            const menu = btn.nextElementSibling;
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                if (m !== menu) {
                    m.classList.remove('active');
                    m.style.top = '';
                    m.style.bottom = '';
                }
            });
            
            // Toggle current dropdown
            const isActive = menu.classList.contains('active');
            menu.classList.toggle('active');
            
            if (!isActive) {
                // Check if dropdown would go off-screen
                const menuRect = menu.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                
                // If dropdown extends beyond viewport, open it upward
                if (menuRect.bottom > viewportHeight - 20) {
                    menu.style.top = 'auto';
                    menu.style.bottom = 'calc(100% + 4px)';
                } else {
                    menu.style.top = 'calc(100% + 4px)';
                    menu.style.bottom = 'auto';
                }
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('active');
                    menu.style.top = '';
                    menu.style.bottom = '';
                });
            }
        });
    </script>
</body>
</html>
