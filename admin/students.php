<?php
/**
 * SelamatRide SmartSchoolBus - Student Management
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin', 'staff']);

$pageTitle = "Student Management";
$currentPage = "students";

// Handle success/error messages
$message = '';
$messageType = '';

if (isset($_GET['success'])) {
    $messageType = 'success';
    switch ($_GET['success']) {
        case 'created':
            $message = 'Student created successfully!';
            break;
        case 'updated':
            $message = 'Student updated successfully!';
            break;
        case 'deleted':
            $message = 'Student deleted successfully!';
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
    $whereClause = "WHERE s.student_name LIKE ? OR s.rfid_uid LIKE ? OR p.parent_name LIKE ? OR b.bus_number LIKE ?";
    $searchParam = "%{$search}%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Count total records
try {
    $countSql = "SELECT COUNT(*) as total FROM students s 
                 LEFT JOIN parents p ON s.parent_id = p.parent_id 
                 {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $perPage);
} catch (Exception $e) {
    error_log("Count Error: " . $e->getMessage());
    $totalRecords = 0;
    $totalPages = 0;
}

// Fetch students with pagination
try {
    $sql = "
        SELECT 
            s.*,
            p.parent_name,
            p.phone_primary,
            b.bus_number
        FROM students s
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        LEFT JOIN buses b ON s.bus_id = b.bus_id
        {$whereClause}
        ORDER BY s.student_name
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Student Management Error: " . $e->getMessage());
    $students = [];
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
            overflow: visible;
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

        .student-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .student-avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.paid {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-badge.unpaid {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
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

        tbody tr:last-child .dropdown {
            position: relative;
            z-index: 1001;
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
            text-decoration: none;
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

        .rfid-badge {
            font-family: 'Courier New', monospace;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
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
                <h1>Student Management</h1>
                <p>Manage student records, RFID cards, and bus assignments</p>
            </div>
            <a href="<?= SITE_URL ?>/admin/create_student.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Student
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
                placeholder="Search by name, RFID, parent, or bus..." 
                value="<?= htmlspecialchars($search) ?>"
            >
            <button type="submit" class="btn btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search): ?>
                <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-search">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- Students Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Avatar</th>
                        <th>Student Name</th>
                        <th>RFID UID</th>
                        <th>Parent Name</th>
                        <th>Parent Phone</th>
                        <th>Assigned Bus</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-secondary);">
                                <?= $search ? 'No students found matching your search.' : 'No students found. Create your first student to get started.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rowNumber = $offset + 1;
                        foreach ($students as $student): ?>
                            <tr>
                                <td><strong>#<?= $rowNumber++ ?></strong></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($student['photo_url']) && file_exists(__DIR__ . '/../' . $student['photo_url'])): ?>
                                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($student['photo_url']) ?>?v=<?= time() ?>" 
                                             class="student-avatar" 
                                             alt="<?= htmlspecialchars($student['student_name']) ?>">
                                    <?php else: ?>
                                        <div class="student-avatar-placeholder">
                                            <?= strtoupper(substr($student['student_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($student['student_name']) ?></strong></td>
                                <td><span class="rfid-badge"><?= htmlspecialchars($student['rfid_uid']) ?></span></td>
                                <td><?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($student['phone_primary'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($student['bus_number'] ?? 'Not assigned') ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                            Actions <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="<?= SITE_URL ?>/admin/edit_student.php?id=<?= $student['student_id'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="<?= SITE_URL ?>/admin/delete_student.php?id=<?= $student['student_id'] ?>" 
                                               class="delete" 
                                               onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars($student['student_name']) ?>?')">
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
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalRecords) ?> of <?= $totalRecords ?> students
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
