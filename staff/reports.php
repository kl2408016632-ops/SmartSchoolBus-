<?php
/**
 * SelamatRide SmartSchoolBus - Staff Reports Dashboard
 * Comprehensive Reporting System for Staff Operations
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Reports Dashboard";
$currentPage = "reports";

date_default_timezone_set('Asia/Kuala_Lumpur');

// Get quick stats for dashboard
try {
    // Attendance stats
    $attendanceStats = $pdo->query("
        SELECT 
            COUNT(*) as total_today,
            COUNT(DISTINCT student_id) as unique_students
        FROM attendance_records 
        WHERE DATE(timestamp) = CURDATE()
    ")->fetch();
    
    // Payment stats for current month
    $paymentStats = $pdo->query("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count
        FROM payments 
        WHERE month = MONTH(CURDATE()) AND year = YEAR(CURDATE())
    ")->fetch();
    
    // Student stats
    $studentStats = $pdo->query("
        SELECT 
            COUNT(*) as total_students,
            COUNT(DISTINCT bus_id) as total_buses
        FROM students 
        WHERE status = 'active'
    ")->fetch();
    
    // Checklist completion for today
    $checklistStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as completed_today
        FROM daily_checklists 
        WHERE checklist_date = CURDATE() AND staff_id = ?
    ");
    $checklistStmt->execute([$_SESSION['user_id']]);
    $checklistStats = $checklistStmt->fetch();
    
} catch (Exception $e) {
    error_log("Dashboard Stats Error: " . $e->getMessage());
    $attendanceStats = ['total_today' => 0, 'unique_students' => 0];
    $paymentStats = ['total_payments' => 0, 'paid_count' => 0, 'unpaid_count' => 0];
    $studentStats = ['total_students' => 0, 'total_buses' => 0];
    $checklistStats = ['completed_today' => 0];
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
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 48px;
            margin-bottom: 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .dashboard-header h1 {
            font-size: 42px;
            font-weight: 800;
            margin: 0 0 12px 0;
            position: relative;
            z-index: 1;
        }
        
        .dashboard-header p {
            font-size: 18px;
            margin: 0;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .quick-stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }
        
        .quick-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .quick-stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        
        .quick-stat-icon.blue {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .quick-stat-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .quick-stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .quick-stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .quick-stat-content h3 {
            font-size: 32px;
            font-weight: 800;
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }
        
        .quick-stat-content p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
            font-weight: 500;
        }
        
        .section-header {
            margin-bottom: 24px;
        }
        
        .section-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .section-header p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .report-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 32px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--accent-gradient);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s;
        }
        
        .report-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15);
            border-color: var(--accent-color);
        }
        
        .report-card:hover::before {
            transform: scaleX(1);
        }
        
        .report-card.blue {
            --accent-color: #3b82f6;
            --accent-gradient: linear-gradient(90deg, #3b82f6, #2563eb);
        }
        
        .report-card.green {
            --accent-color: #10b981;
            --accent-gradient: linear-gradient(90deg, #10b981, #059669);
        }
        
        .report-card.orange {
            --accent-color: #f59e0b;
            --accent-gradient: linear-gradient(90deg, #f59e0b, #d97706);
        }
        
        .report-card.purple {
            --accent-color: #8b5cf6;
            --accent-gradient: linear-gradient(90deg, #8b5cf6, #7c3aed);
        }
        
        .report-card.red {
            --accent-color: #ef4444;
            --accent-gradient: linear-gradient(90deg, #ef4444, #dc2626);
        }
        
        .report-icon {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
            background: var(--accent-gradient);
            color: white;
        }
        
        .report-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .report-card p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0 0 20px 0;
        }
        
        .report-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .report-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .report-meta-item i {
            color: var(--accent-color);
        }
        
        .info-banner {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(102, 126, 234, 0.1));
            border: 2px solid rgba(59, 130, 246, 0.3);
            border-radius: 16px;
            padding: 24px 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .info-banner-icon {
            font-size: 32px;
            color: #3b82f6;
        }
        
        .info-banner-content h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 6px 0;
        }
        
        .info-banner-content p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>📊 Reports Dashboard</h1>
            <p>Generate comprehensive reports for attendance, payments, students, and operations</p>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-stat-card">
                <div class="quick-stat-icon blue">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="quick-stat-content">
                    <h3><?= $attendanceStats['total_today'] ?></h3>
                    <p>Scans Today</p>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon green">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="quick-stat-content">
                    <h3><?= $paymentStats['paid_count'] ?>/<?= $paymentStats['total_payments'] ?></h3>
                    <p>Payments This Month</p>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon orange">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="quick-stat-content">
                    <h3><?= $studentStats['total_students'] ?></h3>
                    <p>Active Students</p>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon purple">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="quick-stat-content">
                    <h3><?= $checklistStats['completed_today'] ?>/2</h3>
                    <p>Checklists Today</p>
                </div>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="info-banner">
            <div class="info-banner-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="info-banner-content">
                <h3>Staff Access Level - View Only</h3>
                <p>You have view-only access to these reports. All data is read-only to maintain system integrity. For modifications or additional report types, please contact an administrator.</p>
            </div>
        </div>

        <!-- Reports Section -->
        <div class="section-header">
            <h2>Available Reports</h2>
            <p>Select a report type to generate detailed insights and export data</p>
        </div>

        <div class="reports-grid">
            <!-- 1. Daily Attendance Report -->
            <a href="report_attendance.php" class="report-card blue">
                <div class="report-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3>Daily Attendance Report</h3>
                <p>View RFID scan records with boarding and drop-off times, verification status, and student details by date range and bus.</p>
                <div class="report-meta">
                    <div class="report-meta-item">
                        <i class="fas fa-clock"></i>
                        <span>Real-time</span>
                    </div>
                    <div class="report-meta-item">
                        <i class="fas fa-file-export"></i>
                        <span>Exportable</span>
                    </div>
                </div>
            </a>

            <!-- 2. Payment Collection Report -->
            <a href="report_payments.php" class="report-card green">
                <div class="report-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h3>Payment Collection Report</h3>
                <p>Monthly payment status summary with collection rates, outstanding balances, and proof of payment attachments by bus or student.</p>
                <div class="report-meta">
                    <div class="report-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Monthly</span>
                    </div>
                    <div class="report-meta-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
                    </div>
                </div>
            </a>

            <!-- 3. Student Roster Report -->
            <a href="report_students.php" class="report-card purple">
                <div class="report-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Student Roster Report</h3>
                <p>Complete student directory with parent contacts, emergency information, bus assignments, RFID UIDs, and enrollment status.</p>
                <div class="report-meta">
                    <div class="report-meta-item">
                        <i class="fas fa-address-book"></i>
                        <span>Directory</span>
                    </div>
                    <div class="report-meta-item">
                        <i class="fas fa-print"></i>
                        <span>Printable</span>
                    </div>
                </div>
            </a>

            <!-- 4. Daily Checklist Summary -->
            <a href="report_checklists.php" class="report-card orange">
                <div class="report-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3>Daily Checklist Summary</h3>
                <p>Track morning and evening operational checklist completion, staff compliance, and safety item verification over time.</p>
                <div class="report-meta">
                    <div class="report-meta-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Safety</span>
                    </div>
                    <div class="report-meta-item">
                        <i class="fas fa-check-double"></i>
                        <span>Compliance</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Help Section -->
        <div class="info-banner" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(124, 58, 237, 0.1)); border-color: rgba(139, 92, 246, 0.3);">
            <div class="info-banner-icon" style="color: #8b5cf6;">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="info-banner-content">
                <h3>Need Help?</h3>
                <p>Click any report card above to generate that specific report. Use filters to customize date ranges, buses, and other parameters. All reports can be printed or exported to Excel for further analysis.</p>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
