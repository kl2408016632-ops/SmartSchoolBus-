<?php
/**
 * SelamatRide SmartSchoolBus - Create User
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['admin']);

$pageTitle = "Create User";
$currentPage = "users";

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
            'full_name' => trim($_POST['full_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'role_id' => (int)($_POST['role_id'] ?? 0),
            'status' => $_POST['status'] ?? 'active'
        ];

        // Validation
        if (empty($formData['full_name'])) {
            $errors[] = 'Full name is required.';
        }

        if (empty($formData['username'])) {
            $errors[] = 'Username is required.';
        } elseif (strlen($formData['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$formData['username']]);
            if ($stmt->fetch()) {
                $errors[] = 'Username already exists.';
            }
        }

        if (!empty($formData['email'])) {
            if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$formData['email']]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email already exists.';
                }
            }
        }

        if (empty($formData['password'])) {
            $errors[] = 'Password is required.';
        } elseif (strlen($formData['password']) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($formData['password'] !== $formData['password_confirm']) {
            $errors[] = 'Passwords do not match.';
        }

        if ($formData['role_id'] === 0) {
            $errors[] = 'Please select a role.';
        }

        // Handle avatar upload
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'Invalid file type. Only JPG, PNG, and GIF images are allowed.';
            }
            
            // Validate file size (max 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'File size must be less than 2MB.';
            }
            
            if (empty($errors)) {
                // Generate unique filename
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'user_' . time() . '_' . uniqid() . '.' . $extension;
                $uploadPath = __DIR__ . '/../uploads/avatars/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $avatarPath = 'uploads/avatars/' . $filename;
                } else {
                    $errors[] = 'Failed to upload avatar. Please try again.';
                }
            }
        } else {
            // No avatar uploaded, use default
            $avatarPath = 'uploads/avatars/default.svg';
        }

        // If no errors, create user
        if (empty($errors)) {
            try {
                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO users (full_name, username, email, phone, password_hash, role_id, status, avatar_url, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['full_name'],
                    $formData['username'],
                    $formData['email'] ?: null,
                    $formData['phone'] ?: null,
                    $passwordHash,
                    $formData['role_id'],
                    $formData['status'],
                    $avatarPath
                ]);

                // Log activity
                logActivity('create', 'user', $pdo->lastInsertId(), [
                    'username' => $formData['username'],
                    'role_id' => $formData['role_id']
                ]);

                // Redirect with success message
                header('Location: ' . SITE_URL . '/admin/users.php?success=created');
                exit;

            } catch (Exception $e) {
                error_log("Create User Error: " . $e->getMessage());
                $errors[] = 'Failed to create user. Please try again.';
            }
        }
    }
}

// Fetch roles
try {
    $roles = $pdo->query("SELECT role_id, role_name FROM roles ORDER BY role_name")->fetchAll();
} catch (Exception $e) {
    error_log("Fetch Roles Error: " . $e->getMessage());
    $roles = [];
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-label .required {
            color: var(--danger);
        }

        .form-input, .form-select {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--card-bg);
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input.error {
            border-color: var(--danger);
        }

        .form-buttons {
            display: flex;
            gap: 12px;
            margin-top: 32px;
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

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert ul {
            margin: 0;
            padding-left: 20px;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
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
                <h1>Create New User</h1>
                <p>Add a new user to the system</p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle" style="margin-top: 2px;"></i>
                <div>
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-grid">
                    <!-- Full Name -->
                    <div class="form-group">
                        <label class="form-label">
                            Full Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="full_name" 
                            class="form-input <?= in_array('Full name is required.', $errors) ? 'error' : '' ?>"
                            value="<?= htmlspecialchars($formData['full_name'] ?? '') ?>"
                            placeholder="Enter full name"
                            required
                        >
                    </div>

                    <!-- Username -->
                    <div class="form-group">
                        <label class="form-label">
                            Username <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-input"
                            value="<?= htmlspecialchars($formData['username'] ?? '') ?>"
                            placeholder="Enter username"
                            required
                        >
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label">
                            Email
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-input"
                            value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                            placeholder="Enter email address"
                        >
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label class="form-label">
                            Phone Number
                        </label>
                        <input 
                            type="text" 
                            name="phone" 
                            class="form-input"
                            value="<?= htmlspecialchars($formData['phone'] ?? '') ?>"
                            placeholder="+60123456789"
                        >
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label">
                            Password <span class="required">*</span>
                        </label>
                        <div class="password-toggle">
                            <input 
                                type="password" 
                                name="password" 
                                id="password"
                                class="form-input"
                                placeholder="Enter password"
                                required
                            >
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label class="form-label">
                            Confirm Password <span class="required">*</span>
                        </label>
                        <div class="password-toggle">
                            <input 
                                type="password" 
                                name="password_confirm" 
                                id="password_confirm"
                                class="form-input"
                                placeholder="Re-enter password"
                                required
                            >
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('password_confirm')">
                                <i class="fas fa-eye" id="password_confirm-icon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label class="form-label">
                            Role <span class="required">*</span>
                        </label>
                        <select name="role_id" class="form-select" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['role_id'] ?>" 
                                    <?= (isset($formData['role_id']) && $formData['role_id'] == $role['role_id']) ? 'selected' : '' ?>>
                                    <?= ucfirst($role['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label">
                            Status <span class="required">*</span>
                        </label>
                        <select name="status" class="form-select" required>
                            <option value="active" <?= (!isset($formData['status']) || $formData['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (isset($formData['status']) && $formData['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <!-- Avatar Upload -->
                    <div class="form-group full-width">
                        <label class="form-label">
                            Profile Picture
                        </label>
                        <input 
                            type="file" 
                            name="avatar" 
                            class="form-input"
                            accept="image/jpeg,image/jpg,image/png,image/gif"
                            onchange="previewAvatar(this)"
                        >
                        <span class="form-hint">Optional. Max 2MB. Supported formats: JPG, PNG, GIF</span>
                        
                        <!-- Avatar Preview -->
                        <div id="avatarPreview" style="margin-top: 16px; display: none;">
                            <img id="avatarPreviewImg" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);">
                        </div>
                    </div>
                </div>

                <!-- Form Buttons -->
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function previewAvatar(input) {
            const preview = document.getElementById('avatarPreview');
            const previewImg = document.getElementById('avatarPreviewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
