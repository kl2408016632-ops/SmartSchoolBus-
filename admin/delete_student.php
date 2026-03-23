<?php
/**
 * SelamatRide SmartSchoolBus - Delete Student
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']); // Only admin can delete students

$pageTitle = "Delete Student";
$currentPage = "students";

$student = null;
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$attendanceCount = 0;

// Fetch student data
if ($studentId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, p.parent_name, p.phone_primary, b.bus_number
            FROM students s
            LEFT JOIN parents p ON s.parent_id = p.parent_id
            LEFT JOIN buses b ON s.bus_id = b.bus_id
            WHERE s.student_id = ?
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        if (!$student) {
            header('Location: ' . SITE_URL . '/admin/students.php?error=notfound');
            exit;
        }

        // Check if student has attendance records
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $attendanceCount = $stmt->fetchColumn();

    } catch (Exception $e) {
        error_log("Student Fetch Error: " . $e->getMessage());
        header('Location: ' . SITE_URL . '/admin/students.php?error=database');
        exit;
    }
} else {
    header('Location: ' . SITE_URL . '/admin/students.php?error=invalid');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } 
    
    // Check confirmation
    if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== 'yes') {
        $errors[] = 'Please confirm that you want to delete this student.';
    }

    // Safety check: Prevent deletion if attendance records exist
    if ($attendanceCount > 0 && !isset($_POST['force_delete'])) {
        $errors[] = "Cannot delete student with {$attendanceCount} attendance records. Student data is protected for record-keeping purposes.";
    }

    // If no errors, proceed with deletion
    if (empty($errors)) {
        try {
            // Delete student photo if exists
            if (!empty($student['photo_url']) && file_exists(__DIR__ . '/../' . $student['photo_url'])) {
                unlink(__DIR__ . '/../' . $student['photo_url']);
            }

            // Delete student record
            $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->execute([$studentId]);

            // Redirect with success message
            header('Location: ' . SITE_URL . '/admin/students.php?success=deleted');
            exit;
        } catch (Exception $e) {
            error_log("Student Deletion Error: " . $e->getMessage());
            $errors[] = 'Failed to delete student. This student may be referenced by other records.';
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
        .delete-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 32px;
            max-width: 700px;
        }

        .delete-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .delete-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: var(--danger);
        }

        .delete-header h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: var(--danger);
        }

        .delete-header p {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .student-preview {
            background: var(--content-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 24px;
        }

        .student-preview-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .student-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--danger);
        }

        .student-avatar-placeholder-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--danger), #dc2626);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: white;
        }

        .student-preview-info h3 {
            margin: 0 0 8px 0;
            font-size: 20px;
            color: var(--text-primary);
        }

        .student-preview-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .meta-item i {
            color: var(--primary-color);
        }

        .student-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            color: var(--text-primary);
            word-break: break-word;
        }

        .warning-box {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .warning-box-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .warning-box-header i {
            font-size: 24px;
            color: #f59e0b;
        }

        .warning-box-header strong {
            font-size: 16px;
            color: #f59e0b;
        }

        .warning-box p {
            margin: 0;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .danger-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .danger-box-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .danger-box-header i {
            font-size: 24px;
            color: var(--danger);
        }

        .danger-box-header strong {
            font-size: 16px;
            color: var(--danger);
        }

        .danger-box p {
            margin: 0;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        .alert li {
            margin: 4px 0;
        }

        .confirmation-box {
            background: var(--content-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 24px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-wrapper label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            user-select: none;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 24px;
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

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-danger:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--content-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        @media (max-width: 768px) {
            .student-details-grid {
                grid-template-columns: 1fr;
            }

            .student-preview-header {
                flex-direction: column;
                text-align: center;
            }

            .form-actions {
                flex-direction: column-reverse;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
                <h1>Delete Student</h1>
                <p>Permanently remove student from the system</p>
            </div>
        </div>

        <!-- Delete Container -->
        <div class="delete-container">
            <div class="delete-header">
                <div class="delete-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h2>Confirm Student Deletion</h2>
                <p>This action cannot be undone. Please review the information below carefully.</p>
            </div>

            <!-- Student Preview -->
            <div class="student-preview">
                <div class="student-preview-header">
                    <?php if (!empty($student['photo_url']) && file_exists(__DIR__ . '/../' . $student['photo_url'])): ?>
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($student['photo_url']) ?>?v=<?= time() ?>" 
                             class="student-avatar-large" 
                             alt="<?= htmlspecialchars($student['student_name']) ?>">
                    <?php else: ?>
                        <div class="student-avatar-placeholder-large">
                            <?= strtoupper(substr($student['student_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="student-preview-info">
                        <h3><?= htmlspecialchars($student['student_name']) ?></h3>
                        <div class="student-preview-meta">
                            <span class="meta-item">
                                <i class="fas fa-id-card"></i>
                                RFID: <?= htmlspecialchars($student['rfid_uid']) ?>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-hashtag"></i>
                                ID: <?= $student['student_id'] ?>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Enrolled: <?= !empty($student['enrollment_date']) ? date('d M Y', strtotime($student['enrollment_date'])) : 'N/A' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="student-details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Parent/Guardian</span>
                        <span class="detail-value">
                            <?= !empty($student['parent_name']) ? htmlspecialchars($student['parent_name']) : 'Not assigned' ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Parent Phone</span>
                        <span class="detail-value">
                            <?= !empty($student['phone_primary']) ? htmlspecialchars($student['phone_primary']) : 'N/A' ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Assigned Bus</span>
                        <span class="detail-value">
                            <?= !empty($student['bus_number']) ? htmlspecialchars($student['bus_number']) : 'Not assigned' ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Payment Status</span>
                        <span class="detail-value">
                            <?= ucfirst($student['payment_status']) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">
                            <?= ucfirst($student['status']) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Attendance Records</span>
                        <span class="detail-value">
                            <?= $attendanceCount ?> record<?= $attendanceCount != 1 ? 's' : '' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Warnings -->
            <?php if ($attendanceCount > 0): ?>
                <div class="danger-box">
                    <div class="danger-box-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Cannot Delete: Attendance Records Exist</strong>
                    </div>
                    <p>
                        This student has <strong><?= $attendanceCount ?></strong> attendance record<?= $attendanceCount != 1 ? 's' : '' ?> in the system. 
                        For data integrity and compliance purposes, students with attendance history cannot be deleted.
                    </p>
                    <p style="margin-top: 12px;">
                        <strong>Recommended Action:</strong> Instead of deleting, you can set the student status to "Inactive" 
                        from the <a href="<?= SITE_URL ?>/admin/edit_student.php?id=<?= $studentId ?>" style="color: var(--primary-color); text-decoration: underline;">Edit Student</a> page.
                    </p>
                </div>
            <?php else: ?>
                <div class="warning-box">
                    <div class="warning-box-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning: Permanent Action</strong>
                    </div>
                    <p>
                        Deleting this student will permanently remove all associated data including:
                    </p>
                    <ul style="margin: 12px 0 0 20px; padding: 0;">
                        <li>Student profile information</li>
                        <li>RFID card association</li>
                        <li>Bus assignment</li>
                        <li>Student photo (if uploaded)</li>
                    </ul>
                    <p style="margin-top: 12px;">
                        <strong>This action cannot be undone.</strong> Consider setting the student status to "Inactive" instead.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong><i class="fas fa-exclamation-circle"></i> Error:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Confirmation Form -->
            <?php if ($attendanceCount == 0): ?>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="confirmation-box">
                        <div class="checkbox-wrapper">
                            <input 
                                type="checkbox" 
                                id="confirm_delete" 
                                name="confirm_delete" 
                                value="yes"
                                onchange="document.getElementById('deleteBtn').disabled = !this.checked"
                            >
                            <label for="confirm_delete">
                                I understand that this action is permanent and cannot be undone
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" id="deleteBtn" class="btn btn-danger" disabled>
                            <i class="fas fa-trash-alt"></i> Delete Student
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="form-actions">
                    <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Students
                    </a>
                    <a href="<?= SITE_URL ?>/admin/edit_student.php?id=<?= $studentId ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Student Instead
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
