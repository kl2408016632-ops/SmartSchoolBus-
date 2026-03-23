<?php
/**
 * SelamatRide SmartSchoolBus - Student Absence Management
 * Mark absent, track patterns, notify parents
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Student Absences";
$currentPage = "absences";

date_default_timezone_set('Asia/Kuala_Lumpur');

// Handle Mark Absent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_absent'])) {
    try {
        $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $absence_date = filter_input(INPUT_POST, 'absence_date', FILTER_SANITIZE_STRING);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
        $reason_details = filter_input(INPUT_POST, 'reason_details', FILTER_SANITIZE_STRING);
        
        if ($student_id && $absence_date && in_array($reason, ['sick', 'vacation', 'emergency', 'other'])) {
            // Check if already marked
            $check = $pdo->prepare("SELECT absence_id FROM student_absences WHERE student_id = ? AND absence_date = ?");
            $check->execute([$student_id, $absence_date]);
            
            if ($check->fetch()) {
                $_SESSION['error_message'] = "This student is already marked absent for that date.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO student_absences 
                    (student_id, absence_date, reason, reason_details, marked_by, parent_notified)
                    VALUES (?, ?, ?, ?, ?, FALSE)
                ");
                $stmt->execute([$student_id, $absence_date, $reason, $reason_details, $_SESSION['user_id']]);
                
                $_SESSION['success_message'] = "Student marked as absent successfully.";
            }
        } else {
            $_SESSION['error_message'] = "Please fill all required fields.";
        }
        
        header("Location: " . SITE_URL . "/staff/absences.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Absence Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to mark absence.";
    }
}

// Filters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$student_filter = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Fetch absences
try {
    $where = ["DATE(absence_date) = ?"];
    $params = [$date_filter];
    
    if ($student_filter > 0) {
        $where[] = "sa.student_id = ?";
        $params[] = $student_filter;
    }
    
    $where_sql = implode(' AND ', $where);
    
    $absences = $pdo->prepare("
        SELECT sa.*, s.student_name, s.rfid_uid, s.student_photo, u.full_name as marked_by_name,
               p.parent_name, p.phone_primary
        FROM student_absences sa
        JOIN students s ON sa.student_id = s.student_id
        JOIN users u ON sa.marked_by = u.user_id
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        WHERE $where_sql
        ORDER BY sa.created_at DESC
    ");
    $absences->execute($params);
    $absence_records = $absences->fetchAll();
    
    // Absence statistics (last 30 days)
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_absences,
            COUNT(DISTINCT student_id) as students_affected,
            SUM(CASE WHEN reason = 'sick' THEN 1 ELSE 0 END) as sick_count,
            SUM(CASE WHEN reason = 'vacation' THEN 1 ELSE 0 END) as vacation_count
        FROM student_absences
        WHERE absence_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ")->fetch();
    
    // Get all active students for dropdown
    $students = $pdo->query("SELECT student_id, student_name, rfid_uid FROM students ORDER BY student_name")->fetchAll();
    
    // Debug: Log student count
    error_log("Total students found: " . count($students));
    
} catch (Exception $e) {
    error_log("Absences Fetch Error: " . $e->getMessage());
    $absence_records = [];
    $students = [];
    $stats = ['total_absences' => 0, 'students_affected' => 0, 'sick_count' => 0, 'vacation_count' => 0];
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
    
    <style>
        .absence-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .absence-stat-card {
            background: linear-gradient(135deg, var(--card-bg), rgba(59, 130, 246, 0.05));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .absence-stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.2);
            border-color: rgba(59, 130, 246, 0.4);
        }
        
        .absence-stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.08), transparent);
            border-radius: 50%;
        }
        
        .stat-icon-box {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }
        
        .stat-icon-box.orange {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.1));
            color: #fb923c;
        }
        
        .stat-icon-box.red {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.1));
            color: #ef4444;
        }
        
        .stat-icon-box.purple {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.2), rgba(147, 51, 234, 0.1));
            color: #a855f7;
        }
        
        .stat-icon-box.blue {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.1));
            color: var(--primary-color);
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .stat-title {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .filters-section {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(37, 99, 235, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 28px;
        }
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .filters-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 16px;
            align-items: end;
        }
        
        .filter-item label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .filter-item select,
        .filter-item input[type="date"] {
            width: 100%;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 13px 16px;
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-item select:focus,
        .filter-item input[type="date"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .absence-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .absence-table thead th {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(37, 99, 235, 0.08));
            padding: 18px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--primary-color);
            border-bottom: 2px solid rgba(59, 130, 246, 0.3);
            white-space: nowrap;
        }
        
        .absence-table thead th i {
            margin-right: 6px;
            opacity: 0.7;
        }
        
        .absence-table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s;
        }
        
        .absence-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
            transform: translateX(4px);
        }
        
        .absence-table tbody td {
            padding: 18px 16px;
            vertical-align: middle;
        }
        
        .student-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .student-cell-name {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-primary);
        }
        
        .student-cell-id {
            font-size: 12px;
            color: var(--text-secondary);
            font-family: 'Courier New', monospace;
            background: rgba(99, 102, 241, 0.1);
            padding: 3px 8px;
            border-radius: 5px;
            display: inline-block;
            width: fit-content;
        }
        
        .reason-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            text-transform: capitalize;
        }
        
        .reason-badge.sick {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .reason-badge.vacation {
            background: rgba(59, 130, 246, 0.15);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .reason-badge.emergency {
            background: rgba(251, 146, 60, 0.15);
            color: #fb923c;
            border: 1px solid rgba(251, 146, 60, 0.3);
        }
        
        .reason-badge.other {
            background: rgba(156, 163, 175, 0.15);
            color: #9ca3af;
            border: 1px solid rgba(156, 163, 175, 0.3);
        }
        
        .parent-cell {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .parent-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .parent-phone {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .notified-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .notified-badge.yes {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .notified-badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
        }
        
        .modal-container {
            background: var(--card-bg);
            border-radius: 20px;
            width: 90%;
            max-width: 560px;
            padding: 0;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            animation: modalSlideUp 0.3s ease-out;
            position: relative;
        }
        
        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.1));
            padding: 30px 32px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 24px;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-color);
        }
        
        .modal-body {
            padding: 32px;
        }
        
        .modal-body .form-group {
            margin-bottom: 24px;
        }
        
        .modal-body .form-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-secondary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-body .form-control {
            width: 100%;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 14px 16px;
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .modal-body .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        
        .modal-body .form-control textarea {
            resize: vertical;
            font-family: inherit;
        }
        
        .modal-footer {
            padding: 24px 32px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn-add-absence {
            background: linear-gradient(135deg, #fb923c, #f97316);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(251, 146, 60, 0.3);
        }
        
        .btn-add-absence:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(251, 146, 60, 0.4);
            background: linear-gradient(135deg, #f97316, #ea580c);
        }
        
        .btn-add-absence:active {
            transform: translateY(0);
        }
        
        .btn-modal-cancel {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-modal-cancel:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
            color: var(--text-primary);
            transform: translateY(-1px);
        }
        
        .btn-modal-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-modal-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .btn-modal-submit:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-user-clock" style="color: #fb923c;"></i> Student Absences</h1>
                <p>Comprehensive absence tracking and parent notification management</p>
            </div>
            <button onclick="openMarkAbsentModal()" class="btn-add-absence" style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-plus-circle"></i> Mark Student Absent
            </button>
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

        <!-- Statistics Cards -->
        <div class="absence-stats-grid">
            <div class="absence-stat-card">
                <div class="stat-icon-box orange">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-number" style="color: #fb923c;"><?= number_format($stats['total_absences']) ?></div>
                <div class="stat-title">Total Absences (30d)</div>
            </div>

            <div class="absence-stat-card">
                <div class="stat-icon-box red">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number" style="color: #ef4444;"><?= number_format($stats['students_affected']) ?></div>
                <div class="stat-title">Students Affected</div>
            </div>

            <div class="absence-stat-card">
                <div class="stat-icon-box purple">
                    <i class="fas fa-thermometer"></i>
                </div>
                <div class="stat-number" style="color: #a855f7;"><?= number_format($stats['sick_count']) ?></div>
                <div class="stat-title">Sick Leave</div>
            </div>

            <div class="absence-stat-card">
                <div class="stat-icon-box blue">
                    <i class="fas fa-plane-departure"></i>
                </div>
                <div class="stat-number" style="color: var(--primary-color);"><?= number_format($stats['vacation_count']) ?></div>
                <div class="stat-title">Vacation</div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-header">
                    <h3 class="filters-title">
                        <i class="fas fa-sliders-h"></i> Filter Absence Records
                    </h3>
                    <?php if ($date_filter != date('Y-m-d') || $student_filter > 0): ?>
                        <a href="<?= SITE_URL ?>/staff/absences.php" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; color: var(--text-primary); display: flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.3s;">
                            <i class="fas fa-redo"></i> Reset Filters
                        </a>
                    <?php endif; ?>
                </div>
                <div class="filters-grid">
                    <div class="filter-item">
                        <label><i class="fas fa-calendar"></i> Select Date</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="filter-item">
                        <label><i class="fas fa-user-graduate"></i> Select Student</label>
                        <select name="student_id">
                            <option value="0">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['student_id'] ?>" <?= $student_filter == $student['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['student_name']) ?> - <?= htmlspecialchars($student['rfid_uid']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Absence Records Table -->
        <div class="absence-table">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-user-graduate"></i> Student</th>
                        <th><i class="fas fa-heartbeat"></i> Reason</th>
                        <th><i class="fas fa-comment-dots"></i> Notes</th>
                        <th><i class="fas fa-users"></i> Parent/Guardian</th>
                        <th><i class="fas fa-bell"></i> Notified</th>
                        <th><i class="fas fa-user-tie"></i> Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absence_records)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px 24px;">
                                <div style="opacity: 0.5;">
                                    <div style="width: 64px; height: 64px; margin: 0 auto 16px; background: rgba(251, 146, 60, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-calendar-check" style="font-size: 28px; color: #fb923c;"></i>
                                    </div>
                                    <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">No absences recorded</div>
                                    <div style="font-size: 14px;">All students are present for the selected date</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($absence_records as $record): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-weight: 600; font-size: 14px; color: var(--text-primary);">
                                            <?= date('d M Y', strtotime($record['absence_date'])) ?>
                                        </span>
                                        <span style="font-size: 12px; color: var(--text-secondary);">
                                            <?= date('l', strtotime($record['absence_date'])) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php 
                                        $avatar_path = !empty($record['student_photo']) && file_exists("../uploads/students/" . $record['student_photo']) 
                                            ? SITE_URL . "/uploads/students/" . $record['student_photo'] 
                                            : null;
                                        ?>
                                        <?php if ($avatar_path): ?>
                                            <img src="<?= $avatar_path ?>" alt="<?= htmlspecialchars($record['student_name']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(59, 130, 246, 0.3);">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #2563eb); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white; font-size: 16px;">
                                                <?= strtoupper(substr($record['student_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="display: flex; flex-direction: column;">
                                            <span style="font-weight: 600; font-size: 14px; color: var(--text-primary);">
                                                <?= htmlspecialchars($record['student_name']) ?>
                                            </span>
                                            <span style="font-size: 12px; color: var(--text-secondary); font-family: 'Courier New', monospace;">
                                                <?= htmlspecialchars($record['rfid_uid']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="reason-badge reason-<?= strtolower($record['reason']) ?>">
                                        <?php
                                        $reason_icons = [
                                            'sick' => 'fas fa-thermometer',
                                            'vacation' => 'fas fa-plane-departure',
                                            'emergency' => 'fas fa-exclamation-triangle',
                                            'other' => 'fas fa-ellipsis-h'
                                        ];
                                        $icon = $reason_icons[strtolower($record['reason'])] ?? 'fas fa-info-circle';
                                        ?>
                                        <i class="<?= $icon ?>"></i>
                                        <?= ucfirst($record['reason']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($record['reason_details'])): ?>
                                        <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-secondary); font-size: 13px;">
                                            <?= htmlspecialchars($record['reason_details']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-tertiary); font-style: italic; font-size: 13px;">No additional notes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="parent-cell">
                                        <i class="fas fa-user-friends"></i>
                                        <div style="display: flex; flex-direction: column;">
                                            <span style="font-weight: 500;"><?= htmlspecialchars($record['parent_name']) ?></span>
                                            <span style="font-size: 11px; color: var(--text-tertiary); font-family: 'Courier New', monospace;"><?= htmlspecialchars($record['phone_primary']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="notified-badge notified-<?= $record['parent_notified'] ? 'yes' : 'pending' ?>">
                                        <i class="fas <?= $record['parent_notified'] ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                                        <?= $record['parent_notified'] ? 'Yes' : 'Pending' ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-weight: 500; font-size: 13px; color: var(--text-primary);">
                                            <?= htmlspecialchars($record['marked_by_name']) ?>
                                        </span>
                                        <span style="font-size: 11px; color: var(--text-tertiary);">
                                            <?= date('d M, H:i', strtotime($record['created_at'])) ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Mark Absent Modal -->
    <div class="modal-overlay" id="markAbsentModal" style="display: none;">
        <div class="modal-container" style="max-width: 550px;">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-user-clock"></i>
                    Mark Student Absent
                </h3>
                <button onclick="closeMarkAbsentModal()" style="background: none; border: none; color: var(--text-secondary); font-size: 24px; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.3s;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="mark_absent" value="1">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-graduate"></i> Select Student *</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">Choose a student...</option>
                            <?php if (empty($students)): ?>
                                <option value="" disabled>No students found in database</option>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['student_id'] ?>">
                                        <?= htmlspecialchars($student['student_name']) ?> - <?= htmlspecialchars($student['rfid_uid']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($students)): ?>
                            <small style="color: var(--danger); font-size: 12px; display: block; margin-top: 6px;">
                                <i class="fas fa-exclamation-triangle"></i> No students found. Please add students first.
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Absence Date *</label>
                        <input type="date" name="absence_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-heartbeat"></i> Reason *</label>
                        <select name="reason" class="form-control" required>
                            <option value="">Select reason...</option>
                            <option value="sick">🤒 Sick</option>
                            <option value="vacation">✈️ Vacation</option>
                            <option value="emergency">⚠️ Emergency</option>
                            <option value="other">📋 Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-comment-dots"></i> Additional Details</label>
                        <textarea name="reason_details" class="form-control" rows="4" placeholder="Enter any additional information about the absence..." style="resize: vertical;"></textarea>
                        <small style="color: var(--text-tertiary); font-size: 12px; display: block; margin-top: 6px;">
                            Optional: Provide context or special instructions
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeMarkAbsentModal()" class="btn-modal-cancel" style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-times-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn-modal-submit" style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-save"></i> Mark Absent
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    
    <script>
        console.log('Absence page scripts loaded');
        
        function openMarkAbsentModal() {
            console.log('Opening mark absent modal');
            const modal = document.getElementById('markAbsentModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                console.log('Modal opened');
            } else {
                console.error('Modal element not found!');
            }
        }
        
        function closeMarkAbsentModal() {
            console.log('Closing mark absent modal');
            const modal = document.getElementById('markAbsentModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM ready, initializing modal listeners');
            
            const modal = document.getElementById('markAbsentModal');
            if (modal) {
                // Close modal when clicking outside
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeMarkAbsentModal();
                    }
                });
                console.log('Modal click listener added');
            }
            
            // Close modal with ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('markAbsentModal');
                    if (modal && modal.style.display === 'flex') {
                        closeMarkAbsentModal();
                    }
                }
            });
            console.log('ESC key listener added');
        });
    </script>
    
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
