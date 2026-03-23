<?php
/**
 * SelamatRide SmartSchoolBus - Reports Dashboard
 * Production-Grade IoT SaaS System
 * Admin Reports & Analytics Module
 */
require_once '../config.php';
requireRole(['admin']);

$pageTitle = "Reports & Analytics";
$currentPage = "reports";

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$busFilter = isset($_GET['bus_id']) ? (int)$_GET['bus_id'] : 0;
$studentFilter = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$scanTypeFilter = isset($_GET['scan_type']) ? $_GET['scan_type'] : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$where = ["DATE(ar.scan_time) BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($busFilter > 0) {
    $where[] = "ar.bus_id = ?";
    $params[] = $busFilter;
}

if ($studentFilter > 0) {
    $where[] = "ar.student_id = ?";
    $params[] = $studentFilter;
}

if (!empty($scanTypeFilter) && in_array($scanTypeFilter, ['IN', 'OUT'])) {
    $where[] = "ar.scan_type = ?";
    $params[] = $scanTypeFilter;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get total count for pagination
try {
    $countQuery = "SELECT COUNT(*) FROM attendance_records ar {$whereClause}";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);
} catch (Exception $e) {
    error_log("Count Error: " . $e->getMessage());
    $totalRecords = 0;
    $totalPages = 0;
}

// Get attendance records with all details
try {
    $query = "
        SELECT 
            ar.*,
            s.full_name as student_name,
            s.rfid_uid as student_rfid,
            b.bus_number,
            b.plate_number
        FROM attendance_records ar
        LEFT JOIN students s ON ar.student_id = s.student_id
        LEFT JOIN buses b ON ar.bus_id = b.bus_id
        {$whereClause}
        ORDER BY ar.scan_time DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Reports Query Error: " . $e->getMessage());
    $records = [];
}

// Get statistics for the filtered period
try {
    $statsQuery = "
        SELECT 
            COUNT(*) as total_scans,
            SUM(CASE WHEN scan_type = 'IN' THEN 1 ELSE 0 END) as total_in,
            SUM(CASE WHEN scan_type = 'OUT' THEN 1 ELSE 0 END) as total_out,
            COUNT(DISTINCT student_id) as unique_students,
            COUNT(DISTINCT bus_id) as unique_buses
        FROM attendance_records ar
        {$whereClause}
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Stats Error: " . $e->getMessage());
    $stats = [
        'total_scans' => 0,
        'total_in' => 0,
        'total_out' => 0,
        'unique_students' => 0,
        'unique_buses' => 0
    ];
}

// Get buses for filter dropdown
try {
    $busesStmt = $pdo->query("SELECT bus_id, bus_number, plate_number FROM buses WHERE status = 'active' ORDER BY bus_number");
    $buses = $busesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $buses = [];
}

// Get students for filter dropdown
try {
    $studentsStmt = $pdo->query("SELECT student_id, full_name, rfid_uid FROM students WHERE status = 'active' ORDER BY full_name");
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $students = [];
}

// Build print URL with current filters
$printParams = http_build_query([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'bus_id' => $busFilter,
    'student_id' => $studentFilter,
    'scan_type' => $scanTypeFilter
]);
$printUrl = "print_report.php?" . $printParams;
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
        .filter-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            background: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-secondary {
            background: var(--content-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.blue {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .stat-icon.green {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-icon.red {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .stat-icon.orange {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .stat-content h4 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .stat-content p {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 4px 0 0 0;
        }

        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
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
            border-bottom: 1px solid var(--border-color);
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

        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .scan-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .scan-badge.in {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .scan-badge.out {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            padding: 24px;
            border-top: 1px solid var(--border-color);
        }

        .pagination a {
            padding: 8px 12px;
            background: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination a.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
                <p>Attendance records and system analytics</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" action="reports.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" required>
                    </div>

                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" required>
                    </div>

                    <div class="filter-group">
                        <label><i class="fas fa-bus"></i> Bus</label>
                        <select name="bus_id">
                            <option value="0">All Buses</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?= $bus['bus_id'] ?>" <?= $busFilter == $bus['bus_id'] ? 'selected' : '' ?>>
                                    Bus <?= htmlspecialchars($bus['bus_number']) ?> - <?= htmlspecialchars($bus['plate_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><i class="fas fa-user-graduate"></i> Student</label>
                        <select name="student_id">
                            <option value="0">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['student_id'] ?>" <?= $studentFilter == $student['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Scan Type</label>
                        <select name="scan_type">
                            <option value="">All Types</option>
                            <option value="IN" <?= $scanTypeFilter === 'IN' ? 'selected' : '' ?>>IN</option>
                            <option value="OUT" <?= $scanTypeFilter === 'OUT' ? 'selected' : '' ?>>OUT</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                    <a href="<?= $printUrl ?>" target="_blank" class="btn btn-success">
                        <i class="fas fa-print"></i> Print Report
                    </a>
                    <a href="reports.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon blue">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <h4><?= number_format($stats['total_scans']) ?></h4>
                    <p>Total Scans</p>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon green">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="stat-content">
                    <h4><?= number_format($stats['total_in']) ?></h4>
                    <p>Check-In (IN)</p>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon red">
                    <i class="fas fa-arrow-left"></i>
                </div>
                <div class="stat-content">
                    <h4><?= number_format($stats['total_out']) ?></h4>
                    <p>Check-Out (OUT)</p>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h4><?= number_format($stats['unique_students']) ?></h4>
                    <p>Unique Students</p>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="table-container">
            <?php if (empty($records)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p style="font-size: 18px; margin-bottom: 8px;">No records found</p>
                    <p style="font-size: 14px;">Try adjusting your filters</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Student</th>
                            <th style="width: 150px;">RFID UID</th>
                            <th style="width: 120px;">Bus</th>
                            <th style="width: 80px;">Type</th>
                            <th style="width: 180px;">Scan Time</th>
                            <th style="width: 120px;">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        foreach ($records as $record): 
                            $initials = '';
                            if (!empty($record['student_name'])) {
                                $nameParts = explode(' ', $record['student_name']);
                                $initials = strtoupper(substr($nameParts[0], 0, 1));
                                if (count($nameParts) > 1) {
                                    $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
                                }
                            }
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar"><?= $initials ?></div>
                                        <div>
                                            <div style="font-weight: 500;">
                                                <?= htmlspecialchars($record['student_name'] ?? 'Unknown') ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                ID: <?= $record['student_id'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: var(--content-bg); padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                        <?= htmlspecialchars($record['rfid_uid']) ?>
                                    </code>
                                </td>
                                <td>
                                    <div style="font-weight: 500;">Bus <?= htmlspecialchars($record['bus_number'] ?? 'N/A') ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?= htmlspecialchars($record['plate_number'] ?? '') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="scan-badge <?= strtolower($record['scan_type']) ?>">
                                        <?= htmlspecialchars($record['scan_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= date('d M Y', strtotime($record['scan_time'])) ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?= date('h:i:s A', strtotime($record['scan_time'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 13px;">
                                        <?= htmlspecialchars($record['recorded_by']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $baseUrl = 'reports.php?' . http_build_query($queryParams) . ($queryParams ? '&' : '');
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?= $baseUrl ?>page=1">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="<?= $baseUrl ?>page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="<?= $baseUrl ?>page=<?= $totalPages ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align: center; padding: 0 24px 24px; color: var(--text-secondary); font-size: 14px;">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalRecords) ?> of <?= number_format($totalRecords) ?> records
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
