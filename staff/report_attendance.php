<?php
/**
 * SelamatRide SmartSchoolBus - Staff Reports
 * View and Print Attendance Reports (No Delete/Modify)
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Reports";
$currentPage = "reports";

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$busFilter = isset($_GET['bus_id']) ? (int)$_GET['bus_id'] : 0;
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';

// Initialize variables
$totalRecords = 0;
$totalPages = 0;
$records = [];
$buses = [];
$stats = ['total_scans' => 0, 'unique_students' => 0, 'total_boarded' => 0, 'total_dropped' => 0, 'verified_scans' => 0, 'error_scans' => 0];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$where = ["DATE(ar.timestamp) BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($busFilter > 0) {
    $where[] = "ar.bus_id = ?";
    $params[] = $busFilter;
}

if (!empty($actionFilter) && in_array($actionFilter, ['boarded', 'dropped_off'])) {
    $where[] = "ar.action = ?";
    $params[] = $actionFilter;
}

$whereClause = "WHERE " . implode(" AND ", $where);

try {
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM attendance_records ar {$whereClause}";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);

    // Get attendance records
    $query = "
        SELECT 
            ar.record_id,
            ar.timestamp,
            ar.action,
            ar.verification_status,
            ar.device_id,
            s.student_name,
            s.rfid_uid,
            s.avatar_url,
            b.bus_number
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.student_id
        JOIN buses b ON ar.bus_id = b.bus_id
        {$whereClause}
        ORDER BY ar.timestamp DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_scans,
            COUNT(DISTINCT ar.student_id) as unique_students,
            SUM(CASE WHEN ar.action = 'boarded' THEN 1 ELSE 0 END) as total_boarded,
            SUM(CASE WHEN ar.action = 'dropped_off' THEN 1 ELSE 0 END) as total_dropped,
            SUM(CASE WHEN ar.verification_status = 'verified' THEN 1 ELSE 0 END) as verified_scans,
            SUM(CASE WHEN ar.verification_status IN ('pending', 'flagged') THEN 1 ELSE 0 END) as error_scans
        FROM attendance_records ar
        {$whereClause}
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Get buses for filter
    $busesStmt = $pdo->query("SELECT bus_id, bus_number FROM buses WHERE status = 'active' ORDER BY bus_number");
    $buses = $busesStmt->fetchAll();

} catch (Exception $e) {
    error_log("Staff Reports Error: " . $e->getMessage());
    $records = [];
    $buses = [];
    $stats = ['total_scans' => 0, 'unique_students' => 0, 'total_boarded' => 0, 'total_dropped' => 0, 'verified_scans' => 0, 'error_scans' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php include '../admin/includes/admin_styles.php'; ?>
    
    <style>
        /* Professional Report Styles */
        .report-header-card {
            background: linear-gradient(135deg, #e8eef7 0%, #dce4f0 100%);
            border-radius: 16px;
            padding: 32px 40px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .report-header-card .icon-wrapper {
            font-size: 48px;
            line-height: 1;
        }
        
        .report-header-card h1 {
            font-size: 36px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: #1e293b;
        }
        
        .report-header-card p {
            margin: 0;
            font-size: 16px;
            color: #64748b;
            font-weight: 400;
        }
        
        /* Filter Card */
        .filter-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .filter-card-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-card-body {
            padding: 32px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            background: var(--card-bg);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
        }
        
        .btn-export {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.4);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
        }
        
        .stat-card.blue::before {
            --gradient-start: #3b82f6;
            --gradient-end: #2563eb;
        }
        
        .stat-card.green::before {
            --gradient-start: #10b981;
            --gradient-end: #059669;
        }
        
        .stat-card.orange::before {
            --gradient-start: #f59e0b;
            --gradient-end: #d97706;
        }
        
        .stat-card.red::before {
            --gradient-start: #ef4444;
            --gradient-end: #dc2626;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .stat-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-card-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .stat-card-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .stat-card-icon.orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .stat-card-icon.red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .stat-card-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-card-value {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }
        
        /* Report Table */
        .report-table-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .report-table-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, rgba(59, 130, 246, 0.05), rgba(102, 126, 234, 0.05));
        }
        
        .report-table-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .report-table-header .date-range {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
            padding: 8px 16px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: var(--content-bg);
        }
        
        .data-table thead th {
            padding: 16px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .data-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }
        
        .data-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.03);
        }
        
        .data-table tbody td {
            padding: 20px 24px;
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .data-table tbody td:first-child {
            font-weight: 600;
        }
        
        .rfid-code {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(102, 126, 234, 0.1));
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        
        .badge.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .badge.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .badge.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .badge.outline {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 32px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: var(--text-secondary);
            opacity: 0.3;
            margin-bottom: 24px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .empty-state p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Pagination */
        .pagination {
            padding: 32px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            border-top: 1px solid var(--border-color);
        }
        
        .page-info {
            padding: 10px 20px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn-secondary {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: var(--content-bg);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        /* Info Card */
        .info-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(102, 126, 234, 0.08));
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 28px;
            margin-top: 32px;
        }
        
        .info-card h3 {
            margin: 0 0 12px 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-card p {
            margin: 0;
            color: var(--text-primary);
            line-height: 1.7;
            font-size: 14px;
        }
        
        .info-card strong {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        /* Print Styles */
        @media print {
            .topbar, .sidebar, .report-header-card, .filter-card, .no-print {
                display: none !important;
            }
            body {
                padding-top: 0 !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 15mm !important;
                max-width: 100% !important;
            }
            
            /* Print Header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 3px solid #3b82f6;
                page-break-after: avoid;
            }
            .print-logo {
                font-size: 28px;
                font-weight: 700;
                color: #3b82f6;
                margin-bottom: 5px;
            }
            .print-tagline {
                font-size: 12px;
                color: #666;
                margin-bottom: 12px;
            }
            .print-title {
                font-size: 20px;
                font-weight: 700;
                color: #000;
                margin: 12px 0 5px 0;
            }
            .print-date {
                font-size: 11px;
                color: #666;
            }
            
            /* Report Parameters */
            .print-params {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                background: #f3f4f6;
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 15px;
                font-size: 11px;
                page-break-after: avoid;
            }
            .param-row {
                display: flex;
            }
            .param-label {
                font-weight: 600;
                width: 100px;
            }
            .param-value {
                color: #666;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
                margin-bottom: 15px !important;
                page-break-after: avoid;
            }
            .stat-card {
                padding: 10px !important;
                page-break-inside: avoid;
            }
            .stat-card-header {
                margin-bottom: 8px !important;
            }
            .stat-card-icon {
                width: 36px !important;
                height: 36px !important;
                font-size: 18px !important;
            }
            .stat-card-value {
                font-size: 20px !important;
            }
            .stat-card-title {
                font-size: 9px !important;
            }
            .report-table-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .report-table-header {
                background: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 10px 12px !important;
            }
            .report-table-header h3 {
                font-size: 13px !important;
            }
            .data-table {
                font-size: 9px !important;
            }
            .data-table thead {
                background: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .data-table thead th {
                padding: 6px 8px !important;
                font-size: 9px !important;
            }
            .data-table tbody td {
                padding: 6px 8px !important;
                font-size: 9px !important;
            }
            .rfid-code {
                font-size: 8px !important;
                padding: 2px 6px !important;
            }
            .badge {
                font-size: 8px !important;
                padding: 3px 6px !important;
            }
            
            /* Print Footer */
            .print-footer {
                display: block !important;
                margin-top: 30px;
                padding-top: 15px;
                border-top: 2px solid #ddd;
                page-break-inside: avoid;
            }
            .signature-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 15px;
            }
            .signature-box {
                text-align: center;
            }
            .signature-line {
                border-bottom: 1px solid #000;
                margin-bottom: 5px;
                height: 60px;
            }
            .signature-label {
                font-size: 10px;
                font-weight: 600;
            }
            .print-note {
                font-size: 9px;
                color: #666;
                line-height: 1.4;
            }
            
            @page {
                size: A4 portrait;
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Print Header (Only visible when printing) -->
        <div class="print-header" style="display: none;">
            <div class="print-logo">🚌 SelamatRide SmartSchoolBus</div>
            <div class="print-tagline">Secure RFID Student Boarding Verification System</div>
            <div class="print-title">ATTENDANCE REPORT</div>
            <div class="print-date">Generated on: <?= date('F d, Y - h:i A') ?></div>
        </div>

        <!-- Print Parameters Grid (Only visible when printing) -->
        <div class="print-params" style="display: none;">
            <div class="param-item">
                <span class="param-label">Report Period:</span>
                <span class="param-value"><?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Bus:</span>
                <span class="param-value"><?= $busFilter == 0 ? 'All Buses' : htmlspecialchars($selectedBusNumber) ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Student:</span>
                <span class="param-value"><?= $studentFilter == 0 ? 'All Students' : htmlspecialchars($selectedStudentName) ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Scan Type:</span>
                <span class="param-value"><?= $actionFilter == 'all' ? 'All Types' : ucfirst(str_replace('_', ' ', $actionFilter)) ?></span>
            </div>
        </div>

        <!-- Report Header Card -->
        <div class="report-header-card no-print">
            <div class="icon-wrapper">📊</div>
            <div>
                <h1>Attendance Reports</h1>
                <p>View comprehensive attendance records and generate detailed reports</p>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card no-print">
            <div class="filter-card-header">
                <h3><i class="fas fa-filter"></i> Filter & Generate Report</h3>
            </div>
            <div class="filter-card-body">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> From Date</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-check"></i> To Date</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-bus"></i> Bus</label>
                            <select name="bus_id" class="form-control">
                                <option value="0">All Buses</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= $bus['bus_id'] ?>" <?= $busFilter == $bus['bus_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bus['bus_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-exchange-alt"></i> Action Type</label>
                            <select name="action" class="form-control">
                                <option value="">All Actions</option>
                                <option value="boarded" <?= $actionFilter === 'boarded' ? 'selected' : '' ?>>Boarded (IN)</option>
                                <option value="dropped_off" <?= $actionFilter === 'dropped_off' ? 'selected' : '' ?>>Dropped Off (OUT)</option>
                            </select>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                        <button type="button" onclick="window.print()" class="btn btn-success">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <button type="button" onclick="exportToExcel()" class="btn btn-export">
                            <i class="fas fa-file-excel"></i> Export to Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-title">Total Scans</div>
                        <div class="stat-card-value"><?= number_format($stats['total_scans']) ?></div>
                    </div>
                    <div class="stat-card-icon blue">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-title">Unique Students</div>
                        <div class="stat-card-value"><?= number_format($stats['unique_students']) ?></div>
                    </div>
                    <div class="stat-card-icon green">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-title">Boarded (IN)</div>
                        <div class="stat-card-value"><?= number_format($stats['total_boarded']) ?></div>
                    </div>
                    <div class="stat-card-icon orange">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card red">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-title">Dropped Off (OUT)</div>
                        <div class="stat-card-value"><?= number_format($stats['total_dropped']) ?></div>
                    </div>
                    <div class="stat-card-icon red">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="report-table-card">
            <div class="report-table-header no-print">
                <h3>
                    <i class="fas fa-file-alt"></i> Attendance Records
                </h3>
                <div class="date-range">
                    <i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?>
                </div>
            </div>
            
            <!-- Print Header (visible only when printing) -->
            <div style="display: none; padding: 24px; border-bottom: 2px solid #000;" class="print-only">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="margin: 0; font-size: 24px;">SelamatRide SmartSchoolBus</h1>
                    <h2 style="margin: 8px 0; font-size: 18px;">Attendance Report</h2>
                    <p style="margin: 0; font-size: 14px;">
                        Period: <?= date('M d, Y', strtotime($dateFrom)) ?> to <?= date('M d, Y', strtotime($dateTo)) ?>
                    </p>
                    <p style="margin: 4px 0; font-size: 14px;">
                        Generated: <?= date('M d, Y h:i A') ?>
                    </p>
                </div>
            </div>
            
            <div class="table-container">
                <?php if (empty($records)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Records Found</h3>
                        <p>No attendance records found for the selected period. Try adjusting your filters.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user-graduate"></i> Student Name</th>
                                <th><i class="fas fa-id-card"></i> RFID UID</th>
                                <th><i class="fas fa-bus"></i> Bus Number</th>
                                <th><i class="fas fa-exchange-alt"></i> Action</th>
                                <th><i class="fas fa-clock"></i> Date & Time</th>
                                <th><i class="fas fa-check-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['student_name']) ?></td>
                                    <td>
                                        <span class="rfid-code">
                                            <?= htmlspecialchars($record['rfid_uid']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge outline">
                                            <i class="fas fa-bus"></i> <?= htmlspecialchars($record['bus_number']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($record['action'] === 'boarded'): ?>
                                            <span class="badge success">
                                                <i class="fas fa-sign-in-alt"></i> BOARDED
                                            </span>
                                        <?php else: ?>
                                            <span class="badge warning">
                                                <i class="fas fa-sign-out-alt"></i> DROPPED OFF
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= date('M d, Y', strtotime($record['timestamp'])) ?></strong><br>
                                        <span style="color: var(--text-secondary); font-size: 13px;">
                                            <?= date('h:i A', strtotime($record['timestamp'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($record['verification_status'] === 'verified'): ?>
                                            <span class="badge success">
                                                <i class="fas fa-check-circle"></i> VERIFIED
                                            </span>
                                        <?php else: ?>
                                            <span class="badge danger">
                                                <i class="fas fa-exclamation-triangle"></i> <?= strtoupper($record['verification_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination no-print">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&bus_id=<?= $busFilter ?>&action=<?= $actionFilter ?>" class="btn btn-secondary">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="page-info">
                                Page <?= $page ?> of <?= $totalPages ?> <span style="color: var(--text-secondary);">(<?= number_format($totalRecords) ?> records)</span>
                            </span>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&bus_id=<?= $busFilter ?>&action=<?= $actionFilter ?>" class="btn btn-secondary">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Print Footer (Only visible when printing) -->
        <div class="print-footer" style="display: none;">
            <div class="signature-grid">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Prepared By</div>
                    <div class="signature-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                    <div class="signature-role">Staff Member</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Verified By</div>
                    <div class="signature-name">_________________</div>
                    <div class="signature-role">Supervisor</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Approved By</div>
                    <div class="signature-name">_________________</div>
                    <div class="signature-role">School Administrator</div>
                </div>
            </div>
            <div class="print-notes">
                <p><strong>Note:</strong> This report is generated from the SelamatRide SmartSchoolBus system. All attendance records are tracked via RFID technology for accuracy and security.</p>
            </div>
        </div>

        <!-- Info Card -->
        <div class="info-card no-print">
            <h3>
                <i class="fas fa-shield-alt"></i> Staff Report Access & Permissions
            </h3>
            <p>
                Staff members have <strong>view-only access</strong> to attendance reports. You can generate, filter, and print comprehensive reports, 
                but cannot modify or delete historical attendance data. All records are <strong>read-only</strong> to maintain system integrity and audit trails. 
                For data corrections, please contact an administrator.
            </p>
        </div>
    </main>

    <script>
        // Export to Excel functionality
        function exportToExcel() {
            // Create a simple CSV export
            let csv = 'Student Name,RFID UID,Bus Number,Action,Date,Time,Status\n';
            
            const table = document.querySelector('.data-table tbody');
            if (!table) {
                alert('No data to export');
                return;
            }
            
            const rows = table.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 6) {
                    const studentName = cells[0].textContent.trim();
                    const rfid = cells[1].textContent.trim();
                    const bus = cells[2].textContent.trim().replace(/\s+/g, ' ');
                    const action = cells[3].textContent.trim().replace(/\s+/g, ' ');
                    const datetime = cells[4].textContent.trim().split('\n');
                    const date = datetime[0].trim();
                    const time = datetime[1] ? datetime[1].trim() : '';
                    const status = cells[5].textContent.trim().replace(/\s+/g, ' ');
                    
                    csv += `"${studentName}","${rfid}","${bus}","${action}","${date}","${time}","${status}"\n`;
                }
            });
            
            // Create download link
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Attendance_Report_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
