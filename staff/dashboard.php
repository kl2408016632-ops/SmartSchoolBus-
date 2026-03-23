<?php
/**
 * SelamatRide SmartSchoolBus - Staff Dashboard
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Dashboard";
$currentPage = "dashboard";

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Fetch dashboard statistics
$stats = [];
$alerts = [];

try {
    // Total Students
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $stats['total_students'] = $stmt->fetch()['count'];

    // Boarded Today (IN scans)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) as count FROM attendance_records WHERE DATE(timestamp) = CURDATE() AND action = 'boarded'");
    $stats['boarded_today'] = $stmt->fetch()['count'];

    // Dropped Off Today (OUT scans)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) as count FROM attendance_records WHERE DATE(timestamp) = CURDATE() AND action = 'dropped_off'");
    $stats['dropped_off_today'] = $stmt->fetch()['count'];

    // Verified Attendance Records (Today)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance_records WHERE DATE(timestamp) = CURDATE() AND verification_status = 'verified'");
    $stats['verified_records'] = $stmt->fetch()['count'];

    // Error/Unverified Records (Today)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance_records WHERE DATE(timestamp) = CURDATE() AND verification_status IN ('pending', 'flagged')");
    $stats['pending_records'] = $stmt->fetch()['count'];
    
    // Active Buses
    $buses_stmt = $pdo->query("SELECT COUNT(*) as count FROM buses WHERE status = 'active'");
    $stats['active_buses'] = $buses_stmt->fetch()['count'];
    
    // ALERTS - Critical issues that need attention
    
    // Alert 1: Pending verification scans
    if ($stats['pending_records'] > 0) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'exclamation-triangle',
            'title' => 'Pending Verifications',
            'message' => $stats['pending_records'] . ' attendance records need verification',
            'action' => 'attendance.php',
            'action_text' => 'Verify Now'
        ];
    }
    
    // Alert 2: Students without bus assignment
    $no_bus_stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE bus_id IS NULL");
    $no_bus_count = $no_bus_stmt->fetchColumn();
    if ($no_bus_count > 0) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'bus',
            'title' => 'Unassigned Students',
            'message' => $no_bus_count . ' students not assigned to any bus',
            'action' => 'students.php',
            'action_text' => 'Assign Buses'
        ];
    }
    
    // Alert 3: Morning checklist not completed
    $checklist_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM daily_checklists 
        WHERE checklist_date = CURDATE() AND shift_type = 'morning' AND staff_id = ?
    ");
    $checklist_stmt->execute([$_SESSION['user_id']]);
    if ($checklist_stmt->fetchColumn() == 0) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'clipboard-check',
            'title' => 'Morning Checklist Pending',
            'message' => 'You haven\'t completed today\'s morning checklist',
            'action' => 'daily_checklist.php',
            'action_text' => 'Complete Now'
        ];
    }

    // Recent Attendance (Today - Last 15 records)
    $recentAttendance = $pdo->query("
        SELECT 
            ar.record_id,
            ar.timestamp,
            ar.action,
            ar.verification_status,
            s.student_name,
            s.rfid_uid,
            s.photo_url,
            b.bus_number
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.student_id
        JOIN buses b ON ar.bus_id = b.bus_id
        WHERE DATE(ar.timestamp) = CURDATE()
        ORDER BY ar.timestamp DESC
        LIMIT 15
    ")->fetchAll();

} catch (Exception $e) {
    error_log("Staff Dashboard Error: " . $e->getMessage());
    $stats = array_fill_keys(['total_students', 'boarded_today', 'dropped_off_today', 'verified_records', 'pending_records', 'active_buses'], 0);
    $recentAttendance = [];
    $alerts = [];
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
        /* Professional Dashboard Styling */
        .dashboard-welcome {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
        }
        
        .dashboard-welcome h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .dashboard-welcome p {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .alert-section {
            margin-bottom: 24px;
        }
        
        .alert-card {
            background: var(--card-bg);
            border-left: 4px solid;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s;
        }
        
        .alert-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .alert-card.warning {
            border-left-color: #f59e0b;
            background: rgba(245, 158, 11, 0.05);
        }
        
        .alert-card.danger {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }
        
        .alert-card.info {
            border-left-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }
        
        .alert-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .alert-card.warning .alert-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .alert-card.danger .alert-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .alert-card.info .alert-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }
        
        .alert-message {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .alert-action {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .alert-card.warning .alert-action {
            background: #f59e0b;
            color: white;
        }
        
        .alert-card.danger .alert-action {
            background: #ef4444;
            color: white;
        }
        
        .alert-card.info .alert-action {
            background: #3b82f6;
            color: white;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .quick-action-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .quick-action-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-color);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .quick-action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .quick-action-card.blue .quick-action-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .quick-action-card.green .quick-action-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .quick-action-card.orange .quick-action-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .quick-action-card.red .quick-action-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .quick-action-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Welcome Header -->
        <div class="dashboard-welcome">
            <h1>
                <i class="fas fa-hand-sparkles" style="color: #fbbf24;"></i>
                Welcome Back, <?= htmlspecialchars(getCurrentUser()['full_name']) ?>!
            </h1>
            <p>Ready to manage today's bus operations</p>
        </div>

        <!-- Alerts Section -->
        <?php if (!empty($alerts)): ?>
            <div class="alert-section">
                <h3 style="font-size: 16px; margin-bottom: 16px; color: var(--text-primary);">
                    <i class="fas fa-bell"></i> Alerts & Action Items (<?= count($alerts) ?>)
                </h3>
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert-card <?= $alert['type'] ?>">
                        <div class="alert-icon">
                            <i class="fas fa-<?= $alert['icon'] ?>"></i>
                        </div>
                        <div class="alert-content">
                            <h4 class="alert-title"><?= $alert['title'] ?></h4>
                            <p class="alert-message"><?= $alert['message'] ?></p>
                        </div>
                        <a href="<?= SITE_URL ?>/staff/<?= $alert['action'] ?>" class="alert-action">
                            <?= $alert['action_text'] ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <h3 style="font-size: 16px; margin-bottom: 16px; color: var(--text-primary);">
            <i class="fas fa-bolt"></i> Quick Actions
        </h3>
        <div class="quick-actions-grid">
            <a href="<?= SITE_URL ?>/staff/daily_checklist.php" class="quick-action-card blue">
                <div class="quick-action-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h4 class="quick-action-title">Daily Checklist</h4>
            </a>
            
            <a href="<?= SITE_URL ?>/staff/attendance.php" class="quick-action-card green">
                <div class="quick-action-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h4 class="quick-action-title">Verify Scans</h4>
            </a>
            
            <a href="<?= SITE_URL ?>/staff/students.php" class="quick-action-card orange">
                <div class="quick-action-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h4 class="quick-action-title">Manage Students</h4>
            </a>
            
            <a href="<?= SITE_URL ?>/staff/incidents.php" class="quick-action-card red">
                <div class="quick-action-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h4 class="quick-action-title">Report Incident</h4>
            </a>
        </div>

        <!-- Statistics Cards -->
        <h3 style="font-size: 16px; margin: 32px 0 16px; color: var(--text-primary);">
            <i class="fas fa-chart-line"></i> Today's Overview
        </h3>
        <div class="stats-grid">
            <!-- Boarded Today -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Boarded Today</span>
                    <div class="stat-card-icon green">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['boarded_today']) ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Students IN</div>
            </div>

            <!-- Dropped Off Today -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Dropped Off Today</span>
                    <div class="stat-card-icon orange">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['dropped_off_today']) ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Students OUT</div>
            </div>

            <!-- Verified Records -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Verified Scans</span>
                    <div class="stat-card-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['verified_records']) ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Confirmed</div>
            </div>

            <!-- Pending Records -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Pending Verify</span>
                    <div class="stat-card-icon warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['pending_records']) ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Needs Review</div>
            </div>
            
            <!-- Total Students -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Students</span>
                    <div class="stat-card-icon blue">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['total_students']) ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Registered</div>
            </div>
            
            <!-- Active Buses -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Active Buses</span>
                    <div class="stat-card-icon purple">
                        <i class="fas fa-bus"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['active_buses']) ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">In Service</div>
            </div>
        </div>

        <!-- Real-Time Attendance Activity -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-clock"></i> Recent Bus Activity
                    <span style="font-size: 14px; font-weight: 400; color: var(--success); margin-left: 12px;">
                        <i class="fas fa-circle" style="font-size: 8px; animation: pulse 2s infinite;"></i> Live
                    </span>
                </h2>
                <a href="<?= SITE_URL ?>/staff/attendance.php" style="color: var(--primary-color); text-decoration: none; font-size: 14px; font-weight: 600;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-container">
                <?php if (empty($recentAttendance)): ?>
                    <div style="text-align: center; padding: 64px 24px;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-qrcode" style="font-size: 36px; color: var(--primary-color); opacity: 0.5;"></i>
                        </div>
                        <h3 style="font-size: 18px; font-weight: 600; margin: 0 0 8px 0; color: var(--text-primary);">No Activity Yet</h3>
                        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                            RFID scans will appear here in real-time
                        </p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user"></i> Student</th>
                                <th><i class="fas fa-id-card"></i> RFID UID</th>
                                <th><i class="fas fa-bus"></i> Bus</th>
                                <th><i class="fas fa-exchange-alt"></i> Type</th>
                                <th><i class="fas fa-clock"></i> Time</th>
                                <th><i class="fas fa-check-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttendance as $record): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <?php 
                                            $avatar_path = null;
                                            $photo_url = trim((string)($record['photo_url'] ?? ''));
                                            if (!empty($photo_url)) {
                                                $relative_path = ltrim($photo_url, '/');
                                                if (file_exists("../" . $relative_path)) {
                                                    $avatar_path = SITE_URL . "/" . $relative_path;
                                                }
                                            }
                                            ?>
                                            <?php if ($avatar_path): ?>
                                                <img src="<?= $avatar_path ?>" 
                                                     style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(59, 130, 246, 0.3);" 
                                                     alt="<?= htmlspecialchars($record['student_name']) ?>">
                                            <?php else: ?>
                                                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;">
                                                    <?= strtoupper(substr($record['student_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <span style="font-weight: 600;"><?= htmlspecialchars($record['student_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <code style="font-size: 13px; background: var(--content-bg); padding: 6px 12px; border-radius: 6px; color: var(--primary-color); font-weight: 600;">
                                            <?= htmlspecialchars($record['rfid_uid']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="badge badge-outline" style="font-weight: 600;">
                                            <i class="fas fa-bus"></i> <?= htmlspecialchars($record['bus_number']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $record['action'] === 'boarded' ? 'success' : 'warning' ?>" style="font-weight: 700; font-size: 12px;">
                                            <i class="fas fa-<?= $record['action'] === 'boarded' ? 'sign-in-alt' : 'sign-out-alt' ?>"></i>
                                            <?= $record['action'] === 'boarded' ? 'IN' : 'OUT' ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 600; color: var(--text-secondary);">
                                        <?= date('h:i A', strtotime($record['timestamp'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $record['verification_status'] === 'verified' ? 'success' : 'danger' ?>" style="font-size: 12px;">
                                            <i class="fas fa-<?= $record['verification_status'] === 'verified' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                                            <?= ucfirst($record['verification_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
    
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
    
    <script>
        // Auto-refresh every 30 seconds to show latest data
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
