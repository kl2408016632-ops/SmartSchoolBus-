<?php
/**
 * SelamatRide SmartSchoolBus - Create Bus
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']);

$pageTitle = "Create Bus";
$currentPage = "buses";

$errors = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Collect and sanitize input
        $formData = [
            'bus_number' => trim($_POST['bus_number'] ?? ''),
            'license_plate' => trim($_POST['license_plate'] ?? ''),
            'assigned_driver_id' => !empty($_POST['assigned_driver_id']) ? (int)$_POST['assigned_driver_id'] : null,
            'capacity' => (int)($_POST['capacity'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
            'route_description' => trim($_POST['route_description'] ?? ''),
            'device_id' => trim($_POST['device_id'] ?? '')
        ];

        // Validation
        if (empty($formData['bus_number'])) {
            $errors[] = 'Bus number is required.';
        } else {
            // Check if bus number already exists
            $stmt = $pdo->prepare("SELECT bus_id FROM buses WHERE bus_number = ?");
            $stmt->execute([$formData['bus_number']]);
            if ($stmt->fetch()) {
                $errors[] = 'Bus number already exists.';
            }
        }

        if (empty($formData['license_plate'])) {
            $errors[] = 'License plate number is required.';
        } else {
            // Check if license plate already exists
            $stmt = $pdo->prepare("SELECT bus_id FROM buses WHERE license_plate = ?");
            $stmt->execute([$formData['license_plate']]);
            if ($stmt->fetch()) {
                $errors[] = 'License plate number already exists.';
            }
        }

        if ($formData['capacity'] <= 0) {
            $errors[] = 'Please enter a valid capacity greater than 0.';
        } elseif ($formData['capacity'] > 100) {
            $errors[] = 'Capacity cannot exceed 100 seats.';
        }

        // Validate driver if assigned
        if ($formData['assigned_driver_id']) {
            $stmt = $pdo->prepare("SELECT u.user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ? AND r.role_name = 'driver' AND u.status = 'active'");
            $stmt->execute([$formData['assigned_driver_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Selected driver is invalid or not active.';
            }
        }

        // Validate device_id if provided
        if (!empty($formData['device_id'])) {
            $stmt = $pdo->prepare("SELECT bus_id FROM buses WHERE device_id = ?");
            $stmt->execute([$formData['device_id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Device ID already exists.';
            }
        }

        // If no errors, insert into database
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO buses (bus_number, license_plate, capacity, assigned_driver_id, route_description, status, device_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['bus_number'],
                    $formData['license_plate'],
                    $formData['capacity'],
                    $formData['assigned_driver_id'],
                    $formData['route_description'],
                    $formData['status'],
                    !empty($formData['device_id']) ? $formData['device_id'] : null
                ]);

                // Redirect to buses page with success message
                header('Location: ' . SITE_URL . '/admin/buses.php?success=created');
                exit;
            } catch (Exception $e) {
                error_log("Bus Creation Error: " . $e->getMessage());
                $errors[] = 'Failed to create bus. Please try again.';
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch active drivers for dropdown
try {
    $drivers = $pdo->query("
        SELECT u.user_id, u.full_name, u.phone 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE r.role_name = 'driver' AND u.status = 'active'
        ORDER BY u.full_name
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Driver Fetch Error: " . $e->getMessage());
    $drivers = [];
}
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
        .form-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 32px;
            max-width: 800px;
        }

        .form-header {
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-header h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: var(--text-primary);
        }

        .form-header p {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        .alert li {
            margin: 4px 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group label .required {
            color: var(--danger);
        }

        .form-control {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
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

        .form-control:disabled {
            background: var(--border-color);
            cursor: not-allowed;
            opacity: 0.6;
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .helper-text {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 12px 24px;
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

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            
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
                <h1>Create New Bus</h1>
                <p>Add a new bus to your fleet</p>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-bus"></i> Bus Information</h2>
                <p>Fill in the details below to add a new bus</p>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong><i class="fas fa-exclamation-circle"></i> Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-grid">
                    <!-- Bus Number -->
                    <div class="form-group">
                        <label for="bus_number">
                            Bus Number <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="bus_number" 
                            name="bus_number" 
                            class="form-control" 
                            placeholder="e.g., Bus #01"
                            value="<?= htmlspecialchars($formData['bus_number'] ?? '') ?>"
                            required
                        >
                        <span class="helper-text">Unique identifier for this bus</span>
                    </div>

                    <!-- License Plate -->
                    <div class="form-group">
                        <label for="license_plate">
                            License Plate <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="license_plate" 
                            name="license_plate" 
                            class="form-control" 
                            placeholder="e.g., WA1234B"
                            value="<?= htmlspecialchars($formData['license_plate'] ?? '') ?>"
                            required
                        >
                        <span class="helper-text">Vehicle registration number</span>
                    </div>

                    <!-- Capacity -->
                    <div class="form-group">
                        <label for="capacity">
                            Capacity (Seats) <span class="required">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="capacity" 
                            name="capacity" 
                            class="form-control" 
                            placeholder="e.g., 30"
                            min="1"
                            max="100"
                            value="<?= htmlspecialchars($formData['capacity'] ?? '') ?>"
                            required
                        >
                        <span class="helper-text">Maximum number of students</span>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="status">
                            Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?= ($formData['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="maintenance" <?= ($formData['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="inactive" <?= ($formData['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <span class="helper-text">Current operational status</span>
                    </div>

                    <!-- Assigned Driver -->
                    <div class="form-group">
                        <label for="assigned_driver_id">
                            Assigned Driver
                        </label>
                        <select id="assigned_driver_id" name="assigned_driver_id" class="form-control">
                            <option value="">-- No Driver Assigned --</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= $driver['user_id'] ?>" 
                                    <?= ($formData['assigned_driver_id'] ?? '') == $driver['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($driver['full_name']) ?>
                                    <?= $driver['phone'] ? ' - ' . htmlspecialchars($driver['phone']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="helper-text">Select driver for this bus (optional)</span>
                    </div>

                    <!-- Device ID -->
                    <div class="form-group">
                        <label for="device_id">
                            Device ID (ESP32)
                        </label>
                        <input 
                            type="text" 
                            id="device_id" 
                            name="device_id" 
                            class="form-control" 
                            placeholder="e.g., ESP32_BUS04"
                            value="<?= htmlspecialchars($formData['device_id'] ?? '') ?>"
                        >
                        <span class="helper-text">RFID reader device identifier</span>
                    </div>

                    <!-- Route Description -->
                    <div class="form-group full-width">
                        <label for="route_description">
                            Route Description
                        </label>
                        <textarea 
                            id="route_description" 
                            name="route_description" 
                            class="form-control" 
                            placeholder="e.g., Sepang - Putrajaya Route"
                        ><?= htmlspecialchars($formData['route_description'] ?? '') ?></textarea>
                        <span class="helper-text">Brief description of the bus route (optional)</span>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="<?= SITE_URL ?>/admin/buses.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Bus
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
