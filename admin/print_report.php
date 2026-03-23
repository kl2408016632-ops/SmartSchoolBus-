<?php
/**
 * SelamatRide SmartSchoolBus - Print Report
 * Production-Grade IoT SaaS System
 * Printer-Friendly Report Page
 */
require_once '../config.php';
requireRole(['admin', 'staff']);

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$busFilter = isset($_GET['bus_id']) ? (int)$_GET['bus_id'] : 0;
$studentFilter = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$scanTypeFilter = isset($_GET['scan_type']) ? $_GET['scan_type'] : '';

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

// Get attendance records (no pagination for print)
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
        LIMIT 500
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Print Report Error: " . $e->getMessage());
    $records = [];
}

// Get statistics
try {
    $statsQuery = "
        SELECT 
            COUNT(*) as total_scans,
            SUM(CASE WHEN scan_type = 'IN' THEN 1 ELSE 0 END) as total_in,
            SUM(CASE WHEN scan_type = 'OUT' THEN 1 ELSE 0 END) as total_out,
            COUNT(DISTINCT student_id) as unique_students
        FROM attendance_records ar
        {$whereClause}
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_scans' => 0,
        'total_in' => 0,
        'total_out' => 0,
        'unique_students' => 0
    ];
}

// Get filter names for report header
$busName = "All Buses";
$studentName = "All Students";

if ($busFilter > 0) {
    $busStmt = $pdo->prepare("SELECT bus_number, plate_number FROM buses WHERE bus_id = ?");
    $busStmt->execute([$busFilter]);
    $busData = $busStmt->fetch(PDO::FETCH_ASSOC);
    if ($busData) {
        $busName = "Bus " . $busData['bus_number'] . " - " . $busData['plate_number'];
    }
}

if ($studentFilter > 0) {
    $studentStmt = $pdo->prepare("SELECT full_name FROM students WHERE student_id = ?");
    $studentStmt->execute([$studentFilter]);
    $studentData = $studentStmt->fetch(PDO::FETCH_ASSOC);
    if ($studentData) {
        $studentName = $studentData['full_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - <?= SITE_NAME ?></title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: white;
            color: #000;
            padding: 20px;
        }

        .report-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3b82f6;
        }

        .company-logo {
            font-size: 32px;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 5px;
        }

        .company-tagline {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .report-title {
            font-size: 24px;
            font-weight: 700;
            color: #000;
            margin-top: 15px;
        }

        .report-date {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Report Info */
        .report-info {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .info-row {
            display: flex;
            font-size: 13px;
        }

        .info-label {
            font-weight: 600;
            width: 120px;
        }

        .info-value {
            color: #666;
        }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #000;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }

        thead {
            background: #3b82f6;
            color: white;
        }

        thead th {
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        tbody td {
            padding: 10px 8px;
        }

        .scan-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }

        .scan-badge.in {
            background: #d1fae5;
            color: #065f46;
        }

        .scan-badge.out {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Footer */
        .report-footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            font-size: 11px;
            color: #666;
        }

        .signature-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 50px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 11px;
        }

        /* Print Styles */
        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .report-container {
                max-width: 100%;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }

            .report-footer {
                page-break-before: avoid;
            }

            @page {
                margin: 1cm;
            }
        }

        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .print-button:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button onclick="window.print()" class="print-button no-print">
        🖨️ Print Report
    </button>

    <div class="report-container">
        <!-- Header -->
        <div class="report-header">
            <div class="company-logo">🚌 SelamatRide SmartSchoolBus</div>
            <div class="company-tagline">Secure RFID Student Boarding Verification System</div>
            <div class="report-title">ATTENDANCE REPORT</div>
            <div class="report-date">Generated on: <?= date('d F Y, h:i A') ?></div>
        </div>

        <!-- Report Info -->
        <div class="report-info">
            <div class="info-row">
                <span class="info-label">Report Period:</span>
                <span class="info-value"><?= date('d M Y', strtotime($dateFrom)) ?> to <?= date('d M Y', strtotime($dateTo)) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Bus:</span>
                <span class="info-value"><?= htmlspecialchars($busName) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Student:</span>
                <span class="info-value"><?= htmlspecialchars($studentName) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Scan Type:</span>
                <span class="info-value"><?= $scanTypeFilter ? htmlspecialchars($scanTypeFilter) : 'All Types' ?></span>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value"><?= number_format($stats['total_scans']) ?></div>
                <div class="stat-label">Total Scans</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: #059669;"><?= number_format($stats['total_in']) ?></div>
                <div class="stat-label">Check-In</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: #dc2626;"><?= number_format($stats['total_out']) ?></div>
                <div class="stat-label">Check-Out</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: #f59e0b;"><?= number_format($stats['unique_students']) ?></div>
                <div class="stat-label">Unique Students</div>
            </div>
        </div>

        <!-- Data Table -->
        <?php if (empty($records)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <p style="font-size: 18px;">No records found for the selected criteria</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Student Name</th>
                        <th style="width: 130px;">RFID UID</th>
                        <th style="width: 100px;">Bus</th>
                        <th style="width: 60px;">Type</th>
                        <th style="width: 140px;">Scan Time</th>
                        <th style="width: 100px;">Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($records as $record): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($record['student_name'] ?? 'Unknown') ?></strong><br>
                                <small style="color: #666;">ID: <?= $record['student_id'] ?></small>
                            </td>
                            <td>
                                <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?= htmlspecialchars($record['rfid_uid']) ?>
                                </code>
                            </td>
                            <td>
                                Bus <?= htmlspecialchars($record['bus_number'] ?? 'N/A') ?><br>
                                <small style="color: #666;"><?= htmlspecialchars($record['plate_number'] ?? '') ?></small>
                            </td>
                            <td>
                                <span class="scan-badge <?= strtolower($record['scan_type']) ?>">
                                    <?= htmlspecialchars($record['scan_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d M Y', strtotime($record['scan_time'])) ?><br>
                                <small style="color: #666;"><?= date('h:i:s A', strtotime($record['scan_time'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($record['recorded_by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Footer -->
        <div class="report-footer">
            <p><strong>Note:</strong> This is a computer-generated report from SelamatRide SmartSchoolBus System.</p>
            <p>Report limited to 500 most recent records for printing purposes.</p>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">Prepared By</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Verified By</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Approved By</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-trigger print dialog after page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
