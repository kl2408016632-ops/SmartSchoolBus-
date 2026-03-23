<?php
/**
 * SelamatRide SmartSchoolBus - Payment History
 * View complete payment history for a student
 */
require_once '../config.php';
requireRole(['admin', 'staff']);

$pageTitle = "Payment History";
$currentPage = "payments";

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($studentId <= 0) {
    header('Location: ' . SITE_URL . '/' . $_SESSION['role_name'] . '/payments.php');
    exit;
}

// Fetch student details
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
        header('Location: ' . SITE_URL . '/' . $_SESSION['role_name'] . '/payments.php?error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Student Fetch Error: " . $e->getMessage());
    header('Location: ' . SITE_URL . '/' . $_SESSION['role_name'] . '/payments.php?error=database');
    exit;
}

// Fetch payment history
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.full_name as recorded_by_name
        FROM payments p
        LEFT JOIN users u ON p.recorded_by = u.user_id
        WHERE p.student_id = ?
            ORDER BY p.payment_date DESC, p.payment_id DESC
    ");
    $stmt->execute([$studentId]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Payment History Error: " . $e->getMessage());
    $payments = [];
}

// Calculate statistics
$totalPaid = 0;
$totalAmount = 0;
foreach ($payments as $payment) {
    if ($payment['status'] === 'completed') {
        $totalPaid++;
        $totalAmount += $payment['amount'];
    }
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
        .student-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .student-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }
        
        .student-avatar-placeholder-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 32px;
        }
        
        .student-info h2 {
            margin: 0 0 8px 0;
            font-size: 28px;
            color: var(--text-primary);
        }
        
        .student-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .student-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-box-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-box-label {
            font-size: 13px;
            color: var(--text-secondary);
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
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.paid {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-badge.unpaid {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
            .status-badge.pending {
                background: rgba(245, 158, 11, 0.15);
                color: #b45309;
            }
        
            .status-badge.failed {
                background: rgba(239, 68, 68, 0.15);
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
        
        .btn-secondary {
            background: var(--content-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
    </style>
</head>
<body>
    <?php if ($_SESSION['role_name'] === 'admin'): ?>
        <?php include '../admin/includes/admin_header.php'; ?>
        <?php include '../admin/includes/admin_sidebar.php'; ?>
    <?php else: ?>
        <?php include '../staff/includes/staff_header.php'; ?>
        <?php include '../staff/includes/staff_sidebar.php'; ?>
    <?php endif; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-history"></i> Payment History</h1>
                <p>Complete payment record for student</p>
            </div>
            <a href="<?= SITE_URL ?>/<?= $_SESSION['role_name'] ?>/payments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Payments
            </a>
        </div>

        <!-- Student Card -->
        <div class="student-card">
            <?php if (!empty($student['photo_url'])): ?>
                <img src="<?= SITE_URL ?>/<?= htmlspecialchars($student['photo_url']) ?>" class="student-avatar-large" alt="">
            <?php else: ?>
                <div class="student-avatar-placeholder-large">
                    <?= strtoupper(substr($student['student_name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            
            <div class="student-info">
                <h2><?= htmlspecialchars($student['student_name']) ?></h2>
                <div class="student-meta">
                    <div class="student-meta-item">
                        <i class="fas fa-bus"></i>
                        <span><?= htmlspecialchars($student['bus_number'] ?? 'No bus assigned') ?></span>
                    </div>
                    <div class="student-meta-item">
                        <i class="fas fa-user"></i>
                        <span><?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="student-meta-item">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($student['phone_primary'] ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-value" style="color: var(--success);"><?= $totalPaid ?></div>
                <div class="stat-box-label">Months Paid</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-value" style="color: var(--primary-color);"><?= count($payments) ?></div>
                <div class="stat-box-label">Total Records</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-value" style="color: #f59e0b;">RM <?= number_format($totalAmount, 2) ?></div>
                <div class="stat-box-label">Total Amount Paid</div>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="table-container">
            <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                <h3 style="margin: 0; font-size: 18px; color: var(--text-primary);">
                    <i class="fas fa-receipt"></i> Payment Records
                </h3>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Month/Year</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Proof</th>
                        <th>Recorded By</th>
                        <th>Recorded Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 16px; display: block;"></i>
                                No payment records found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($payment['month_paid_for'] ?? ($payment['payment_date'] ? date('F Y', strtotime($payment['payment_date'])) : '-')) ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($payment['status']) ?>">
                                        <?php
                                            if ($payment['status'] === 'completed') {
                                                echo '✓ Completed';
                                            } elseif ($payment['status'] === 'pending') {
                                                echo '⧗ Pending';
                                            } else {
                                                echo '✗ Failed';
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td><strong>RM <?= number_format($payment['amount'], 2) ?></strong></td>
                                <td><?= $payment['payment_date'] ? date('d M Y', strtotime($payment['payment_date'])) : '-' ?></td>
                                <td>
                                    <?php if (!empty($payment['proof_of_payment'])): ?>
                                        <a href="<?= SITE_URL ?>/<?= htmlspecialchars($payment['proof_of_payment']) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                            <i class="fas fa-file-invoice"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($payment['recorded_by_name'] ?? 'System') ?></td>
                                <td><?= date('d M Y H:i', strtotime($payment['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    
    <?php if ($_SESSION['role_name'] === 'staff'): ?>
        <?php include '../staff/includes/logout_feedback_interceptor.php'; ?>
    <?php endif; ?>
</body>
</html>
