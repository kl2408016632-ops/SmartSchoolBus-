<?php
/**
 * SelamatRide SmartSchoolBus - Payment Management System
 * Production-Grade Monthly Fee Tracking (Staff)
 */
require_once '../config.php';
requireRole(['staff']);

// Add CSRF token to session
$_SESSION['csrf_token'] = csrfToken();

$pageTitle = "Payment Management";
$currentPage = "payments";

function extractProofPathFromNotes(?string $notes): ?string {
    if (empty($notes)) {
        return null;
    }

    if (preg_match('/\[proof:([^\]]+)\]/', $notes, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function mergeProofPathIntoNotes(?string $notes, string $proofPath): string {
    $cleanNotes = trim((string)preg_replace('/\s*\[proof:[^\]]+\]\s*/', ' ', (string)$notes));
    return trim($cleanNotes . ' [proof:' . $proofPath . ']');
}

// Get current month and year
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

// Handle payment status update with proof of payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Security validation failed. Please try again.';
        header("Location: " . SITE_URL . "/staff/payments.php");
        exit;
    }
    
    try {
        $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $month = isset($_POST['month']) ? max(1, min(12, (int)$_POST['month'])) : (int)date('n');
        $year = isset($_POST['year']) ? max(2000, (int)$_POST['year']) : (int)date('Y');
        $status = in_array($_POST['status'] ?? '', ['completed', 'pending', 'failed']) ? $_POST['status'] : 'pending';
        $amount = (float)($_POST['amount'] ?? 0);
        $monthPaidFor = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        if ($student_id && $amount >= 0) {
            $proofFilePath = null;
            
            // Handle proof of payment file upload
            if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['proof_of_payment'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'payment_' . $student_id . '_' . $month . '_' . $year . '_' . time() . '.' . $extension;
                    $uploadPath = '../uploads/payment_proofs/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $proofFilePath = 'uploads/payment_proofs/' . $filename;
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid file. Only JPG, PNG, PDF allowed (max 5MB).";
                    header("Location: " . SITE_URL . "/staff/payments.php?month=$month&year=$year");
                    exit;
                }
            }
            
            // Check if payment record exists
            $checkStmt = $pdo->prepare("SELECT payment_id, notes FROM payments WHERE student_id = ? AND month_paid_for = ?");
            $checkStmt->execute([$student_id, $monthPaidFor]);
            $existingPayment = $checkStmt->fetch();
            $notesToSave = $existingPayment['notes'] ?? null;

            if ($proofFilePath) {
                $notesToSave = mergeProofPathIntoNotes($notesToSave, $proofFilePath);
            }
            
            if ($existingPayment) {
                // Update existing record
                $sql = "UPDATE payments 
                        SET status = ?, 
                            amount = ?,
                            payment_date = CURDATE(),
                            month_paid_for = ?,
                            recorded_by = ?,
                            notes = ?";
                $params = [$status, $amount, $monthPaidFor, $_SESSION['user_id'] ?? null, $notesToSave];
                
                $sql .= " WHERE payment_id = ?";
                $params[] = $existingPayment['payment_id'];
                
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute($params);
            } else {
                // Insert new record
                $insertStmt = $pdo->prepare("
                    INSERT INTO payments (student_id, amount, payment_date, month_paid_for, status, recorded_by, notes)
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?)
                ");
                $insertStmt->execute([$student_id, $amount, $monthPaidFor, $status, $_SESSION['user_id'] ?? null, $notesToSave]);
            }
            
            $_SESSION['success_message'] = "Payment status updated successfully.";
            header("Location: " . SITE_URL . "/staff/payments.php?month=$month&year=$year");
            exit;
        }
    } catch (Exception $e) {
        error_log("Payment Update Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update payment status.";
    }
}

// Get filter parameters
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$filterBus = isset($_GET['bus']) ? (int)$_GET['bus'] : 0;
$filterStudent = isset($_GET['student']) ? trim($_GET['student']) : '';
$filterParent = isset($_GET['parent']) ? trim($_GET['parent']) : '';

// Fetch all buses for filter
try {
    $buses = $pdo->query("SELECT bus_id, bus_number FROM buses ORDER BY bus_number")->fetchAll();
} catch (Exception $e) {
    error_log("Bus Fetch Error: " . $e->getMessage());
    $buses = [];
}

// Fetch students with payment status
try {
    $sql = "
        SELECT 
            s.student_id,
            s.student_name,
            s.photo_url,
            s.bus_id,
            b.bus_number,
            p.parent_name,
            p.phone_primary,
            pay.payment_id,
            pay.status as payment_status,
            pay.amount,
            pay.payment_date,
                pay.notes AS payment_notes
        FROM students s
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        LEFT JOIN buses b ON s.bus_id = b.bus_id
        LEFT JOIN payments pay ON s.student_id = pay.student_id 
                AND pay.month_paid_for = ?
    ";
    
            $params = [date('F Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear))];
    
            $whereClauses = ["s.status = 'active'"];
    if ($filterBus > 0) {
        $whereClauses[] = "s.bus_id = ?";
        $params[] = $filterBus;
    }
    
    if (!empty($filterStudent)) {
        $whereClauses[] = "s.student_name LIKE ?";
        $params[] = "%{$filterStudent}%";
    }
    
    if (!empty($filterParent)) {
        $whereClauses[] = "p.parent_name LIKE ?";
        $params[] = "%{$filterParent}%";
    }
    
    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }
    
    $sql .= " ORDER BY s.student_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Payment Fetch Error: " . $e->getMessage());
    $students = [];
}

// Calculate statistics
$totalStudents = count($students);
$paidCount = 0;
$unpaidCount = 0;
$totalAmount = 0;

foreach ($students as $student) {
    if ($student['payment_status'] === 'completed') {
        $paidCount++;
        $totalAmount += $student['amount'];
    } else {
        $unpaidCount++;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
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
        
        .stat-icon.paid {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .stat-icon.unpaid {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .stat-icon.total {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }
        
        .stat-icon.amount {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
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
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: visible;
            position: relative;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .student-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
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
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-secondary {
            background: var(--content-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .btn-link {
            background: none;
            color: var(--primary-color);
            border: none;
            padding: 8px;
            font-size: 16px;
        }
        
        .btn-link:hover {
            color: var(--primary-dark);
            background: rgba(59, 130, 246, 0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Dropdown Menu Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .dropdown-toggle:hover {
            background: var(--primary-dark);
        }
        
        .dropdown-toggle i.fa-chevron-down {
            font-size: 10px;
            transition: transform 0.2s;
        }
        
        .dropdown.active .dropdown-toggle i.fa-chevron-down {
            transform: rotate(180deg);
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 4px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 180px;
            z-index: 1000;
            overflow: hidden;
        }
        
        .dropdown.dropup .dropdown-menu {
            top: auto;
            bottom: 100%;
            margin-top: 0;
            margin-bottom: 4px;
        }
        
        .dropdown.active .dropdown-menu {
            display: block;
        }
        
        .dropdown-menu a,
        .dropdown-menu button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: var(--text-primary);
            text-decoration: none;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .dropdown-menu a:hover,
        .dropdown-menu button:hover {
            background: var(--content-bg);
        }
        
        .dropdown-menu a i,
        .dropdown-menu button i {
            width: 16px;
            text-align: center;
        }
        
        .dropdown-menu .delete {
            color: var(--danger);
        }
        
        .dropdown-menu .delete:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background: var(--content-bg);
            color: var(--text-primary);
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
                <h1><i class="fas fa-money-check-alt"></i> Payment Management</h1>
                <p>Track monthly bus fee payments for all students</p>
            </div>
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
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon paid">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?= $paidCount ?></div>
                <div class="stat-label">Paid Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon unpaid">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value"><?= $unpaidCount ?></div>
                <div class="stat-label">Unpaid Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= $totalStudents ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon amount">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">RM <?= number_format($totalAmount, 2) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <h3 style="margin: 0 0 20px 0; font-size: 16px; color: var(--text-primary);">
                <i class="fas fa-filter"></i> Filter Payments
            </h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Month</label>
                        <select name="month" class="form-control">
                            <?php
                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                            for ($i = 1; $i <= 12; $i++):
                            ?>
                                <option value="<?= $i ?>" <?= $filterMonth == $i ? 'selected' : '' ?>>
                                    <?= $months[$i-1] ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" class="form-control">
                            <?php for ($y = $currentYear - 1; $y <= $currentYear + 1; $y++): ?>
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
                    
                    <div class="form-group">
                        <label>Student Name</label>
                        <input type="text" name="student" class="form-control" placeholder="Search student..." value="<?= htmlspecialchars($filterStudent) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Parent Name</label>
                        <input type="text" name="parent" class="form-control" placeholder="Search parent..." value="<?= htmlspecialchars($filterParent) ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Apply
                        </button>
                    </div>
                    
                    <?php if ($filterBus > 0 || !empty($filterStudent) || !empty($filterParent)): ?>
                    <div class="form-group">
                        <a href="?month=<?= $filterMonth ?>&year=<?= $filterYear ?>" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Payment Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Avatar</th>
                        <th>Student Name</th>
                        <th>Bus</th>
                        <th>Parent</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Last Payment</th>
                        <th>Proof</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 48px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 16px; display: block;"></i>
                                No students found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rowNum = 1;
                        foreach ($students as $student): 
                            $status = $student['payment_status'] ?? 'no-record';
                            $amount = $student['amount'] ?? 150.00;
                            $proofPath = extractProofPathFromNotes($student['payment_notes'] ?? null);
                        ?>
                            <tr>
                                <td><strong>#<?= $rowNum++ ?></strong></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($student['photo_url'])): ?>
                                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($student['photo_url']) ?>" class="student-avatar" alt="">
                                    <?php else: ?>
                                        <div class="student-avatar-placeholder">
                                            <?= strtoupper(substr($student['student_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($student['student_name']) ?></strong></td>
                                <td><?= htmlspecialchars($student['bus_number'] ?? 'Not assigned') ?></td>
                                <td><?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($student['phone_primary'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $student['payment_id'] ? ($status === 'completed' ? 'paid' : ($status === 'failed' ? 'failed' : 'pending')) : 'pending' ?>">
                                        <?php
                                            if ($student['payment_id']) {
                                                if ($status === 'completed') echo '✓ Completed';
                                                elseif ($status === 'failed') echo '✗ Failed';
                                                else echo '⧗ Pending';
                                            } else {
                                                echo '⧗ No Record';
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td>RM <?= number_format($amount, 2) ?></td>
                                <td><?= !empty($student['payment_date']) ? date('d M Y', strtotime($student['payment_date'])) : 'Never' ?></td>
                                <td>
                                    <?php if (!empty($proofPath)): ?>
                                        <a href="<?= SITE_URL ?>/<?= htmlspecialchars($proofPath) ?>" target="_blank" class="btn btn-link" title="View Proof">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 12px;">No proof</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                            Actions <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <button onclick="viewHistory(<?= $student['student_id'] ?>)">
                                                <i class="fas fa-history"></i> View History
                                            </button>
                                            <?php if (!$student['payment_id'] || $status !== 'completed'): ?>
                                                <button onclick="updatePayment(<?= $student['student_id'] ?>, '<?= htmlspecialchars($student['student_name'], ENT_QUOTES) ?>', 'completed', <?= $amount ?>)">
                                                    <i class="fas fa-check"></i> Mark as Completed
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="editPayment(<?= $student['student_id'] ?>, '<?= htmlspecialchars($student['student_name'], ENT_QUOTES) ?>', <?= $amount ?>, '<?= htmlspecialchars($proofPath ?? '', ENT_QUOTES) ?>', <?= $student['payment_id'] ?? 0 ?>)">
                                                <i class="fas fa-edit"></i> Edit Payment
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Payment Proof Upload Modal -->
    <div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; font-size: 20px;">
                    <i class="fas fa-file-invoice"></i> Mark Payment as Paid
                </h3>
                <button onclick="closePaymentModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="paymentForm" method="POST" action="" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="update_payment" value="1">
                <input type="hidden" name="student_id" id="modal_student_id">
                <input type="hidden" name="month" value="<?= $filterMonth ?>">
                <input type="hidden" name="year" value="<?= $filterYear ?>">
                <input type="hidden" name="status" value="completed">
                <input type="hidden" name="amount" id="modal_amount">
                
                <div style="margin-bottom: 20px;">
                    <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 15px;">
                        Student: <strong id="modal_student_name" style="color: var(--text-primary);"></strong><br>
                        Month: <strong><?= date('F Y', mktime(0,0,0,$filterMonth,1,$filterYear)) ?></strong>
                    </p>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-paperclip"></i> Proof of Payment (Optional)
                    </label>
                    <input type="file" name="proof_of_payment" id="proof_of_payment" accept="image/jpeg,image/jpg,image/png,application/pdf" class="form-control" style="padding: 10px;">
                    <small style="display: block; margin-top: 8px; color: var(--text-secondary); font-size: 12px;">
                        Accepted: JPG, PNG, PDF (Max 5MB)
                    </small>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closePaymentModal()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Mark as Paid
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div id="editPaymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; font-size: 20px;">
                    <i class="fas fa-edit"></i> Edit Payment
                </h3>
                <button onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="editPaymentForm" method="POST" action="" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="update_payment" value="1">
                <input type="hidden" name="student_id" id="edit_student_id">
                <input type="hidden" name="month" value="<?= $filterMonth ?>">
                <input type="hidden" name="year" value="<?= $filterYear ?>">
                <input type="hidden" name="status" value="completed">
                <input type="hidden" id="edit_payment_id">
                
                <div style="margin-bottom: 20px;">
                    <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 15px;">
                        Student: <strong id="edit_student_name" style="color: var(--text-primary);"></strong><br>
                        Month: <strong><?= date('F Y', mktime(0,0,0,$filterMonth,1,$filterYear)) ?></strong>
                    </p>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-money-bill"></i> Amount (RM)
                    </label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" min="0" class="form-control" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-paperclip"></i> Proof of Payment
                    </label>
                    <div id="current_proof" style="margin-bottom: 10px;"></div>
                    <input type="file" name="proof_of_payment" id="edit_proof_of_payment" accept="image/jpeg,image/jpg,image/png,application/pdf" class="form-control" style="padding: 10px;">
                    <small style="display: block; margin-top: 8px; color: var(--text-secondary); font-size: 12px;">
                        Upload new file to replace existing proof. Accepted: JPG, PNG, PDF (Max 5MB)
                    </small>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
    
    <script>
        // Dropdown toggle
        function toggleDropdown(button) {
            const dropdown = button.parentElement;
            const isActive = dropdown.classList.contains('active');
            
            // Close all dropdowns
            document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
            
            // Toggle current dropdown
            if (!isActive) {
                dropdown.classList.add('active');
                
                // Check if dropdown should open upward
                const rect = dropdown.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;
                
                // If not enough space below but enough space above, open upward
                if (spaceBelow < 200 && spaceAbove > 200) {
                    dropdown.classList.add('dropup');
                } else {
                    dropdown.classList.remove('dropup');
                }
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
            }
        });
        
        function updatePayment(studentId, studentName, newStatus, amount) {
            // Close dropdown
            document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
            
            if (newStatus === 'completed') {
                // Open modal for paid status to allow proof upload
                openPaymentModal(studentId, studentName, amount);
            } else {
                // Direct submission for unpaid status
                const confirmMsg = `Are you sure you want to mark ${studentName} as UNPAID for <?= date('F Y', mktime(0,0,0,$filterMonth,1,$filterYear)) ?>?`;
                
                if (confirm(confirmMsg)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    form.innerHTML = `
                        <input type="hidden" name="update_payment" value="1">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                        <input type="hidden" name="student_id" value="${studentId}">
                        <input type="hidden" name="month" value="<?= $filterMonth ?>">
                        <input type="hidden" name="year" value="<?= $filterYear ?>">
                        <input type="hidden" name="status" value="${newStatus}">
                        <input type="hidden" name="amount" value="${amount}">
                    `;
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        
        function openPaymentModal(studentId, studentName, amount) {
            document.getElementById('modal_student_id').value = studentId;
            document.getElementById('modal_student_name').textContent = studentName;
            document.getElementById('modal_amount').value = amount;
            document.getElementById('paymentModal').style.display = 'flex';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('paymentForm').reset();
        }
        
        function editPayment(studentId, studentName, amount, proofPath, paymentId) {
            // Close dropdown
            document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
            
            document.getElementById('edit_student_id').value = studentId;
            document.getElementById('edit_student_name').textContent = studentName;
            document.getElementById('edit_amount').value = amount || 150.00;
            document.getElementById('edit_payment_id').value = paymentId;
            
            const currentProofDiv = document.getElementById('current_proof');
            if (proofPath) {
                currentProofDiv.innerHTML = `
                    <div style="background: var(--content-bg); padding: 10px; border-radius: 6px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-invoice" style="color: var(--primary-color);"></i>
                        <span style="flex: 1; font-size: 13px;">Current proof uploaded</span>
                        <a href="<?= SITE_URL ?>/${proofPath}" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                            <i class="fas fa-external-link-alt"></i> View
                        </a>
                    </div>
                `;
            } else {
                currentProofDiv.innerHTML = `<small style="color: var(--text-secondary);">No proof uploaded yet</small>`;
            }
            
            document.getElementById('editPaymentModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editPaymentModal').style.display = 'none';
            document.getElementById('editPaymentForm').reset();
        }
        
        // Close modal on outside click
        document.getElementById('paymentModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
        
        document.getElementById('editPaymentModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        function viewHistory(studentId) {
            // Close dropdown
            document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
            window.location.href = '<?= SITE_URL ?>/staff/payment_history.php?student_id=' + studentId;
        }
    </script>
</body>
</html>
