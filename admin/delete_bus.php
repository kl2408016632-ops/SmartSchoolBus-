<?php
/**
 * SelamatRide SmartSchoolBus - Delete Bus
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']);

// Get bus ID from URL
$busId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($busId <= 0) {
    header('Location: ' . SITE_URL . '/admin/buses.php?error=' . urlencode('Invalid bus ID.'));
    exit;
}

// Fetch bus details
try {
    $stmt = $pdo->prepare("SELECT * FROM buses WHERE bus_id = ?");
    $stmt->execute([$busId]);
    $bus = $stmt->fetch();
    
    if (!$bus) {
        header('Location: ' . SITE_URL . '/admin/buses.php?error=' . urlencode('Bus not found.'));
        exit;
    }
} catch (Exception $e) {
    error_log("Bus Fetch Error: " . $e->getMessage());
    header('Location: ' . SITE_URL . '/admin/buses.php?error=' . urlencode('Failed to load bus.'));
    exit;
}

// Check if bus has assigned students
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM students WHERE bus_id = ? AND status = 'active'");
    $stmt->execute([$busId]);
    $result = $stmt->fetch();
    $studentCount = $result['student_count'];
    
    if ($studentCount > 0) {
        header('Location: ' . SITE_URL . '/admin/buses.php?error=' . urlencode('Cannot delete bus. ' . $studentCount . ' student(s) are currently assigned to this bus. Please reassign students first.'));
        exit;
    }
} catch (Exception $e) {
    error_log("Student Count Error: " . $e->getMessage());
    header('Location: ' . SITE_URL . '/admin/buses.php?error=' . urlencode('Failed to verify bus assignments.'));
    exit;
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: ' . SITE_URL . '/admin/buses.php?error=' . urlencode('Invalid security token.'));
        exit;
    }
    
    try {
        // Delete the bus
        $stmt = $pdo->prepare("DELETE FROM buses WHERE bus_id = ?");
        $stmt->execute([$busId]);
        
        // Redirect with success message
        header('Location: ' . SITE_URL . '/admin/buses.php?success=deleted');
        exit;
    } catch (Exception $e) {
        error_log("Bus Deletion Error: " . $e->getMessage());
        header('Location: ' . SITE_URL . '/admin/buses.php?error=' . urlencode('Failed to delete bus.'));
        exit;
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Delete Bus";
$currentPage = "buses";
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
        .confirmation-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 32px;
            max-width: 600px;
            margin: 0 auto;
        }

        .confirmation-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .confirmation-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .confirmation-icon i {
            font-size: 36px;
            color: var(--danger);
        }

        .confirmation-header h2 {
            margin: 0 0 12px 0;
            font-size: 24px;
            color: var(--text-primary);
        }

        .confirmation-header p {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .bus-details {
            background: var(--content-bg);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-badge.maintenance {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .warning-box i {
            color: var(--danger);
            margin-top: 2px;
        }

        .warning-box-content {
            flex: 1;
        }

        .warning-box-content strong {
            display: block;
            margin-bottom: 4px;
            color: var(--danger);
        }

        .warning-box-content p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 12px 32px;
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

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column-reverse;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
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
            <div>
                <h1>Delete Bus</h1>
                <p>Confirm bus deletion</p>
            </div>
        </div>

        <!-- Confirmation Container -->
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="confirmation-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2>Delete Bus Confirmation</h2>
                <p>Are you sure you want to delete this bus? This action cannot be undone.</p>
            </div>

            <!-- Warning Box -->
            <div class="warning-box">
                <i class="fas fa-exclamation-circle"></i>
                <div class="warning-box-content">
                    <strong>Warning!</strong>
                    <p>Deleting this bus will permanently remove all its information from the system. Make sure no students are assigned to this bus before deletion.</p>
                </div>
            </div>

            <!-- Bus Details -->
            <div class="bus-details">
                <div class="detail-row">
                    <span class="detail-label">Bus ID</span>
                    <span class="detail-value">#<?= $bus['bus_id'] ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bus Number</span>
                    <span class="detail-value"><?= htmlspecialchars($bus['bus_number']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">License Plate</span>
                    <span class="detail-value"><?= htmlspecialchars($bus['license_plate']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Capacity</span>
                    <span class="detail-value"><?= $bus['capacity'] ?> seats</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge <?= $bus['status'] ?>">
                            <?= ucfirst($bus['status']) ?>
                        </span>
                    </span>
                </div>
                <?php if ($bus['route_description']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Route</span>
                        <span class="detail-value"><?= htmlspecialchars($bus['route_description']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Created</span>
                    <span class="detail-value"><?= date('M d, Y', strtotime($bus['created_at'])) ?></span>
                </div>
            </div>

            <!-- Form Actions -->
            <form method="POST" action="" onsubmit="return confirm('Are you absolutely sure you want to delete this bus?');">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-actions">
                    <a href="<?= SITE_URL ?>/admin/buses.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Yes, Delete Bus
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
