<?php
/**
 * SelamatRide SmartSchoolBus - Staff Attendance Records
 * Real-Time Attendance Monitoring and Editing
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Attendance Records";
$currentPage = "attendance";

// Handle Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_attendance'])) {
    try {
        $record_id = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
        $verification_status = filter_input(INPUT_POST, 'verification_status', FILTER_SANITIZE_STRING);
        
        if ($record_id && in_array($verification_status, ['verified', 'pending', 'flagged'])) {
            $stmt = $pdo->prepare("UPDATE attendance_records SET verification_status = ? WHERE record_id = ?");
            $stmt->execute([$verification_status, $record_id]);
            
            $_SESSION['success_message'] = "Attendance record updated successfully.";
            header("Location: " . SITE_URL . "/staff/attendance.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Invalid data provided.";
        }
    } catch (Exception $e) {
        error_log("Attendance Edit Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update record.";
    }
}

// Pagination and Filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_bus = isset($_GET['bus']) ? intval($_GET['bus']) : 0;
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';

// Build query
$where_conditions = ["DATE(ar.timestamp) = ?"];
$params = [$filter_date];

if ($filter_bus > 0) {
    $where_conditions[] = "ar.bus_id = ?";
    $params[] = $filter_bus;
}

if ($filter_action && in_array($filter_action, ['boarded', 'dropped_off'])) {
    $where_conditions[] = "ar.action = ?";
    $params[] = $filter_action;
}

$where_sql = implode(' AND ', $where_conditions);

try {
    // Count total records
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM attendance_records ar 
        WHERE $where_sql
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch attendance records
    $params_with_limit = array_merge($params, [$offset, $records_per_page]);
    
    $attendanceRecords = $pdo->prepare("
        SELECT 
            ar.record_id,
            ar.timestamp,
            ar.action,
            ar.verification_status,
            ar.device_id,
            s.student_id,
            s.student_name,
            s.rfid_uid,
            s.avatar_url,
            b.bus_id,
            b.bus_number
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.student_id
        JOIN buses b ON ar.bus_id = b.bus_id
        WHERE $where_sql
        ORDER BY ar.timestamp DESC
        LIMIT ?, ?
    ");
    $attendanceRecords->execute($params_with_limit);
    $records = $attendanceRecords->fetchAll();

    // Get bus list for filter
    $buses = $pdo->query("SELECT bus_id, bus_number FROM buses WHERE status = 'active' ORDER BY bus_number")->fetchAll();

    // Get the last scan from RFID reader (most recent attendance record)
    $lastScanStmt = $pdo->query("
        SELECT ar.timestamp, s.student_name 
        FROM attendance_records ar 
        JOIN students s ON ar.student_id = s.student_id 
        ORDER BY ar.timestamp DESC 
        LIMIT 1
    ");
    $lastScan = $lastScanStmt->fetch();

} catch (Exception $e) {
    error_log("Attendance Fetch Error: " . $e->getMessage());
    $records = [];
    $buses = [];
    $total_records = 0;
    $total_pages = 0;
    $lastScan = null;
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
        .attendance-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .attendance-stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 28px 24px;
            transition: all 0.3s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .attendance-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .filters-bar {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(37, 99, 235, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .filter-control {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 16px;
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .attendance-table thead th {
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
        
        .attendance-table thead th i {
            margin-right: 6px;
            opacity: 0.7;
        }
        
        .attendance-table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s;
        }
        
        .attendance-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
            transform: translateX(4px);
        }
        
        .attendance-table tbody td {
            padding: 16px;
            vertical-align: middle;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .student-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .student-avatar-placeholder {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .student-name {
            font-weight: 600;
            font-size: 15px;
        }
        
        .rfid-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            padding: 6px 12px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .bus-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .scan-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .scan-badge.in {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .scan-badge.out {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .timestamp-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .timestamp-date {
            font-weight: 600;
            font-size: 14px;
        }
        
        .timestamp-time {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .status-badge.verified {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-badge.verified i {
            font-size: 14px;
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .status-badge.flagged {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .action-btn {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: var(--primary-color);
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .action-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .edit-modal-overlay {
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
        }
        
        .edit-modal-content {
            background: var(--card-bg);
            border-radius: 20px;
            width: 90%;
            max-width: 540px;
            padding: 0;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            animation: modalSlideUp 0.3s ease-out;
        }
        
        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.1));
            padding: 28px 32px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .modal-title {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-color);
        }
        
        .modal-body {
            padding: 32px;
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            padding: 28px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .pagination-btn:hover {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .pagination-info {
            padding: 10px 20px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 10px;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 14px;
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
                <h1><i class="fas fa-qrcode" style="color: var(--primary-color);"></i> Attendance Records</h1>
                <p>Real-time RFID scanning monitoring and verification system</p>
            </div>
            <button onclick="location.reload()" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sync-alt"></i> Live Refresh
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
        <div class="attendance-stats-grid">
            <div class="attendance-stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value">
                    <?php
                    $verified = array_filter($records, fn($r) => $r['verification_status'] === 'verified');
                    echo count($verified);
                    ?>
                </div>
                <div class="stat-label">Verified Scans</div>
            </div>
            
            <div class="attendance-stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value">
                    <?php
                    $pending = array_filter($records, fn($r) => $r['verification_status'] === 'pending');
                    echo count($pending);
                    ?>
                </div>
                <div class="stat-label">Pending Review</div>
            </div>
            
            <div class="attendance-stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value">
                    <?php
                    $boarded = array_filter($records, fn($r) => $r['action'] === 'boarded');
                    echo count($boarded);
                    ?>
                </div>
                <div class="stat-label">Total Records</div>
            </div>
            
            <div class="attendance-stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value">
                    <?php 
                    if ($lastScan) {
                        echo date('H:i', strtotime($lastScan['timestamp']));
                    } else {
                        echo '--:--';
                    }
                    ?>
                </div>
                <div class="stat-label">Last Scan Time</div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="filters-bar">
            <form method="GET" action="">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--primary-color);">
                        <i class="fas fa-sliders-h"></i> Advanced Filters
                    </h3>
                    <button type="button" onclick="window.location.href='<?= SITE_URL ?>/staff/attendance.php'" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; color: var(--text-primary); display: flex; align-items: center; gap: 6px; transition: all 0.3s;">
                        <i class="fas fa-redo"></i> Reset All
                    </button>
                </div>
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-calendar"></i> Select Date</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="filter-control">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-bus"></i> Select Bus</label>
                        <select name="bus" class="filter-control">
                            <option value="0">All Buses</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?= $bus['bus_id'] ?>" <?= $filter_bus == $bus['bus_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bus['bus_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-exchange-alt"></i> Scan Type</label>
                        <select name="action" class="filter-control">
                            <option value="">All Types</option>
                            <option value="boarded" <?= $filter_action === 'boarded' ? 'selected' : '' ?>>IN (Boarded)</option>
                            <option value="dropped_off" <?= $filter_action === 'dropped_off' ? 'selected' : '' ?>>OUT (Dropped Off)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label" style="opacity: 0;">Apply</label>
                        <button type="submit" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Attendance Records Table -->
        <div class="content-card">
            <div class="card-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 20px;">
                <div>
                    <h2 class="card-title" style="margin-bottom: 8px;">
                        <i class="fas fa-table"></i> Live Attendance Records
                    </h2>
                    <p style="margin: 0; font-size: 14px; color: var(--text-secondary);">
                        Showing <span style="color: var(--primary-color); font-weight: 700;"><?= number_format($total_records) ?></span> records for <?= date('F d, Y', strtotime($filter_date)) ?>
                    </p>
                </div>
            </div>
            <div class="table-container" style="overflow-x: auto;">
                <?php if (empty($records)): ?>
                    <div style="text-align: center; padding: 80px 24px; background: rgba(255, 255, 255, 0.02); border-radius: 16px; margin: 24px;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-inbox" style="font-size: 40px; color: var(--text-secondary); opacity: 0.3;"></i>
                        </div>
                        <h3 style="margin: 0 0 8px 0; font-size: 20px; color: var(--text-primary);">No Records Found</h3>
                        <p style="color: var(--text-secondary); margin: 0;">No attendance scans match your selected filters</p>
                    </div>
                <?php else: ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user-graduate"></i> Student</th>
                                <th><i class="fas fa-id-card"></i> RFID UID</th>
                                <th><i class="fas fa-bus"></i> Bus</th>
                                <th><i class="fas fa-exchange-alt"></i> Type</th>
                                <th><i class="fas fa-clock"></i> Timestamp</th>
                                <th><i class="fas fa-shield-check"></i> Status</th>
                                <th style="text-align: center;"><i class="fas fa-cog"></i> Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <?php if (!empty($record['avatar_url']) && file_exists(__DIR__ . '/../' . $record['avatar_url'])): ?>
                                                <img src="<?= SITE_URL ?>/<?= htmlspecialchars($record['avatar_url']) ?>" 
                                                     class="student-avatar"
                                                     alt="<?= htmlspecialchars($record['student_name']) ?>">
                                            <?php else: ?>
                                                <div class="student-avatar-placeholder">
                                                    <?= strtoupper(substr($record['student_name'], 0, 2)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="student-name"><?= htmlspecialchars($record['student_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="rfid-badge">
                                            <i class="fas fa-microchip"></i>
                                            <?= htmlspecialchars($record['rfid_uid']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bus-badge">
                                            <i class="fas fa-bus"></i>
                                            <?= htmlspecialchars($record['bus_number']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="scan-badge <?= $record['action'] === 'boarded' ? 'in' : 'out' ?>">
                                            <i class="fas fa-arrow-<?= $record['action'] === 'boarded' ? 'up' : 'down' ?>"></i>
                                            <?= $record['action'] === 'boarded' ? 'IN' : 'OUT' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="timestamp-cell">
                                            <span class="timestamp-date"><?= date('M d, Y', strtotime($record['timestamp'])) ?></span>
                                            <span class="timestamp-time"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($record['timestamp'])) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $record['verification_status'] ?>">
                                            <i class="fas fa-<?= $record['verification_status'] === 'verified' ? 'check-circle' : ($record['verification_status'] === 'pending' ? 'hourglass-half' : 'flag') ?>"></i>
                                            <?= ucfirst($record['verification_status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <button onclick="editRecord(<?= $record['record_id'] ?>, '<?= $record['verification_status'] ?>', '<?= addslashes($record['student_name']) ?>')" 
                                                class="action-btn" title="Edit Verification Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&date=<?= $filter_date ?>&bus=<?= $filter_bus ?>&action=<?= $filter_action ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <div class="pagination-info">
                                Page <?= $page ?> of <?= $total_pages ?>
                            </div>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&date=<?= $filter_date ?>&bus=<?= $filter_bus ?>&action=<?= $filter_action ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="edit-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; align-items: center; justify-content: center;">
        <div class="edit-modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-shield-check"></i>
                    Edit Verification Status
                </h3>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="record_id" id="edit_record_id">
                    <input type="hidden" name="edit_attendance" value="1">
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-user-graduate"></i> Student Name
                        </label>
                        <input type="text" id="edit_student_name" class="form-control" readonly style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 14px; border-radius: 10px; font-size: 15px; font-weight: 600;">
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-check-circle"></i> Verification Status
                        </label>
                        <select name="verification_status" id="edit_verification_status" class="form-control" required style="background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.1); padding: 14px; border-radius: 10px; font-size: 15px; font-weight: 600;">
                            <option value="verified">✓ Verified - Scan confirmed accurate</option>
                            <option value="pending">⏳ Pending - Requires review</option>
                            <option value="flagged">⚠ Flagged - Issue detected</option>
                        </select>
                    </div>
                </div>
                
                <div style="padding: 24px 32px; background: rgba(255, 255, 255, 0.02); border-top: 1px solid rgba(255, 255, 255, 0.05); display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px;">
                        <i class="fas fa-check"></i> Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    
    <script>
        function editRecord(recordId, currentStatus, studentName) {
            document.getElementById('edit_record_id').value = recordId;
            document.getElementById('edit_student_name').value = studentName;
            document.getElementById('edit_verification_status').value = currentStatus;
            document.getElementById('editModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on outside click
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('editModal').style.display === 'flex') {
                closeEditModal();
            }
        });
    </script>
    
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
