<?php
/**
 * SelamatRide SmartSchoolBus - Student Roster Report
 * Complete student directory with contact information
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Student Roster Report";
$currentPage = "reports";

// Get filter parameters
$filterBus = isset($_GET['bus']) ? (int)$_GET['bus'] : 0;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'active';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $whereClause = "WHERE s.status = ?";
    $params = [$filterStatus];
    
    if ($filterBus > 0) {
        $whereClause .= " AND s.bus_id = ?";
        $params[] = $filterBus;
    }
    
    if (!empty($search)) {
        $whereClause .= " AND (s.student_name LIKE ? OR s.rfid_uid LIKE ? OR p.parent_name LIKE ? OR b.bus_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $students = $pdo->prepare("
        SELECT 
            s.*,
            p.parent_name,
            p.phone_primary,
            p.phone_secondary,
            p.email as parent_email,
            p.address as parent_address,
            b.bus_number,
            u.full_name as driver_name
        FROM students s
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        LEFT JOIN buses b ON s.bus_id = b.bus_id
        LEFT JOIN users u ON b.assigned_driver_id = u.user_id
        $whereClause
        ORDER BY b.bus_number, s.student_name
    ");
    $students->execute($params);
    $studentList = $students->fetchAll();
    
    $buses = $pdo->query("SELECT bus_id, bus_number FROM buses WHERE status = 'active' ORDER BY bus_number")->fetchAll();
    
} catch (Exception $e) {
    error_log("Student Roster Error: " . $e->getMessage());
    $studentList = [];
    $buses = [];
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .student-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .student-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
            border-color: #8b5cf6;
        }
        
        .student-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .student-photo {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            object-fit: cover;
        }
        
        .student-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }
        
        .student-rfid {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #8b5cf6;
            background: rgba(139, 92, 246, 0.1);
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .info-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .info-content strong {
            display: block;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }
        
        @media print {
            .no-print { display: none !important; }
            .topbar, .sidebar { display: none !important; }
            body { padding-top: 0 !important; }
            .main-content { 
                margin-left: 0 !important; 
                padding: 12mm !important;
                max-width: 100% !important;
            }
            
            /* Print Header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 3px solid #8b5cf6;
            }
            .print-logo {
                font-size: 24px;
                font-weight: 800;
                color: #8b5cf6;
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
            
            .student-grid { 
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
            }
            .student-card { 
                padding: 10px !important;
                page-break-inside: avoid;
                border: 1px solid #ddd !important;
            }
            .student-header {
                margin-bottom: 10px !important;
                padding-bottom: 10px !important;
            }
            .student-photo {
                width: 48px !important;
                height: 48px !important;
            }
            .student-name {
                font-size: 14px !important;
                margin-bottom: 3px !important;
            }
            .student-rfid {
                font-size: 9px !important;
                padding: 2px 6px !important;
            }
            .info-row {
                margin-bottom: 6px !important;
                font-size: 10px !important;
            }
            .info-icon {
                width: 24px !important;
                height: 24px !important;
                font-size: 12px !important;
            }
            .info-content strong {
                font-size: 9px !important;
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
                size: A4 portrait;
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
            <div class="print-title">STUDENT ROSTER REPORT</div>
            <div class="print-date">Generated on: <?= date('F d, Y - h:i A') ?></div>
        </div>

        <!-- Print Parameters Grid (Only visible when printing) -->
        <div class="print-params" style="display: none;">
            <div class="param-item">
                <span class="param-label">Total Students:</span>
                <span class="param-value"><?= $totalStudents ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Search Filter:</span>
                <span class="param-value"><?= !empty($searchFilter) ? htmlspecialchars($searchFilter) : 'None' ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Bus Filter:</span>
                <span class="param-value"><?= $busFilter > 0 ? htmlspecialchars($selectedBusNumber ?? 'Unknown') : 'All Buses' ?></span>
            </div>
            <div class="param-item">
                <span class="param-label">Status Filter:</span>
                <span class="param-value"><?= ucfirst($statusFilter) ?></span>
            </div>
        </div>

        <div class="no-print">
            <a href="reports.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); text-decoration: none; margin-bottom: 20px; font-weight: 500;">
                <i class="fas fa-arrow-left"></i> Back to Reports Dashboard
            </a>
        </div>

        <div class="report-header">
            <h1>👥 Student Roster Report</h1>
            <p>Complete student directory with contact information and emergency details</p>
        </div>

        <div class="filter-card no-print">
            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase;">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, RFID, parent, or bus..." class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase;">Bus</label>
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
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase;">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" onclick="window.print()" class="btn" style="background: white; border: 2px solid var(--border-color); color: var(--text-primary);">
                        <i class="fas fa-print"></i> Print Roster
                    </button>
                    <?php if (!empty($search) || $filterBus > 0 || $filterStatus !== 'active'): ?>
                        <a href="report_students.php" class="btn" style="background: #ef4444; color: white; text-decoration: none;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div style="margin-bottom: 16px; color: var(--text-secondary); font-size: 14px;">
            <strong>Total Students:</strong> <?= count($studentList) ?>
        </div>

        <div class="student-grid">
            <?php foreach ($studentList as $student): ?>
                <div class="student-card">
                    <div class="student-header">
                        <?php 
                        $photoPath = !empty($student['photo_url']) ? SITE_URL . '/' . $student['photo_url'] : SITE_URL . '/assets/images/default-avatar.png';
                        ?>
                        <img src="<?= htmlspecialchars($photoPath) ?>" 
                             alt="<?= htmlspecialchars($student['student_name']) ?>" 
                             class="student-photo">
                        <div>
                            <h3 class="student-name"><?= htmlspecialchars($student['student_name']) ?></h3>
                            <span class="student-rfid">RFID: <?= htmlspecialchars($student['rfid_uid']) ?></span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-bus"></i></div>
                        <div class="info-content">
                            <strong>Bus Assignment</strong>
                            <?= htmlspecialchars($student['bus_number'] ?: 'Not Assigned') ?>
                            <?php if ($student['driver_name']): ?>
                                <br><span style="font-size: 12px; color: var(--text-secondary);">Driver: <?= htmlspecialchars($student['driver_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-user"></i></div>
                        <div class="info-content">
                            <strong>Parent/Guardian</strong>
                            <?= htmlspecialchars($student['parent_name'] ?: 'Not Available') ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-phone"></i></div>
                        <div class="info-content">
                            <strong>Contact</strong>
                            <?= htmlspecialchars($student['phone_primary'] ?: $student['emergency_contact'] ?: 'Not Available') ?>
                            <?php if ($student['phone_secondary']): ?>
                                <br><?= htmlspecialchars($student['phone_secondary']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($student['parent_email']): ?>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div class="info-content">
                            <strong>Email</strong>
                            <?= htmlspecialchars($student['parent_email']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($student['date_of_birth']): ?>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-birthday-cake"></i></div>
                        <div class="info-content">
                            <strong>Date of Birth</strong>
                            <?= date('M d, Y', strtotime($student['date_of_birth'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
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
                <p><strong>Note:</strong> This roster is generated from the SelamatRide SmartSchoolBus system. All student information is confidential and should be handled according to data privacy policies.</p>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
