 <?php
/**
 * SelamatRide SmartSchoolBus - Daily Operational Checklist
 * Production-Grade IoT SaaS System
 * Morning and Evening Shift Management
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Daily Checklist";
$currentPage = "checklist";

date_default_timezone_set('Asia/Kuala_Lumpur');

// Handle checklist submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_checklist'])) {
    try {
        $shift_type = filter_input(INPUT_POST, 'shift_type', FILTER_SANITIZE_STRING);
        
        if (!in_array($shift_type, ['morning', 'evening'])) {
            throw new Exception('Invalid shift type');
        }
        
        // Check if checklist already exists for today
        $check_stmt = $pdo->prepare("
            SELECT checklist_id FROM daily_checklists 
            WHERE checklist_date = CURDATE() AND shift_type = ? AND staff_id = ?
        ");
        $check_stmt->execute([$shift_type, $_SESSION['user_id']]);
        
        if ($check_stmt->fetch()) {
            $_SESSION['error_message'] = ucfirst($shift_type) . " checklist already completed today.";
        } else {
            // Insert new checklist
            if ($shift_type === 'morning') {
                $stmt = $pdo->prepare("
                    INSERT INTO daily_checklists 
                    (checklist_date, shift_type, staff_id, buses_inspected, drivers_present, 
                     rfid_readers_online, emergency_kits_checked, notes, completed_at)
                    VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $shift_type,
                    $_SESSION['user_id'],
                    isset($_POST['buses_inspected']) ? 1 : 0,
                    isset($_POST['drivers_present']) ? 1 : 0,
                    isset($_POST['rfid_readers_online']) ? 1 : 0,
                    isset($_POST['emergency_kits_checked']) ? 1 : 0,
                    $_POST['notes'] ?? ''
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO daily_checklists 
                    (checklist_date, shift_type, staff_id, all_students_accounted, buses_returned, 
                     incidents_reported, handover_completed, notes, completed_at)
                    VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $shift_type,
                    $_SESSION['user_id'],
                    isset($_POST['all_students_accounted']) ? 1 : 0,
                    isset($_POST['buses_returned']) ? 1 : 0,
                    isset($_POST['incidents_reported']) ? 1 : 0,
                    isset($_POST['handover_completed']) ? 1 : 0,
                    $_POST['notes'] ?? ''
                ]);
            }
            
            $_SESSION['success_message'] = ucfirst($shift_type) . " checklist submitted successfully!";
        }
        
        header("Location: " . SITE_URL . "/staff/daily_checklist.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Checklist Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to submit checklist: " . $e->getMessage();
    }
}

// Fetch today's checklists
try {
    $today_checklists = $pdo->prepare("
        SELECT dc.*, u.full_name 
        FROM daily_checklists dc
        JOIN users u ON dc.staff_id = u.user_id
        WHERE dc.checklist_date = CURDATE()
        ORDER BY dc.shift_type, dc.completed_at DESC
    ");
    $today_checklists->execute();
    $checklists_today = $today_checklists->fetchAll();
    
    // Fetch recent checklists (last 7 days)
    $recent_checklists = $pdo->prepare("
        SELECT dc.*, u.full_name 
        FROM daily_checklists dc
        JOIN users u ON dc.staff_id = u.user_id
        WHERE dc.checklist_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY dc.checklist_date DESC, dc.shift_type, dc.completed_at DESC
        LIMIT 20
    ");
    $recent_checklists->execute();
    $checklists_recent = $recent_checklists->fetchAll();
    
} catch (Exception $e) {
    error_log("Checklist Fetch Error: " . $e->getMessage());
    $checklists_today = [];
    $checklists_recent = [];
}

// Check if user already completed each shift today
$morning_done = false;
$evening_done = false;
foreach ($checklists_today as $cl) {
    if ($cl['staff_id'] == $_SESSION['user_id']) {
        if ($cl['shift_type'] === 'morning') $morning_done = true;
        if ($cl['shift_type'] === 'evening') $evening_done = true;
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <?php include '../admin/includes/admin_styles.php'; ?>
    
    <style>
        .checklist-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .checklist-item {
            display: flex;
            align-items: center;
            padding: 16px;
            background: var(--content-bg);
            border-radius: 8px;
            margin-bottom: 12px;
        }
        
        .checklist-item input[type="checkbox"] {
            width: 24px;
            height: 24px;
            margin-right: 16px;
            cursor: pointer;
        }
        
        .checklist-item label {
            flex: 1;
            font-size: 15px;
            cursor: pointer;
        }
        
        .shift-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .shift-badge.morning {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }
        
        .shift-badge.evening {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .professional-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .professional-table thead th {
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
        
        .professional-table thead th i {
            margin-right: 6px;
            opacity: 0.7;
        }
        
        .professional-table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s;
        }
        
        .professional-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
            transform: translateX(4px);
        }
        
        .professional-table tbody td {
            padding: 16px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        .btn-icon:hover {
            background: rgba(59, 130, 246, 0.2) !important;
            transform: scale(1.05);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--content-bg);
            color: var(--text-primary);
            transition: all 0.3s;
            font-family: inherit;
            resize: vertical;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: var(--card-bg);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }
        
        .btn {
            padding: 14px 24px;
            border-radius: 8px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary i {
            font-size: 16px;
        }
    </style>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-clipboard-check"></i> Daily Operational Checklist</h1>
            <p>Complete morning and evening checklists for operational accountability</p>
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

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">
            <!-- Morning Checklist Form -->
            <div class="checklist-card">
                <h2 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-sun" style="color: var(--warning);"></i>
                    Morning Checklist
                    <?php if ($morning_done): ?>
                        <span class="badge success" style="margin-left: auto;">✓ Completed</span>
                    <?php endif; ?>
                </h2>
                
                <?php if (!$morning_done): ?>
                <form method="POST" action="">
                    <input type="hidden" name="shift_type" value="morning">
                    <input type="hidden" name="submit_checklist" value="1">
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="buses_inspected" id="buses_inspected" required>
                        <label for="buses_inspected">
                            <strong>All buses inspected</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                Tires, brakes, lights, fuel level checked
                            </div>
                        </label>
                    </div>
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="drivers_present" id="drivers_present" required>
                        <label for="drivers_present">
                            <strong>All drivers present & ready</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                Drivers checked in and routes assigned
                            </div>
                        </label>
                    </div>
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="rfid_readers_online" id="rfid_readers_online" required>
                        <label for="rfid_readers_online">
                            <strong>RFID readers online</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                All IoT devices connected and working
                            </div>
                        </label>
                    </div>
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="emergency_kits_checked" id="emergency_kits_checked" required>
                        <label for="emergency_kits_checked">
                            <strong>Emergency kits checked</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                First aid, fire extinguisher, tools available
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group" style="margin-top: 24px;">
                        <label for="morning_notes">
                            <i class="fas fa-sticky-note"></i> Additional Notes
                        </label>
                        <textarea name="notes" id="morning_notes" class="form-control" rows="4" placeholder="Any issues, observations, or important notes..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                        <i class="fas fa-check-circle"></i> Submit Morning Checklist
                    </button>
                </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--success);">
                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 12px;"></i>
                        <p>Morning checklist completed</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Evening Checklist Form -->
            <div class="checklist-card">
                <h2 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-moon" style="color: var(--primary-color);"></i>
                    Evening Checklist
                    <?php if ($evening_done): ?>
                        <span class="badge success" style="margin-left: auto;">✓ Completed</span>
                    <?php endif; ?>
                </h2>
                
                <?php if (!$evening_done): ?>
                <form method="POST" action="">
                    <input type="hidden" name="shift_type" value="evening">
                    <input type="hidden" name="submit_checklist" value="1">
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="all_students_accounted" id="all_students_accounted" required>
                        <label for="all_students_accounted">
                            <strong>All students accounted for</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                Verified all dropoffs, no missing students
                            </div>
                        </label>
                    </div>
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="buses_returned" id="buses_returned" required>
                        <label for="buses_returned">
                            <strong>All buses returned</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                Buses parked, secured, and cleaned
                            </div>
                        </label>
                    </div>
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="incidents_reported" id="incidents_reported" required>
                        <label for="incidents_reported">
                            <strong>Incidents reported (if any)</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                All issues logged in incident report system
                            </div>
                        </label>
                    </div>
                    
                    <div class="checklist-item">
                        <input type="checkbox" name="handover_completed" id="handover_completed" required>
                        <label for="handover_completed">
                            <strong>Handover notes completed</strong>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                Important info shared for next shift
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group" style="margin-top: 24px;">
                        <label for="evening_notes">
                            <i class="fas fa-clipboard-list"></i> Handover Notes
                        </label>
                        <textarea name="notes" id="evening_notes" class="form-control" rows="4" placeholder="Important information for the next shift..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                        <i class="fas fa-check-circle"></i> Submit Evening Checklist
                    </button>
                </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--success);">
                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 12px;"></i>
                        <p>Evening checklist completed</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Checklists -->
        <div class="content-card" style="margin-top: 24px;">
            <div class="card-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 20px; margin-bottom: 20px;">
                <h2 class="card-title"><i class="fas fa-history"></i> Recent Checklists History</h2>
                <p style="margin: 8px 0 0 0; font-size: 14px; color: var(--text-secondary);">Last 7 days operational records</p>
            </div>
            
            <!-- Filters -->
            <div class="filters-container" style="background: rgba(255, 255, 255, 0.02); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-secondary);">Filter by Shift</label>
                        <select id="filterShift" class="form-control" style="background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.1); padding: 10px; border-radius: 6px; color: var(--text-primary); width: 100%;">
                            <option value="">All Shifts</option>
                            <option value="morning">Morning</option>
                            <option value="evening">Evening</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-secondary);">Filter by Date</label>
                        <input type="date" id="filterDate" class="form-control" style="background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.1); padding: 10px; border-radius: 6px; color: var(--text-primary); width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-secondary);">Search Staff</label>
                        <input type="text" id="searchStaff" class="form-control" placeholder="Enter staff name..." style="background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.1); padding: 10px; border-radius: 6px; color: var(--text-primary); width: 100%;">
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="button" id="resetFilters" class="btn btn-secondary" style="width: 100%; padding: 10px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; color: var(--text-primary); cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <?php if (empty($checklists_recent)): ?>
                    <div style="text-align: center; padding: 64px 24px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border: 1px dashed rgba(255, 255, 255, 0.1);">
                        <i class="fas fa-clipboard-list" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 16px;"></i>
                        <p style="color: var(--text-secondary); font-size: 16px; margin: 0;">No checklists found in the last 7 days</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table professional-table">
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
                            <tbody id="checklistTableBody">
                                <?php foreach ($checklists_recent as $index => $checklist): 
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
                                    <tr class="checklist-row" data-shift="<?= $checklist['shift_type'] ?>" data-date="<?= $checklist['checklist_date'] ?>" data-staff="<?= htmlspecialchars($checklist['full_name']) ?>">
                                        <td>
                                            <div style="font-weight: 500;"><?= date('M d, Y', strtotime($checklist['checklist_date'])) ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;"><?= date('l', strtotime($checklist['checklist_date'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="shift-badge <?= $checklist['shift_type'] ?>" style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600;">
                                                <i class="fas fa-<?= $checklist['shift_type'] === 'morning' ? 'sun' : 'moon' ?>"></i>
                                                <?= ucfirst($checklist['shift_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px;">
                                                    <?= strtoupper(substr($checklist['full_name'], 0, 2)) ?>
                                                </div>
                                                <span style="font-weight: 500;"><?= htmlspecialchars($checklist['full_name']) ?></span>
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
                                                        <button type="button" class="view-notes-btn" style="margin-left: 8px; background: none; border: none; color: var(--primary-color); cursor: pointer; font-size: 12px; text-decoration: underline;" onclick="viewFullNotes(<?= $index ?>)">
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
                                            <button type="button" class="btn-icon" onclick="viewChecklistDetails(<?= $index ?>)" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: var(--primary-color); padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: all 0.3s;" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="noResults" style="display: none; text-align: center; padding: 48px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; margin-top: 16px;">
                        <i class="fas fa-search" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 16px;"></i>
                        <p style="color: var(--text-secondary); margin: 0;">No checklists match your filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Card -->
        <div class="content-card" style="margin-top: 24px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1)); border: 1px solid rgba(59, 130, 246, 0.2);">
            <div style="padding: 24px;">
                <h3 style="margin: 0 0 12px 0; font-size: 16px; color: var(--primary-color);">
                    <i class="fas fa-info-circle"></i> Why Daily Checklists Matter
                </h3>
                <p style="margin: 0; color: var(--text-secondary); line-height: 1.6;">
                    Daily checklists ensure operational consistency, safety compliance, and accountability. 
                    Complete both morning and evening checklists every day to maintain service quality and documentation for audits.
                </p>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Filter functionality
    document.getElementById('filterShift')?.addEventListener('change', applyFilters);
    document.getElementById('filterDate')?.addEventListener('change', applyFilters);
    document.getElementById('searchStaff')?.addEventListener('input', applyFilters);
    document.getElementById('resetFilters')?.addEventListener('click', resetFilters);
    
    function applyFilters() {
        const shiftFilter = document.getElementById('filterShift').value.toLowerCase();
        const dateFilter = document.getElementById('filterDate').value;
        const staffFilter = document.getElementById('searchStaff').value.toLowerCase();
        
        const rows = document.querySelectorAll('.checklist-row');
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
        
        const noResults = document.getElementById('noResults');
        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }
    
    function resetFilters() {
        document.getElementById('filterShift').value = '';
        document.getElementById('filterDate').value = '';
        document.getElementById('searchStaff').value = '';
        applyFilters();
    }
    
    function viewFullNotes(index) {
        const rows = document.querySelectorAll('.checklist-row');
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
    
    function viewChecklistDetails(index) {
        const checklistData = <?= json_encode($checklists_recent) ?>;
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
                            <div style="font-weight: 600;">${checklist.full_name}</div>
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
    
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
