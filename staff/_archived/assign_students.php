<?php
/**
 * SelamatRide SmartSchoolBus - Assign Students to Bus
 * Staff can assign/reassign students to different buses
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Assign Students to Bus";
$currentPage = "assign-students";

// Handle Bus Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_student'])) {
    try {
        $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $bus_id = filter_input(INPUT_POST, 'bus_id', FILTER_VALIDATE_INT);
        
        if ($student_id && $bus_id) {
            // Verify bus exists and is active
            $bus_check = $pdo->prepare("SELECT bus_id FROM buses WHERE bus_id = ? AND status = 'active'");
            $bus_check->execute([$bus_id]);
            
            if ($bus_check->fetch()) {
                $stmt = $pdo->prepare("UPDATE students SET assigned_bus_id = ? WHERE student_id = ?");
                $stmt->execute([$bus_id, $student_id]);
                
                $_SESSION['success_message'] = "Student assigned to bus successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid bus selected.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid data provided.";
        }
        
        header("Location: " . SITE_URL . "/staff/assign_students.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Bus Assignment Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to assign student to bus.";
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Count total students
    if ($search) {
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM students 
            WHERE status = 'active' AND (student_name LIKE ? OR rfid_uid LIKE ?)
        ");
        $search_param = "%$search%";
        $count_stmt->execute([$search_param, $search_param]);
    } else {
        $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    }
    
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch students with current bus assignment
    if ($search) {
        $students_stmt = $pdo->prepare("
            SELECT 
                s.student_id,
                s.student_name,
                s.rfid_uid,
                s.avatar_url,
                s.assigned_bus_id,
                b.bus_number as current_bus
            FROM students s
            LEFT JOIN buses b ON s.assigned_bus_id = b.bus_id
            WHERE s.status = 'active' AND (s.student_name LIKE ? OR s.rfid_uid LIKE ?)
            ORDER BY s.student_name
            LIMIT ?, ?
        ");
        $students_stmt->execute([$search_param, $search_param, $offset, $records_per_page]);
    } else {
        $students_stmt = $pdo->prepare("
            SELECT 
                s.student_id,
                s.student_name,
                s.rfid_uid,
                s.avatar_url,
                s.assigned_bus_id,
                b.bus_number as current_bus
            FROM students s
            LEFT JOIN buses b ON s.assigned_bus_id = b.bus_id
            WHERE s.status = 'active'
            ORDER BY s.student_name
            LIMIT ?, ?
        ");
        $students_stmt->execute([$offset, $records_per_page]);
    }
    
    $students = $students_stmt->fetchAll();

    // Get all active buses for assignment dropdown
    $buses = $pdo->query("SELECT bus_id, bus_number, capacity FROM buses WHERE status = 'active' ORDER BY bus_number")->fetchAll();

} catch (Exception $e) {
    error_log("Assign Students Fetch Error: " . $e->getMessage());
    $students = [];
    $buses = [];
    $total_records = 0;
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php include '../admin/includes/admin_styles.php'; ?>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Assign Students to Bus</h1>
            <p>Manage student-to-bus assignments for transportation planning.</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 24px; color: var(--success); display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 24px; color: var(--danger); display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-exclamation-circle" style="font-size: 20px;"></i>
                <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="content-card" style="margin-bottom: 24px;">
            <form method="GET" action="" style="padding: 24px;">
                <div style="display: flex; gap: 12px;">
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by student name or RFID..." 
                           class="form-control"
                           style="flex: 1;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($search): ?>
                        <a href="<?= SITE_URL ?>/staff/assign_students.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Students Table -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-users"></i> Student List (<?= number_format($total_records) ?>)
                </h2>
            </div>
            <div class="table-container">
                <?php if (empty($students)): ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 48px;">
                        <i class="fas fa-info-circle" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                        <?= $search ? 'No students found matching your search.' : 'No active students found.' ?>
                    </p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>RFID UID</th>
                                <th>Current Bus</th>
                                <th>Assign to Bus</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <?php if (!empty($student['avatar_url']) && file_exists(__DIR__ . '/../' . $student['avatar_url'])): ?>
                                                <img src="<?= SITE_URL ?>/<?= htmlspecialchars($student['avatar_url']) ?>" 
                                                     style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" 
                                                     alt="<?= htmlspecialchars($student['student_name']) ?>">
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px;">
                                                    <?= strtoupper(substr($student['student_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <span style="font-weight: 500;"><?= htmlspecialchars($student['student_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <code style="font-size: 12px; background: var(--content-bg); padding: 4px 8px; border-radius: 4px;">
                                            <?= htmlspecialchars($student['rfid_uid']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ($student['current_bus']): ?>
                                            <span class="badge badge-outline">
                                                <?= htmlspecialchars($student['current_bus']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-style: italic;">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display: inline-block;">
                                            <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                                            <input type="hidden" name="assign_student" value="1">
                                            <select name="bus_id" class="form-control" style="min-width: 150px;" required>
                                                <option value="">Select Bus</option>
                                                <?php foreach ($buses as $bus): ?>
                                                    <option value="<?= $bus['bus_id'] ?>" 
                                                            <?= $student['assigned_bus_id'] == $bus['bus_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($bus['bus_number']) ?> (Cap: <?= $bus['capacity'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                    </td>
                                    <td>
                                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">
                                                <i class="fas fa-save"></i> Assign
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination" style="padding: 24px; display: flex; justify-content: center; gap: 8px;">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-secondary">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <span style="padding: 8px 16px; color: var(--text-secondary);">
                                Page <?= $page ?> of <?= $total_pages ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-secondary">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Card -->
        <div class="content-card" style="margin-top: 24px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1)); border: 1px solid rgba(59, 130, 246, 0.2);">
            <div style="padding: 24px;">
                <h3 style="margin: 0 0 12px 0; font-size: 16px; color: var(--primary-color);">
                    <i class="fas fa-info-circle"></i> Assignment Guidelines
                </h3>
                <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary); line-height: 1.8;">
                    <li>Each student can only be assigned to one bus at a time</li>
                    <li>Monitor bus capacity to avoid overcrowding</li>
                    <li>Changes take effect immediately in the system</li>
                    <li>Students without bus assignment cannot use RFID boarding</li>
                </ul>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
</body>
</html>
