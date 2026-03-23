<?php
/**
 * SelamatRide SmartSchoolBus - Payment Collection Report
 * Monthly payment status and collection analysis
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Payment Collection Report";
$currentPage = "reports";

date_default_timezone_set('Asia/Kuala_Lumpur');

// Get filter parameters
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filterBus = isset($_GET['bus']) ? (int)$_GET['bus'] : 0;

try {
    // Get payment summary - show all active students with their payment status
    $whereConditions = ["s.status = 'active'"];
    $params = [];
    
    if ($filterBus > 0) {
        $whereConditions[] = "s.bus_id = ?";
        $params[] = $filterBus;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    // Add month and year params for the LEFT JOIN
    $joinParams = array_merge([$filterMonth, $filterYear], $params);
    
    $paymentData = $pdo->prepare("
        SELECT 
            s.student_id,
            s.student_name,
            s.photo_url,
            b.bus_number,
            pa.parent_name,
            pa.phone_primary,
            COALESCE(p.amount, 150.00) as amount,
            COALESCE(p.status, 'unpaid') as status,
            p.payment_date,
            p.proof_of_payment
        FROM students s
        LEFT JOIN parents pa ON s.parent_id = pa.parent_id
        LEFT JOIN buses b ON s.bus_id = b.bus_id
        LEFT JOIN payments p ON s.student_id = p.student_id AND p.month = ? AND p.year = ?
        $whereClause
        ORDER BY b.bus_number, s.student_name
    ");
    $paymentData->execute($joinParams);
    $payments = $paymentData->fetchAll();
    
    // Calculate statistics
    $totalStudents = count($payments);
    $paidCount = count(array_filter($payments, fn($p) => $p['status'] === 'paid'));
    $unpaidCount = $totalStudents - $paidCount;
    $totalCollected = array_sum(array_map(fn($p) => $p['status'] === 'paid' ? $p['amount'] : 0, $payments));
    $totalOutstanding = array_sum(array_map(fn($p) => $p['status'] === 'unpaid' ? $p['amount'] : 0, $payments));
    $collectionRate = $totalStudents > 0 ? round(($paidCount / $totalStudents) * 100, 1) : 0;
    
    // Get buses for filter
    $buses = $pdo->query("SELECT bus_id, bus_number FROM buses WHERE status = 'active' ORDER BY bus_number")->fetchAll();
    
} catch (Exception $e) {
    error_log("Payment Report Error: " . $e->getMessage());
    $payments = [];
    $buses = [];
    $totalStudents = $paidCount = $unpaidCount = $totalCollected = $totalOutstanding = $collectionRate = 0;
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
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
        .report-header {
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 32px;
            color: white;
        }
        
        .report-header h1 {
            font-size: 36px;
            font-weight: 800;
            margin: 0 0 8px 0;
        }
        
        .report-header p {
            font-size: 16px;
            margin: 0;
            opacity: 0.95;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
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
        
        .filter-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }
        
        .btn-secondary {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .data-table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: rgba(16, 185, 129, 0.05);
        }
        
        .data-table thead th {
            padding: 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        
        .data-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }
        
        .data-table tbody tr:hover {
            background: rgba(16, 185, 129, 0.03);
        }
        
        .data-table tbody td {
            padding: 16px;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .student-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .badge.danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        @media print {
            .no-print { display: none !important; }
            .topbar, .sidebar { display: none !important; }
            body { padding-top: 0 !important; }
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
                border-bottom: 3px solid #10b981;
            }
            .print-logo {
                font-size: 24px;
                font-weight: 800;
                color: #10b981;
                margin-bottom: 5px;
            }
            .print-tagline {
                font-size: 11px;
                color: #6b7280;
                margin-bottom: 12px;
            }
            .print-title {
                font-size: 20px;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 5px;
            }
            .print-date {
                font-size: 10px;
                color: #6b7280;
            }
            
            /* Print Parameters Grid */
            .print-params {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                background: #f3f4f6;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            .param-item {
                display: flex;
                gap: 8px;
                font-size: 10px;
            }
            .param-label {
                font-weight: 600;
                color: #4b5563;
            }
            .param-value {
                color: #1f2937;
            }
            
            /* Regular report header hidden in print */
            .report-header {
                display: none !important;
            }
            
            .stats-grid {
                grid-template-columns: repeat(5, 1fr) !important;
                gap: 10px !important;
                margin-bottom: 15px !important;
                page-break-after: avoid;
            }
            .stat-card {
                padding: 12px !important;
                page-break-inside: avoid;
            }
            .stat-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
                margin-bottom: 8px !important;
            }
            .stat-value {
                font-size: 20px !important;
                margin-bottom: 2px !important;
            }
            .stat-label {
                font-size: 10px !important;
            }
            .data-table-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .data-table {
                font-size: 9px !important;
            }
            .data-table thead th {
                padding: 8px 6px !important;
                font-size: 9px !important;
                background: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .data-table tbody td {
                padding: 8px 6px !important;
                font-size: 9px !important;
            }
            .student-avatar {
                width: 28px !important;
                height: 28px !important;
            }
            .badge {
                padding: 3px 6px !important;
                font-size: 8px !important;
            }
            .student-cell {
                gap: 6px !important;
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
                border-top: 1px solid #000;
                margin: 40px 20px 8px 20px;
            }
            .signature-label {
                font-size: 10px;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 4px;
            }
            .signature-name {
                font-size: 9px;
                color: #4b5563;
                margin-bottom: 2px;
            }
            .signature-role {
                font-size: 9px;
                color: #6b7280;
            }
            .print-notes {
                font-size: 9px;
                color: #6b7280;
                text-align: center;
                padding-top: 10px;
                border-top: 1px solid #e5e7eb;
            }
            .print-notes strong {
                color: #1f2937;
            }
            
            @page {
                size: A4 landscape;
                margin: 10mm;
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
            <div class="print-title">PAYMENT COLLECTION REPORT</div>
            <div class="print-date">Generated on: <?= date('F d, Y - h:i A') ?></div>
        </div>

        <!-- Print Parameters Grid (Only visible when printing) -->
        <div class="print-params" style="display: none;">
            <div class="param-item">
                <span class="param-label">Report Period:</span>
                <span class="param-value"><?= $months[$filterMonth] ?> <?= $filterYear ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Bus Filter:</span>
                <span class="param-value"><?php
                    if ($filterBus > 0) {
                        $selectedBus = array_filter($buses, fn($b) => $b['bus_id'] == $filterBus);
                        $selectedBus = reset($selectedBus);
                        echo htmlspecialchars($selectedBus['bus_number'] ?? 'Unknown');
                    } else {
                        echo 'All Buses';
                    }
                ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Total Students:</span>
                <span class="param-value"><?= $totalStudents ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Collection Rate:</span>
                <span class="param-value"><?= $collectionRate ?>%</span>
            </div>
        </div>

        <div class="no-print">
            <a href="reports.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); text-decoration: none; margin-bottom: 20px; font-weight: 500;">
                <i class="fas fa-arrow-left"></i> Back to Reports Dashboard
            </a>
        </div>

        <div class="report-header">
            <h1>💰 Payment Collection Report</h1>
            <p>Monthly payment status and collection analysis for <?= $months[$filterMonth] ?> <?= $filterYear ?></p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?= $paidCount ?></div>
                <div class="stat-label">Paid Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value"><?= $unpaidCount ?></div>
                <div class="stat-label">Unpaid Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value"><?= $collectionRate ?>%</div>
                <div class="stat-label">Collection Rate</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value">RM <?= number_format($totalCollected, 2) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-value">RM <?= number_format($totalOutstanding, 2) ?></div>
                <div class="stat-label">Outstanding</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card no-print">
            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Month</label>
                        <select name="month" class="form-control">
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $filterMonth == $num ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" class="form-control">
                            <?php for ($y = 2024; $y <= 2027; $y++): ?>
                                <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Bus</label>
                        <select name="bus" class="form-control">
                            <option value="0">All Buses</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?= $bus['bus_id'] ?>" <?= $filterBus == $bus['bus_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bus['bus_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Data Table -->
        <div class="data-table-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Bus</th>
                            <th>Parent Name</th>
                            <th>Contact</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                                    No payment records found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <div class="student-cell">
                                            <?php 
                                            $photoPath = !empty($payment['photo_url']) ? SITE_URL . '/' . $payment['photo_url'] : SITE_URL . '/assets/images/default-avatar.png';
                                            ?>
                                            <img src="<?= htmlspecialchars($photoPath) ?>" 
                                                 alt="<?= htmlspecialchars($payment['student_name']) ?>" 
                                                 class="student-avatar">
                                            <strong><?= htmlspecialchars($payment['student_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($payment['bus_number'] ?: 'Not Assigned') ?></td>
                                    <td><?= htmlspecialchars($payment['parent_name'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($payment['phone_primary'] ?: '-') ?></td>
                                    <td><strong>RM <?= number_format($payment['amount'], 2) ?></strong></td>
                                    <td>
                                        <?php if ($payment['status'] === 'paid'): ?>
                                            <span class="badge success">
                                                <i class="fas fa-check"></i> Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="badge danger">
                                                <i class="fas fa-times"></i> Unpaid
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                <p><strong>Note:</strong> This payment report is generated from the SelamatRide SmartSchoolBus system. All payment records are tracked for financial transparency and audit purposes.</p>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
