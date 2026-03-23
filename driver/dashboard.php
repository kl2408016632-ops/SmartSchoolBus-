<?php
/**
 * SelamatRide SmartSchoolBus
 * Driver Dashboard
 */
require_once '../config.php';
requireRole(['driver']);

$pageTitle = "Dashboard";
$currentPage = "dashboard";

date_default_timezone_set('Asia/Kuala_Lumpur');

// Fetch driver's bus information
$busStmt = $pdo->prepare("
    SELECT 
        b.*,
        COUNT(s.student_id) as student_count
    FROM buses b
    LEFT JOIN students s ON b.bus_id = s.bus_id AND s.status = 'active'
    WHERE b.assigned_driver_id = ?
    GROUP BY b.bus_id
");
$busStmt->execute([$_SESSION['user_id']]);
$bus = $busStmt->fetch();

$stats = ['boarded' => 0, 'dropped' => 0, 'total_students' => 0];

if ($bus) {
    // Total students assigned to this bus
    $stats['total_students'] = $bus['student_count'];
    
    // Boarded Today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM attendance_records 
        WHERE bus_id = ? AND DATE(timestamp) = CURDATE() AND action = 'boarded'
    ");
    $stmt->execute([$bus['bus_id']]);
    $stats['boarded'] = $stmt->fetch()['count'];

    // Dropped Off Today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM attendance_records 
        WHERE bus_id = ? AND DATE(timestamp) = CURDATE() AND action = 'dropped_off'
    ");
    $stmt->execute([$bus['bus_id']]);
    $stats['dropped'] = $stmt->fetch()['count'];

    // Fetch today's records for this bus
    $recordsStmt = $pdo->prepare("
        SELECT 
            ar.timestamp,
            s.student_name,
            ar.action,
            ar.rfid_uid
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.student_id
        WHERE ar.bus_id = ? AND DATE(ar.timestamp) = CURDATE()
        ORDER BY ar.timestamp DESC
        LIMIT 20
    ");
    $recordsStmt->execute([$bus['bus_id']]);
    $records = $recordsStmt->fetchAll();
} else {
    $records = [];
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
        /* Driver Dashboard - Large readable UI */
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
        }
        
        .dashboard-welcome p {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .bus-info-card {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 16px;
            padding: 32px;
            color: white;
            margin-bottom: 32px;
        }
        
        .bus-info-card h2 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px 0;
        }
        
        .bus-number {
            font-size: 48px;
            font-weight: 800;
            margin: 16px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .recent-activity-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #10b981;
            font-size: 14px;
            font-weight: 600;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: rgba(59, 130, 246, 0.05);
        }
        
        .data-table th {
            padding: 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        
        .data-table td {
            padding: 16px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.03);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .badge-info {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }
    </style>
</head>
<body>
    <?php include 'includes/driver_header.php'; ?>
    <?php include 'includes/driver_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Welcome Header -->
        <div class="dashboard-welcome">
            <h1>
                <i class="fas fa-hand-sparkles" style="color: #fbbf24;"></i>
                Welcome Back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?>!
            </h1>
            <p>Ready to manage today's bus operations</p>
        </div>
        
        <?php if ($bus): ?>
            <!-- Bus Assignment Card -->
            <div class="bus-info-card">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <div>
                        <h2>🚌 Your Assigned Bus</h2>
                        <div class="bus-number"><?= htmlspecialchars($bus['bus_number']) ?></div>
                        <p style="font-size: 16px; opacity: 0.9; margin: 0;">
                            Capacity: <?= $bus['student_count'] ?>/<?= $bus['capacity'] ?> Students
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Bus Status</div>
                        <span class="badge" style="background: rgba(255,255,255,0.2); color: white; font-size: 14px; padding: 8px 16px;">
                            <i class="fas fa-check-circle"></i> <?= ucfirst($bus['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Today's Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= $stats['total_students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-value"><?= $stats['boarded'] ?></div>
                    <div class="stat-label">Boarded Today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-value"><?= $stats['dropped'] ?></div>
                    <div class="stat-label">Dropped Off Today</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity-card">
                <div class="card-header">
                    <h2><i class="fas fa-clock-rotate-left"></i> Today's RFID Scan Records</h2>
                    <div class="live-indicator">
                        <div class="live-dot"></div>
                        <span>Live</span>
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Student Name</th>
                                <th>Action</th>
                                <th>RFID UID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($records) > 0): ?>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><strong><?= date('h:i A', strtotime($record['timestamp'])) ?></strong></td>
                                        <td><?= htmlspecialchars($record['student_name']) ?></td>
                                        <td>
                                            <span class="badge <?= $record['action'] === 'boarded' ? 'badge-success' : 'badge-info' ?>">
                                                <i class="fas fa-arrow-<?= $record['action'] === 'boarded' ? 'up' : 'down' ?>"></i>
                                                <?= ucfirst(str_replace('_', ' ', $record['action'])) ?>
                                            </span>
                                        </td>
                                        <td><code><?= htmlspecialchars($record['rfid_uid']) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 60px 20px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px; display: block;"></i>
                                        <div style="color: var(--text-secondary); font-size: 16px;">No scan records for today yet</div>
                                        <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">RFID scans will appear here automatically</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- No Bus Assigned -->
            <div class="recent-activity-card" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-bus" style="font-size: 64px; color: #d1d5db; margin-bottom: 24px; display: block;"></i>
                <h2 style="color: var(--text-primary); margin-bottom: 12px; font-size: 24px;">No Bus Assigned</h2>
                <p style="color: var(--text-secondary); font-size: 16px; max-width: 500px; margin: 0 auto;">Please contact the school administrator to assign a bus to your driver account.</p>
            </div>
        <?php endif; ?>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
    
    <script>
        // Auto-refresh every 30 seconds for live updates
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
