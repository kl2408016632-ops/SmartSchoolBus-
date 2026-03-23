<?php
/**
 * SelamatRide SmartSchoolBus - Admin Dashboard
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']);

// Fetch dashboard statistics
$stats = [];

try {
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['total_users'] = $stmt->fetch()['count'];

    // Total Drivers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id = 3 AND status = 'active'");
    $stats['total_drivers'] = $stmt->fetch()['count'];

    // Total Staff
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND status = 'active'");
    $stats['total_staff'] = $stmt->fetch()['count'];

    // Total Buses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM buses WHERE status = 'active'");
    $stats['total_buses'] = $stmt->fetch()['count'];

    // Total Students
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
    $stats['total_students'] = $stmt->fetch()['count'];

    // Today's Records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance_records WHERE DATE(timestamp) = CURDATE()");
    $stats['today_records'] = $stmt->fetch()['count'];

    // Unread Notifications from Staff
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $stats['unread_notifications'] = $stmt->fetch()['count'];

    // Total Notifications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
    $stats['total_notifications'] = $stmt->fetch()['count'];

    // Latest Notifications (last 5)
    $latestNotifications = $pdo->query("
        SELECT 
            n.notification_id,
            n.title,
            n.type AS category,
            n.is_read,
            n.created_at,
            COALESCE(u.full_name, 'System') as sender_name
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.user_id
        ORDER BY n.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Recent Attendance
    $recentAttendance = $pdo->query("
        SELECT 
            ar.timestamp,
            s.student_name,
            b.bus_number,
            ar.action
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.student_id
        JOIN buses b ON ar.bus_id = b.bus_id
        WHERE DATE(ar.timestamp) = CURDATE()
        ORDER BY ar.timestamp DESC
        LIMIT 5
    ")->fetchAll();

    // Recent Checklists (last 7 days from staff)
    $recentChecklists = $pdo->query("
        SELECT 
            dc.checklist_date,
            dc.shift_type,
            dc.completed_at,
            dc.buses_inspected,
            dc.drivers_present,
            dc.rfid_readers_online,
            dc.emergency_kits_checked,
            dc.all_students_accounted,
            dc.buses_returned,
            dc.incidents_reported,
            dc.handover_completed,
            dc.notes,
            u.full_name as staff_name
        FROM daily_checklists dc
        JOIN users u ON dc.staff_id = u.user_id
        WHERE dc.checklist_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY dc.checklist_date DESC, dc.completed_at DESC
        LIMIT 20
    ")->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $stats = array_fill_keys(['total_users', 'total_drivers', 'total_staff', 'total_buses', 'total_students', 'today_records', 'unread_notifications', 'total_notifications'], 0);
    $recentAttendance = [];
    $latestNotifications = [];
    $recentChecklists = [];
}

$pageTitle = "Dashboard";
$currentPage = "dashboard";
$pageTitle = "Dashboard";
$currentPage = "dashboard";
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
        .shift-badge-morning {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }
        
        .shift-badge-evening {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .admin-checklist-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .admin-checklist-table thead th {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
            padding: 16px;
            font-weight: 600;
            text-align: left;
            border-bottom: 2px solid rgba(59, 130, 246, 0.3);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary-color);
        }
        
        .admin-checklist-table thead th i {
            margin-right: 6px;
            opacity: 0.7;
        }
        
        .admin-checklist-table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s;
        }
        
        .admin-checklist-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
            transform: translateX(4px);
        }
        
        .admin-checklist-table tbody td {
            padding: 16px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        .btn-icon:hover {
            background: rgba(59, 130, 246, 0.2) !important;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Dashboard Overview</h1>
            <p>Welcome back, <?= htmlspecialchars(getCurrentUser()['full_name']) ?>. Here's what's happening today.</p>
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
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Users</span>
                    <div class="stat-card-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['total_users']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Active Buses</span>
                    <div class="stat-card-icon green">
                        <i class="fas fa-bus"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['total_buses']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Active Students</span>
                    <div class="stat-card-icon orange">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['total_students']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Today's Records</span>
                    <div class="stat-card-icon red">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['today_records']) ?></div>
            </div>

                </div>
            
            </div>
        </div>

        <!-- Notifications Widget -->
        <?php if ($stats['total_notifications'] > 0): ?>
        <div class="content-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-bell"></i> Latest Staff Notifications
                    <?php if ($stats['unread_notifications'] > 0): ?>
                        <span class="notification-badge"><?= $stats['unread_notifications'] ?></span>
                    <?php endif; ?>
                </h2>
                <a href="<?= SITE_URL ?>/admin/notifications.php" style="color: var(--primary-color); text-decoration: none; font-size: 14px;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="notification-list">
                <?php foreach ($latestNotifications as $notification): ?>
                    <a href="<?= SITE_URL ?>/admin/view_notification.php?id=<?= $notification['notification_id'] ?>" class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>">
                        <div class="notification-left">
                            <div class="notification-icon <?= strtolower($notification['category']) ?>">
                                <?php
                                $icons = [
                                    'RFID' => 'id-card',
                                    'Bus' => 'bus',
                                    'Student' => 'user-graduate',
                                    'System' => 'exclamation-triangle',
                                    'Other' => 'info-circle'
                                ];
                                ?>
                                <i class="fas fa-<?= $icons[$notification['category']] ?? 'bell' ?>"></i>
                            </div>
                            <div class="notification-info">
                                <h4><?= htmlspecialchars($notification['title']) ?></h4>
                                <p>
                                    <span class="category-badge <?= strtolower($notification['category']) ?>">
                                        <?= htmlspecialchars($notification['category']) ?>
                                    </span>
                                    • From: <?= htmlspecialchars($notification['sender_name']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="notification-right">
                            <?php if (!$notification['is_read']): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                            <p class="notification-time"><?= date('d M, H:i', strtotime($notification['created_at'])) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">Recent Attendance Activity</h2>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <a href="<?= SITE_URL ?>/admin/reports.php" style="color: var(--primary-color); text-decoration: none; font-size: 14px;">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="activity-list">
                <?php if (empty($recentAttendance)): ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 32px;">No attendance records today.</p>
                <?php else: ?>
                    <?php foreach ($recentAttendance as $record): ?>
                        <div class="activity-item">
                            <div class="activity-left">
                                <div class="activity-icon">
                                    <i class="fas fa-<?= $record['action'] === 'boarded' ? 'arrow-up' : 'arrow-down' ?>"></i>
                                </div>
                                <div class="activity-info">
                                    <h4><?= htmlspecialchars($record['student_name']) ?></h4>
                                    <p><?= htmlspecialchars($record['bus_number']) ?> - <?= ucfirst($record['action']) ?></p>
                                </div>
                            </div>
                            <div>
                                <span class="badge <?= $record['action'] === 'boarded' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($record['action']) ?>
                                </span>
                                <p class="activity-time"><?= date('h:i A', strtotime($record['timestamp'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Checklist History -->
        <div class="content-card" style="margin-top: 24px;">
            <div class="card-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 20px; margin-bottom: 20px;">
                <h2 class="card-title"><i class="fas fa-clipboard-check"></i> Recent Checklist History</h2>
                <p style="margin: 8px 0 0 0; font-size: 14px; color: var(--text-secondary);">Last 7 days operational records from staff</p>
            </div>
            
            <!-- Filters -->
            <div class="filters-container" style="background: rgba(255, 255, 255, 0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-secondary);">Filter by Shift</label>
                        <select id="adminFilterShift" class="form-control" style="background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.1); padding: 10px; border-radius: 6px; color: var(--text-primary); width: 100%;">
                            <option value="">All Shifts</option>
                            <option value="morning">Morning</option>
                            <option value="evening">Evening</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-secondary);">Filter by Date</label>
                        <input type="date" id="adminFilterDate" class="form-control" style="background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.1); padding: 10px; border-radius: 6px; color: var(--text-primary); width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-secondary);">Search Staff</label>
                        <input type="text" id="adminSearchStaff" class="form-control" placeholder="Enter staff name..." style="background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.1); padding: 10px; border-radius: 6px; color: var(--text-primary); width: 100%;">
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="button" id="adminResetFilters" class="btn btn-secondary" style="width: 100%; padding: 10px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; color: var(--text-primary); cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <?php if (empty($recentChecklists)): ?>
                    <div style="text-align: center; padding: 64px 24px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border: 1px dashed rgba(255, 255, 255, 0.1);">
                        <i class="fas fa-clipboard-list" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 16px;"></i>
                        <p style="color: var(--text-secondary); font-size: 16px; margin: 0;">No checklists found in the last 7 days</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table admin-checklist-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;"><i class="fas fa-calendar"></i> Date</th>
                                    <th style="width: 100px;"><i class="fas fa-clock"></i> Shift</th>
                                    <th style="width: 180px;"><i class="fas fa-user"></i> Staff Member</th>
                                    <th style="width: 120px;"><i class="fas fa-check-circle"></i> Completed</th>
                                    <th style="width: 100px;"><i class="fas fa-tasks"></i> Status</th>
                                    <th><i class="fas fa-sticky-note"></i> Notes</th>
                                    <th style="width: 80px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="adminChecklistTableBody">
                                <?php foreach ($recentChecklists as $index => $checklist): 
                                    // Calculate completion percentage
                                    $total_items = 4;
                                    $completed_items = 0;
                                    if ($checklist['shift_type'] === 'morning') {
                                        $completed_items += $checklist['buses_inspected'] ?? 0;
                                        $completed_items += $checklist['drivers_present'] ?? 0;
                                        $completed_items += $checklist['rfid_readers_online'] ?? 0;
                                        $completed_items += $checklist['emergency_kits_checked'] ?? 0;
                                    } else {
                                        $completed_items += $checklist['all_students_accounted'] ?? 0;
                                        $completed_items += $checklist['buses_returned'] ?? 0;
                                        $completed_items += $checklist['incidents_reported'] ?? 0;
                                        $completed_items += $checklist['handover_completed'] ?? 0;
                                    }
                                    $completion_percentage = ($completed_items / $total_items) * 100;
                                    $status_color = $completion_percentage == 100 ? 'var(--success)' : ($completion_percentage >= 75 ? 'var(--warning)' : 'var(--danger)');
                                ?>
                                    <tr class="admin-checklist-row" data-shift="<?= $checklist['shift_type'] ?>" data-date="<?= $checklist['checklist_date'] ?>" data-staff="<?= htmlspecialchars($checklist['staff_name']) ?>">
                                        <td>
                                            <div style="font-weight: 500;"><?= date('M d, Y', strtotime($checklist['checklist_date'])) ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;"><?= date('l', strtotime($checklist['checklist_date'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="shift-badge shift-badge-<?= $checklist['shift_type'] ?>" style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600; padding: 6px 12px; border-radius: 6px; font-size: 12px;">
                                                <i class="fas fa-<?= $checklist['shift_type'] === 'morning' ? 'sun' : 'moon' ?>"></i>
                                                <?= ucfirst($checklist['shift_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px;">
                                                    <?= strtoupper(substr($checklist['staff_name'], 0, 2)) ?>
                                                </div>
                                                <span style="font-weight: 500;"><?= htmlspecialchars($checklist['staff_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: var(--text-primary);"><?= date('h:i A', strtotime($checklist['completed_at'])) ?></div>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;"><?= date('M d', strtotime($checklist['completed_at'])) ?></div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <div style="width: 100%; height: 6px; background: rgba(255, 255, 255, 0.1); border-radius: 3px; overflow: hidden;">
                                                        <div style="width: <?= $completion_percentage ?>%; height: 100%; background: <?= $status_color ?>; transition: width 0.3s;"></div>
                                                    </div>
                                                </div>
                                                <span style="font-size: 11px; font-weight: 600; color: <?= $status_color ?>;"><?= round($completion_percentage) ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($checklist['notes'])): ?>
                                                <div class="notes-preview" style="max-width: 300px;">
                                                    <span class="notes-text"><?= htmlspecialchars(substr($checklist['notes'], 0, 60)) ?><?= strlen($checklist['notes']) > 60 ? '...' : '' ?></span>
                                                    <?php if (strlen($checklist['notes']) > 60): ?>
                                                        <button type="button" class="view-notes-btn" style="margin-left: 8px; background: none; border: none; color: var(--primary-color); cursor: pointer; font-size: 12px; text-decoration: underline;" onclick="adminViewFullNotes(<?= $index ?>)">
                                                            View full
                                                        </button>
                                                    <?php endif; ?>
                                                    <div class="full-notes" style="display: none;"><?= htmlspecialchars($checklist['notes']) ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary); font-style: italic; font-size: 13px;"><i class="fas fa-minus-circle"></i> No notes</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <button type="button" class="btn-icon" onclick="adminViewChecklistDetails(<?= $index ?>)" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: var(--primary-color); padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: all 0.3s;" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="adminNoResults" style="display: none; text-align: center; padding: 48px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; margin-top: 16px;">
                        <i class="fas fa-search" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 16px;"></i>
                        <p style="color: var(--text-secondary); margin: 0;">No checklists match your filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
    
    <script>
    // Checklist Filter functionality
    document.getElementById('adminFilterShift')?.addEventListener('change', adminApplyFilters);
    document.getElementById('adminFilterDate')?.addEventListener('change', adminApplyFilters);
    document.getElementById('adminSearchStaff')?.addEventListener('input', adminApplyFilters);
    document.getElementById('adminResetFilters')?.addEventListener('click', adminResetFilters);
    
    function adminApplyFilters() {
        const shiftFilter = document.getElementById('adminFilterShift').value.toLowerCase();
        const dateFilter = document.getElementById('adminFilterDate').value;
        const staffFilter = document.getElementById('adminSearchStaff').value.toLowerCase();
        
        const rows = document.querySelectorAll('.admin-checklist-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const shift = row.dataset.shift;
            const date = row.dataset.date;
            const staff = row.dataset.staff.toLowerCase();
            
            let showRow = true;
            
            if (shiftFilter && shift !== shiftFilter) {
                showRow = false;
            }
            
            if (dateFilter && date !== dateFilter) {
                showRow = false;
            }
            
            if (staffFilter && !staff.includes(staffFilter)) {
                showRow = false;
            }
            
            row.style.display = showRow ? '' : 'none';
            if (showRow) visibleCount++;
        });
        
        const noResults = document.getElementById('adminNoResults');
        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }
    
    function adminResetFilters() {
        document.getElementById('adminFilterShift').value = '';
        document.getElementById('adminFilterDate').value = '';
        document.getElementById('adminSearchStaff').value = '';
        adminApplyFilters();
    }
    
    function adminViewFullNotes(index) {
        const rows = document.querySelectorAll('.admin-checklist-row');
        const row = rows[index];
        const fullNotes = row.querySelector('.full-notes').textContent;
        
        Swal.fire({
            title: '<i class="fas fa-sticky-note"></i> Checklist Notes',
            html: '<div style="text-align: left; padding: 16px; background: rgba(255, 255, 255, 0.05); border-radius: 8px; line-height: 1.6;">' + fullNotes + '</div>',
            icon: 'info',
            confirmButtonText: 'Close',
            customClass: {
                confirmButton: 'btn btn-primary'
            }
        });
    }
    
    function adminViewChecklistDetails(index) {
        const checklistData = <?= json_encode($recentChecklists) ?>;
        const checklist = checklistData[index];
        
        let itemsHtml = '';
        if (checklist.shift_type === 'morning') {
            itemsHtml = `
                <div style="display: grid; gap: 12px; margin: 20px 0;">
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.buses_inspected ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.buses_inspected ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>All buses inspected</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.drivers_present ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.drivers_present ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>All drivers present & ready</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.rfid_readers_online ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.rfid_readers_online ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>RFID readers online</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.emergency_kits_checked ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.emergency_kits_checked ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>Emergency kits checked</span>
                    </div>
                </div>
            `;
        } else {
            itemsHtml = `
                <div style="display: grid; gap: 12px; margin: 20px 0;">
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.all_students_accounted ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.all_students_accounted ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>All students accounted for</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.buses_returned ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.buses_returned ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>All buses returned</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.incidents_reported ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.incidents_reported ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>Incidents reported (if any)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                        <i class="fas fa-${checklist.handover_completed ? 'check-circle' : 'times-circle'}" style="font-size: 20px; color: ${checklist.handover_completed ? 'var(--success)' : 'var(--danger)'}"></i>
                        <span>Handover completed</span>
                    </div>
                </div>
            `;
        }
        
        Swal.fire({
            title: '<i class="fas fa-clipboard-check"></i> Checklist Details',
            html: `
                <div style="text-align: left;">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; padding: 16px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Date</div>
                            <div style="font-weight: 600;">${new Date(checklist.checklist_date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Shift</div>
                            <div style="font-weight: 600; text-transform: capitalize;">${checklist.shift_type}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Staff</div>
                            <div style="font-weight: 600;">${checklist.staff_name}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Completed</div>
                            <div style="font-weight: 600;">${new Date(checklist.completed_at).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</div>
                        </div>
                    </div>
                    
                    <h4 style="margin: 20px 0 12px 0; font-size: 14px; color: var(--primary-color);"><i class="fas fa-tasks"></i> Checklist Items</h4>
                    ${itemsHtml}
                    
                    ${checklist.notes ? `
                        <h4 style="margin: 20px 0 12px 0; font-size: 14px; color: var(--primary-color);"><i class="fas fa-sticky-note"></i> Notes</h4>
                        <div style="padding: 16px; background: rgba(255, 255, 255, 0.05); border-radius: 8px; line-height: 1.6;">${checklist.notes}</div>
                    ` : ''}
                </div>
            `,
            width: '600px',
            confirmButtonText: 'Close',
            customClass: {
                confirmButton: 'btn btn-primary'
            }
        });
    }
    </script>
</body>
</html>
