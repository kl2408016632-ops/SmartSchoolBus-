<?php
/**
 * SelamatRide SmartSchoolBus
 * Driver - Student Addresses & Pickup Locations
 */
require_once '../config.php';
requireRole(['driver']);

$pageTitle = "Student Addresses";
$currentPage = "students";

date_default_timezone_set('Asia/Kuala_Lumpur');

// Fetch driver's bus assignment
$busStmt = $pdo->prepare("
    SELECT bus_id, bus_number 
    FROM buses 
    WHERE assigned_driver_id = ?
");
$busStmt->execute([$_SESSION['user_id']]);
$bus = $busStmt->fetch();

$students = [];
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($bus) {
    try {
        // Fetch students assigned to driver's bus with their addresses
        $query = "
            SELECT 
                s.student_id,
                s.student_name,
                s.photo_url,
                s.rfid_uid,
                s.address,
                p.parent_name,
                p.phone_primary,
                p.phone_secondary
            FROM students s
            LEFT JOIN parents p ON s.parent_id = p.parent_id
            WHERE s.bus_id = ? AND s.status = 'active'
        ";
        
        $params = [$bus['bus_id']];
        
        if (!empty($searchFilter)) {
            $query .= " AND (s.student_name LIKE ? OR s.address LIKE ?)";
            $searchParam = "%{$searchFilter}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $query .= " ORDER BY s.student_name ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Student addresses fetch error: " . $e->getMessage());
        $students = [];
    }
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
        .page-header {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(124, 58, 237, 0.05));
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            display: block;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            display: block;
            width: 100%;
        }
        
        .page-header p {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0;
            display: block;
            width: 100%;
        }
        
        .bus-indicator {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .bus-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .search-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 14px 48px 14px 48px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .search-box .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 18px;
        }
        
        .search-box .clear-btn {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 18px;
            padding: 4px;
        }
        
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .student-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .student-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        
        .student-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .student-photo {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        
        .student-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }
        
        .student-rfid {
            display: inline-block;
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        
        .student-body {
            padding: 20px;
        }
        
        .info-row {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            align-items: flex-start;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-content strong {
            display: block;
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        
        .info-content span {
            display: block;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.5;
        }
        
        .map-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            background: rgba(139, 92, 246, 0.1);
            transition: all 0.2s;
        }
        
        .map-link:hover {
            background: rgba(139, 92, 246, 0.2);
        }
        
        .phone-link {
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .phone-link:hover {
            color: #8b5cf6;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 24px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .empty-state p {
            font-size: 16px;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <?php include 'includes/driver_header.php'; ?>
    <?php include 'includes/driver_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📍 Student Addresses</h1>
            <p>Find your students' home addresses and contact information</p>
        </div>
        
        <?php if ($bus): ?>
            <?php 
            // Debug: Check total students in database for this bus
            try {
                $debugStmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE bus_id = ?");
                $debugStmt->execute([$bus['bus_id']]);
                $totalInDb = $debugStmt->fetchColumn();
                
                $debugStmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE bus_id = ? AND status = 'active'");
                $debugStmt2->execute([$bus['bus_id']]);
                $totalActive = $debugStmt2->fetchColumn();
                
                // Uncomment for debugging:
                // error_log("Driver Bus ID: " . $bus['bus_id'] . ", Total students: " . $totalInDb . ", Active: " . $totalActive);
            } catch (Exception $e) {
                error_log("Debug error: " . $e->getMessage());
            }
            ?>
            <!-- Bus Indicator -->
            <div class="bus-indicator">
                <div class="bus-icon">🚌</div>
                <div style="flex: 1;">
                    <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 4px;">Your Assigned Bus</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($bus['bus_number']) ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 4px;">Total Students</div>
                    <div style="font-size: 20px; font-weight: 700; color: #8b5cf6;"><?= count($students) ?></div>
                </div>
            </div>
            
            <!-- Search Box -->
            <div class="search-card">
                <form method="GET" action="">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($searchFilter) ?>" 
                               placeholder="Search by student name or address..."
                               id="searchInput">
                        <?php if (!empty($searchFilter)): ?>
                            <button type="button" class="clear-btn" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Students Grid -->
            <?php if (count($students) > 0): ?>
                <div class="students-grid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <?php 
                                $photoPath = !empty($student['photo_url']) 
                                    ? SITE_URL . '/' . $student['photo_url'] 
                                    : SITE_URL . '/assets/images/default-avatar.png';
                                ?>
                                <img src="<?= htmlspecialchars($photoPath) ?>" 
                                     alt="<?= htmlspecialchars($student['student_name']) ?>" 
                                     class="student-photo">
                                <div class="student-info">
                                    <h3><?= htmlspecialchars($student['student_name']) ?></h3>
                                    <span class="student-rfid"><?= htmlspecialchars($student['rfid_uid']) ?></span>
                                </div>
                            </div>
                            
                            <div class="student-body">
                                <!-- Home Address -->
                                <?php if (!empty($student['address'])): ?>
                                <div class="info-row">
                                    <div class="info-icon" style="background: rgba(239, 68, 68, 0.1); color: #dc2626;">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="info-content">
                                        <strong>Home Address</strong>
                                        <span><?= nl2br(htmlspecialchars($student['address'])) ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Parent Contact -->
                                <?php if (!empty($student['parent_name'])): ?>
                                <div class="info-row">
                                    <div class="info-icon" style="background: rgba(59, 130, 246, 0.1); color: #2563eb;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="info-content">
                                        <strong>Parent/Guardian</strong>
                                        <span><?= htmlspecialchars($student['parent_name']) ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Phone Numbers -->
                                <?php if (!empty($student['phone_primary']) || !empty($student['phone_secondary'])): ?>
                                <div class="info-row">
                                    <div class="info-icon" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="info-content">
                                        <strong>Contact Number</strong>
                                        <?php if (!empty($student['phone_primary'])): ?>
                                            <span>
                                                <a href="tel:<?= htmlspecialchars($student['phone_primary']) ?>" class="phone-link">
                                                    <i class="fas fa-phone-alt" style="font-size: 12px; color: #059669;"></i> 
                                                    <?= htmlspecialchars($student['phone_primary']) ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($student['phone_secondary'])): ?>
                                            <span>
                                                <a href="tel:<?= htmlspecialchars($student['phone_secondary']) ?>" class="phone-link">
                                                    <i class="fas fa-phone-alt" style="font-size: 12px; color: #059669;"></i> 
                                                    <?= htmlspecialchars($student['phone_secondary']) ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Students Found</h3>
                    <p>
                        <?php if (!empty($searchFilter)): ?>
                            No students match your search. Try different keywords.
                        <?php else: ?>
                            No students are assigned to your bus yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bus"></i>
                <h3>No Bus Assigned</h3>
                <p>Please contact the school administrator to assign a bus to your driver account.</p>
            </div>
        <?php endif; ?>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
    
    <script>
        function clearSearch() {
            window.location.href = 'students.php';
        }
        
        // Auto-submit search after typing stops
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>
