<?php
/**
 * SelamatRide SmartSchoolBus - Daily Checklist Summary Report
 * Track morning and evening operational checklist completion
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Daily Checklist Summary";
$currentPage = "reports";

date_default_timezone_set('Asia/Kuala_Lumpur');

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$shiftFilter = isset($_GET['shift']) ? $_GET['shift'] : '';

try {
    $whereClause = "WHERE checklist_date BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];
    
    if (!empty($shiftFilter) && in_array($shiftFilter, ['morning', 'evening'])) {
        $whereClause .= " AND shift_type = ?";
        $params[] = $shiftFilter;
    }
    
    $checklists = $pdo->prepare("
        SELECT 
            dc.*,
            u.full_name as staff_name
        FROM daily_checklists dc
        LEFT JOIN users u ON dc.staff_id = u.user_id
        $whereClause
        ORDER BY dc.checklist_date DESC, dc.shift_type
    ");
    $checklists->execute($params);
    $checklistData = $checklists->fetchAll();
    
    // Calculate statistics
    $totalChecklists = count($checklistData);
    $morningCount = count(array_filter($checklistData, fn($c) => $c['shift_type'] === 'morning'));
    $eveningCount = count(array_filter($checklistData, fn($c) => $c['shift_type'] === 'evening'));
    $allItemsChecked = count(array_filter($checklistData, function($c) {
        return $c['buses_inspected'] && $c['drivers_present'] && 
               $c['rfid_readers_online'] && $c['emergency_kits_checked'];
    }));
    $complianceRate = $totalChecklists > 0 ? round(($allItemsChecked / $totalChecklists) * 100, 1) : 0;
    
} catch (Exception $e) {
    error_log("Checklist Report Error: " . $e->getMessage());
    $checklistData = [];
    $totalChecklists = $morningCount = $eveningCount = $allItemsChecked = $complianceRate = 0;
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
        .report-header {
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .checklist-grid {
            display: grid;
            gap: 20px;
        }
        
        .checklist-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .checklist-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
        
        .checklist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .checklist-date {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .shift-badge {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .shift-badge.morning {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .shift-badge.evening {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .checklist-items {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .check-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .check-icon.checked {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .check-icon.unchecked {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .notes-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .notes-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .notes-text {
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        @media print {
            .no-print { display: none !important; }
            .topbar, .sidebar { display: none !important; }
            body { padding-top: 0 !important; }
            .main-content { 
                margin-left: 0 !important; 
                padding: 15mm !important;
            }
            
            /* Print Header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 3px solid #f59e0b;
            }
            .print-logo {
                font-size: 24px;
                font-weight: 800;
                color: #f59e0b;
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
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 10px !important;
            }
            .stat-card { padding: 12px !important; }
            .stat-icon { width: 32px !important; height: 32px !important; font-size: 16px !important; }
            .stat-value { font-size: 20px !important; }
            .stat-label { font-size: 10px !important; }
            .checklist-card { page-break-inside: avoid; padding: 16px !important; }
            
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
            <div class="print-title">DAILY CHECKLIST SUMMARY</div>
            <div class="print-date">Generated on: <?= date('F d, Y - h:i A') ?></div>
        </div>

        <!-- Print Parameters Grid (Only visible when printing) -->
        <div class="print-params" style="display: none;">
            <div class="param-item">
                <span class="param-label">Report Period:</span>
                <span class="param-value"><?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Shift Filter:</span>
                <span class="param-value"><?= $shiftFilter == 'all' ? 'All Shifts' : ucfirst($shiftFilter) ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Total Checklists:</span>
                <span class="param-value"><?= $totalChecklists ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Compliance Rate:</span>
                <span class="param-value"><?= $complianceRate ?>%</span>
            </div>
        </div>

        <div class="no-print">
            <a href="reports.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); text-decoration: none; margin-bottom: 20px; font-weight: 500;">
                <i class="fas fa-arrow-left"></i> Back to Reports Dashboard
            </a>
        </div>

        <div class="report-header">
            <h1>✅ Daily Checklist Summary</h1>
            <p>Track operational checklist completion and safety compliance</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?= $totalChecklists ?></div>
                <div class="stat-label">Total Checklists</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <i class="fas fa-sun"></i>
                </div>
                <div class="stat-value"><?= $morningCount ?></div>
                <div class="stat-label">Morning Shifts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                    <i class="fas fa-moon"></i>
                </div>
                <div class="stat-value"><?= $eveningCount ?></div>
                <div class="stat-label">Evening Shifts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-value"><?= $complianceRate ?>%</div>
                <div class="stat-label">Compliance Rate</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card no-print">
            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase;">From Date</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase;">To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase;">Shift Type</label>
                        <select name="shift" class="form-control">
                            <option value="">All Shifts</option>
                            <option value="morning" <?= $shiftFilter === 'morning' ? 'selected' : '' ?>>Morning</option>
                            <option value="evening" <?= $shiftFilter === 'evening' ? 'selected' : '' ?>>Evening</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" onclick="window.print()" class="btn" style="background: white; border: 2px solid var(--border-color); color: var(--text-primary);">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Checklists -->
        <div class="checklist-grid">
            <?php if (empty($checklistData)): ?>
                <div style="text-align: center; padding: 60px; color: var(--text-secondary);">
                    <i class="fas fa-clipboard-list" style="font-size: 64px; opacity: 0.3; margin-bottom: 20px;"></i>
                    <h3 style="font-size: 20px; margin: 0 0 8px 0;">No Checklists Found</h3>
                    <p style="margin: 0;">No checklist records found for the selected period.</p>
                </div>
            <?php else: ?>
                <?php foreach ($checklistData as $checklist): ?>
                    <div class="checklist-card">
                        <div class="checklist-header">
                            <div>
                                <div class="checklist-date">
                                    <?= date('F d, Y', strtotime($checklist['checklist_date'])) ?>
                                </div>
                                <div style="font-size: 14px; color: var(--text-secondary); margin-top: 4px;">
                                    Completed by: <?= htmlspecialchars($checklist['staff_name']) ?>
                                </div>
                            </div>
                            <span class="shift-badge <?= $checklist['shift_type'] ?>">
                                <i class="fas fa-<?= $checklist['shift_type'] === 'morning' ? 'sun' : 'moon' ?>"></i>
                                <?= ucfirst($checklist['shift_type']) ?>
                            </span>
                        </div>
                        
                        <div class="checklist-items">
                            <div class="checklist-item">
                                <div class="check-icon <?= $checklist['buses_inspected'] ? 'checked' : 'unchecked' ?>">
                                    <i class="fas fa-<?= $checklist['buses_inspected'] ? 'check' : 'times' ?>"></i>
                                </div>
                                <span>Buses Inspected</span>
                            </div>
                            
                            <div class="checklist-item">
                                <div class="check-icon <?= $checklist['drivers_present'] ? 'checked' : 'unchecked' ?>">
                                    <i class="fas fa-<?= $checklist['drivers_present'] ? 'check' : 'times' ?>"></i>
                                </div>
                                <span>Drivers Present</span>
                            </div>
                            
                            <div class="checklist-item">
                                <div class="check-icon <?= $checklist['rfid_readers_online'] ? 'checked' : 'unchecked' ?>">
                                    <i class="fas fa-<?= $checklist['rfid_readers_online'] ? 'check' : 'times' ?>"></i>
                                </div>
                                <span>RFID Readers Online</span>
                            </div>
                            
                            <div class="checklist-item">
                                <div class="check-icon <?= $checklist['emergency_kits_checked'] ? 'checked' : 'unchecked' ?>">
                                    <i class="fas fa-<?= $checklist['emergency_kits_checked'] ? 'check' : 'times' ?>"></i>
                                </div>
                                <span>Emergency Kits Checked</span>
                            </div>
                        </div>
                        
                        <?php if (!empty($checklist['notes'])): ?>
                            <div class="notes-section">
                                <div class="notes-label"><i class="fas fa-sticky-note"></i> Notes</div>
                                <div class="notes-text"><?= nl2br(htmlspecialchars($checklist['notes'])) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                <p><strong>Note:</strong> This checklist summary is generated from the SelamatRide SmartSchoolBus system. All checklist records are maintained for safety compliance and operational audit purposes.</p>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
