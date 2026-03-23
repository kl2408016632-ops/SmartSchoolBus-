<?php
/**
 * SelamatRide SmartSchoolBus - Edit Student
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "Edit Student";
$currentPage = "students";

$errors = [];
$student = null;
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch student data
if ($studentId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, p.parent_name, p.phone_primary, b.bus_number
            FROM students s
            LEFT JOIN parents p ON s.parent_id = p.parent_id
            LEFT JOIN buses b ON s.bus_id = b.bus_id
            WHERE s.student_id = ?
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        if (!$student) {
            header('Location: ' . SITE_URL . '/staff/students.php?error=notfound');
            exit;
        }
    } catch (Exception $e) {
        error_log("Student Fetch Error: " . $e->getMessage());
        header('Location: ' . SITE_URL . '/staff/students.php?error=database');
        exit;
    }
} else {
    header('Location: ' . SITE_URL . '/staff/students.php?error=invalid');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Collect and sanitize input
        $formData = [
            'student_name' => trim($_POST['student_name'] ?? ''),
            'rfid_uid' => trim($_POST['rfid_uid'] ?? ''),
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'bus_id' => !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null,
            'payment_status' => $_POST['payment_status'] ?? 'unpaid',
            'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'date_of_birth' => trim($_POST['date_of_birth'] ?? '')
        ];

        // Validation
        if (empty($formData['student_name'])) {
            $errors[] = 'Student name is required.';
        }

        if (empty($formData['rfid_uid'])) {
            $errors[] = 'RFID UID is required.';
        } else {
            // Check if RFID UID already exists (excluding current student)
            $stmt = $pdo->prepare("SELECT student_id FROM students WHERE rfid_uid = ? AND student_id != ?");
            $stmt->execute([$formData['rfid_uid'], $studentId]);
            if ($stmt->fetch()) {
                $errors[] = 'RFID UID already exists. Please use a unique RFID card.';
            }
        }

        // Handle avatar upload (optional)
        $photoPath = $student['photo_url']; // Keep existing photo by default
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'Invalid file type. Only JPG and PNG images are allowed.';
            }
            
            // Validate file size (max 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'File size must be less than 2MB.';
            }
            
            if (empty($errors)) {
                // Delete old photo if exists
                if (!empty($student['photo_url']) && file_exists(__DIR__ . '/../' . $student['photo_url'])) {
                    unlink(__DIR__ . '/../' . $student['photo_url']);
                }
                
                // Generate unique filename
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'student_' . time() . '_' . uniqid() . '.' . $extension;
                $uploadPath = __DIR__ . '/../uploads/students/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $photoPath = 'uploads/students/' . $filename;
                } else {
                    $errors[] = 'Failed to upload photo.';
                }
            }
        }

        // If no errors, update database
        if (empty($errors)) {
            try {
                $sql = "UPDATE students 
                        SET student_name = ?, 
                            rfid_uid = ?, 
                            parent_id = ?, 
                            bus_id = ?, 
                            payment_status = ?, 
                            photo_url = ?, 
                            emergency_contact = ?, 
                            address = ?, 
                            date_of_birth = ?,
                            updated_at = NOW()
                        WHERE student_id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['student_name'],
                    $formData['rfid_uid'],
                    $formData['parent_id'],
                    $formData['bus_id'],
                    $formData['payment_status'],
                    $photoPath,
                    $formData['emergency_contact'],
                    $formData['address'],
                    !empty($formData['date_of_birth']) ? $formData['date_of_birth'] : null,
                    $studentId
                ]);

                // Redirect with success message
                header('Location: ' . SITE_URL . '/staff/students.php?success=updated');
                exit;
            } catch (Exception $e) {
                error_log("Student Update Error: " . $e->getMessage());
                $errors[] = 'Failed to update student. Please try again.';
            }
        }
        
        // Update student array with form data for re-display
        if (!empty($errors)) {
            foreach ($formData as $key => $value) {
                $student[$key] = $value;
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch parents for dropdown
try {
    $parents = $pdo->query("
        SELECT parent_id, parent_name, phone_primary 
        FROM parents 
        ORDER BY parent_name
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Parent Fetch Error: " . $e->getMessage());
    $parents = [];
}

// Fetch active buses for dropdown
try {
    $buses = $pdo->query("
        SELECT bus_id, bus_number, license_plate 
        FROM buses 
        WHERE status = 'active'
        ORDER BY bus_number
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Bus Fetch Error: " . $e->getMessage());
    $buses = [];
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
    
    <?php include '../admin/includes/admin_styles.php'; ?>
    
    <style>
        .form-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 32px;
            max-width: 900px;
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

        .current-photo-container {
            margin-bottom: 24px;
            padding: 20px;
            background: var(--content-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .current-photo-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .current-photo-preview {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .current-photo-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .current-photo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
        }

        .current-photo-info {
            flex: 1;
        }

        .current-photo-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .current-photo-meta {
            font-size: 13px;
            color: var(--text-secondary);
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

        .file-upload-wrapper {
            position: relative;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-upload-label:hover {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
        }

        .file-upload-label i {
            font-size: 24px;
            color: var(--primary-color);
        }

        input[type="file"] {
            display: none;
        }

        .info-box {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding: 16px;
            background: var(--content-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .info-box {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }

            .current-photo-preview {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Edit Student</h1>
                <p>Update student information</p>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-user-edit"></i> Update Student Information</h2>
                <p>Modify the details below to update this student</p>
            </div>

            <!-- Current Photo Preview -->
            <div class="current-photo-container">
                <div class="current-photo-label">Current Photo</div>
                <div class="current-photo-preview">
                    <?php if (!empty($student['photo_url']) && file_exists(__DIR__ . '/../' . $student['photo_url'])): ?>
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($student['photo_url']) ?>?v=<?= time() ?>" 
                             class="current-photo-img" 
                             alt="<?= htmlspecialchars($student['student_name']) ?>">
                    <?php else: ?>
                        <div class="current-photo-placeholder">
                            <?= strtoupper(substr($student['student_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="current-photo-info">
                        <div class="current-photo-name"><?= htmlspecialchars($student['student_name']) ?></div>
                        <div class="current-photo-meta">
                            <i class="fas fa-id-card"></i> RFID: <?= htmlspecialchars($student['rfid_uid']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="info-box">
                <div class="info-item">
                    <span class="info-label">Student ID</span>
                    <span class="info-value">#<?= $student['student_id'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Enrollment Date</span>
                    <span class="info-value">
                        <?= !empty($student['enrollment_date']) ? date('d M Y', strtotime($student['enrollment_date'])) : 'N/A' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Created At</span>
                    <span class="info-value"><?= date('d M Y, H:i', strtotime($student['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?= date('d M Y, H:i', strtotime($student['updated_at'])) ?></span>
                </div>
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
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-grid">
                    <!-- Student Name -->
                    <div class="form-group">
                        <label for="student_name">
                            Student Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="student_name" 
                            name="student_name" 
                            class="form-control" 
                            placeholder="e.g., Ahmad bin Ali"
                            value="<?= htmlspecialchars($student['student_name']) ?>"
                            required
                        >
                        <span class="helper-text">Full name of the student</span>
                    </div>

                    <!-- RFID UID -->
                    <div class="form-group">
                        <label for="rfid_uid">
                            RFID UID <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="rfid_uid" 
                            name="rfid_uid" 
                            class="form-control" 
                            placeholder="e.g., 4A3B2C1D"
                            value="<?= htmlspecialchars($student['rfid_uid']) ?>"
                            required
                        >
                        <span class="helper-text">Unique RFID card identifier</span>
                    </div>

                    <!-- Parent -->
                    <div class="form-group">
                        <label for="parent_id">
                            Parent/Guardian
                        </label>
                        <select id="parent_id" name="parent_id" class="form-control">
                            <option value="">-- Select Parent --</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?= $parent['parent_id'] ?>" 
                                    <?= $student['parent_id'] == $parent['parent_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($parent['parent_name']) ?>
                                    <?= $parent['phone_primary'] ? ' - ' . htmlspecialchars($parent['phone_primary']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="helper-text">Link to parent account (optional)</span>
                    </div>

                    <!-- Assigned Bus -->
                    <div class="form-group">
                        <label for="bus_id">
                            Assigned Bus
                        </label>
                        <select id="bus_id" name="bus_id" class="form-control">
                            <option value="">-- No Bus Assigned --</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?= $bus['bus_id'] ?>" 
                                    <?= $student['bus_id'] == $bus['bus_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bus['bus_number']) ?> - <?= htmlspecialchars($bus['license_plate']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="helper-text">Select bus for this student (optional)</span>
                    </div>

                    <!-- Date of Birth -->
                    <div class="form-group">
                        <label for="date_of_birth">
                            Date of Birth
                        </label>
                        <input 
                            type="date" 
                            id="date_of_birth" 
                            name="date_of_birth" 
                            class="form-control" 
                            value="<?= htmlspecialchars($student['date_of_birth'] ?? '') ?>"
                        >
                        <span class="helper-text">Student's date of birth</span>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="form-group">
                        <label for="emergency_contact">
                            Emergency Contact
                        </label>
                        <input 
                            type="text" 
                            id="emergency_contact" 
                            name="emergency_contact" 
                            class="form-control" 
                            placeholder="e.g., +60123456789"
                            value="<?= htmlspecialchars($student['emergency_contact'] ?? '') ?>"
                        >
                        <span class="helper-text">Emergency contact phone number</span>
                    </div>

                    <!-- Payment Status -->
                    <div class="form-group">
                        <label for="payment_status">
                            Payment Status
                        </label>
                        <select id="payment_status" name="payment_status" class="form-control">
                            <option value="unpaid" <?= $student['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            <option value="paid" <?= $student['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        </select>
                        <span class="helper-text">Current payment status</span>
                    </div>

                    <!-- Address -->
                    <div class="form-group full-width">
                        <label for="address">
                            Address
                        </label>
                        <textarea 
                            id="address" 
                            name="address" 
                            class="form-control" 
                            placeholder="Enter student's home address"
                        ><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                        <span class="helper-text">Student's residential address (optional)</span>
                    </div>

                    <!-- Photo Upload -->
                    <div class="form-group full-width">
                        <label for="photo">
                            Update Student Photo
                        </label>
                        <div class="file-upload-wrapper">
                            <label for="photo" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);">Click to upload new photo</div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">JPG or PNG, max 2MB (leave blank to keep current photo)</div>
                                </div>
                            </label>
                            <input 
                                type="file" 
                                id="photo" 
                                name="photo" 
                                accept="image/jpeg,image/jpg,image/png"
                            >
                        </div>
                        <span class="helper-text">Upload new student avatar (optional - current photo will be kept if not uploaded)</span>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="<?= SITE_URL ?>/staff/students.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Student
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    
    <script>
        // Preview selected file name
        document.getElementById('photo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const label = document.querySelector('.file-upload-label div div:first-child');
                label.textContent = 'Selected: ' + fileName;
            }
        });
    </script>
    
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
