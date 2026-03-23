<?php
/**
 * SelamatRide SmartSchoolBus - Staff Profile
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['staff']);

$pageTitle = "My Profile";
$user = getCurrentUser();
$userId = $user['user_id'];

// Handle form submissions
$message = '';
$messageType = '';

// Update Profile Information
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($fullName) || empty($email)) {
            $message = 'Full name and email are required';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email format';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    $message = 'Email already used by another account';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$fullName, $email, $phone, $userId]);
                    
                    $_SESSION['full_name'] = $fullName;
                    
                    $message = 'Profile updated successfully';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                error_log("Profile Update Error: " . $e->getMessage());
                $message = 'Failed to update profile';
                $messageType = 'error';
            }
        }
    }
}

// Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'All password fields are required';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = 'New password must be at least 8 characters';
            $messageType = 'error';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $message = 'Password must contain at least one number (0-9)';
            $messageType = 'error';
        } elseif (!preg_match('/[!@#$%^&*()]/', $newPassword)) {
            $message = 'Password must contain at least one symbol (!@#$%^&*)';
            $messageType = 'error';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $message = 'Password must contain at least one uppercase letter';
            $messageType = 'error';
        } elseif (!preg_match('/[a-z]/', $newPassword)) {
            $message = 'Password must contain at least one lowercase letter';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch();
                
                if (!password_verify($currentPassword, $userData['password_hash'])) {
                    $message = 'Current password is incorrect';
                    $messageType = 'error';
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    $message = 'Password changed successfully';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                error_log("Password Change Error for User ID {$userId}: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $message = 'Failed to change password. Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Upload Avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 2 * 1024 * 1024;
            
            $fileType = $_FILES['avatar']['type'];
            $fileSize = $_FILES['avatar']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $message = 'Only JPG, JPEG, and PNG files are allowed';
                $messageType = 'error';
            } elseif ($fileSize > $maxSize) {
                $message = 'File size must be less than 2MB';
                $messageType = 'error';
            } else {
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                    $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $oldAvatar = $stmt->fetchColumn();
                    if ($oldAvatar && file_exists(__DIR__ . '/../' . $oldAvatar)) {
                        unlink(__DIR__ . '/../' . $oldAvatar);
                    }
                    
                    $avatarUrl = 'uploads/avatars/' . $filename;
                    $stmt = $pdo->prepare("UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$avatarUrl, $userId]);
                    
                    $message = 'Avatar uploaded successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to upload avatar';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Please select an image file';
            $messageType = 'error';
        }
    }
}

// Get fresh user data
try {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Fetch User Error: " . $e->getMessage());
    $userData = $user;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentPage = "profile";
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
        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        .profile-sidebar {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            height: fit-content;
        }

        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            border: 4px solid var(--border-color);
            object-fit: cover;
        }

        .avatar-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .avatar-upload:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .avatar-upload input {
            display: none;
        }

        .profile-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .profile-role {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .profile-info {
            text-align: left;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .info-item i {
            width: 20px;
            color: var(--primary-color);
        }

        .profile-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .profile-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header i {
            width: 40px;
            height: 40px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            background: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
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

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-input-wrapper input {
            padding-right: 45px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: width 0.3s, background-color 0.3s;
        }

        .password-requirements {
            background: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 12px;
            color: var(--text-primary);
        }

        .requirement:last-child {
            margin-bottom: 0;
        }

        .requirement i {
            font-size: 14px;
            transition: all 0.2s;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/staff_header.php'; ?>
    <?php include 'includes/staff_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-user"></i> My Profile</h1>
                <p>Manage your account information and settings</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="avatar-container">
                    <?php if (!empty($userData['avatar_url']) && file_exists(__DIR__ . '/../' . $userData['avatar_url'])): ?>
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($userData['avatar_url']) ?>?v=<?= time() ?>" 
                             class="profile-avatar" 
                             alt="<?= htmlspecialchars($userData['full_name']) ?>">
                    <?php else: ?>
                        <div class="profile-avatar">
                            <?= strtoupper(substr($userData['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="upload_avatar" value="1">
                        <label class="avatar-upload">
                            <i class="fas fa-camera" style="color: white;"></i>
                            <input type="file" name="avatar" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                        </label>
                    </form>
                </div>

                <div class="profile-name"><?= htmlspecialchars($userData['full_name']) ?></div>
                <div class="profile-role"><?= htmlspecialchars($userData['role_name']) ?></div>

                <div class="profile-info">
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span><?= htmlspecialchars($userData['email']) ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user"></i>
                        <span><?= htmlspecialchars($userData['username']) ?></span>
                    </div>
                    <?php if (!empty($userData['phone'])): ?>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($userData['phone']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <i class="fas fa-calendar"></i>
                        <span>Joined <?= date('M Y', strtotime($userData['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="profile-content">
                <!-- Edit Profile -->
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-user-edit"></i>
                        <h2 class="card-title">Edit Profile</h2>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($userData['full_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="+60123456789">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-lock"></i>
                        <h2 class="card-title">Change Password</h2>
                    </div>
                    
                    <form method="POST" id="changePasswordForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Current Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" name="current_password" id="current_password" required>
                                <i class="fas fa-eye-slash password-toggle" onclick="togglePassword('current_password')"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> New Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" name="new_password" id="new_password" required minlength="8" oninput="checkPasswordStrength()">
                                <i class="fas fa-eye-slash password-toggle" onclick="togglePassword('new_password')"></i>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="password-strength-bar"></div>
                            </div>
                            <div class="password-requirements" id="password-requirements" style="margin-top: 10px; font-size: 12px;">
                                <div class="requirement" id="req-length">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                    <span>Minimum 8 characters</span>
                                </div>
                                <div class="requirement" id="req-number">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                    <span>At least one numeral (0-9)</span>
                                </div>
                                <div class="requirement" id="req-symbol">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                    <span>At least one symbol (!@#$%^&*)</span>
                                </div>
                                <div class="requirement" id="req-uppercase">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                    <span>At least one uppercase letter</span>
                                </div>
                                <div class="requirement" id="req-lowercase">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                    <span>At least one lowercase letter</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm New Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
                                <i class="fas fa-eye-slash password-toggle" onclick="togglePassword('confirm_password')"></i>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include '../admin/includes/admin_scripts.php'; ?>
    
    <script>
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const bar = document.getElementById('password-strength-bar');
            
            // Check each requirement
            const hasLength = password.length >= 8;
            const hasNumber = /[0-9]/.test(password);
            const hasSymbol = /[!@#$%^&*()]/.test(password);
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            
            // Update requirement icons
            updateRequirement('req-length', hasLength);
            updateRequirement('req-number', hasNumber);
            updateRequirement('req-symbol', hasSymbol);
            updateRequirement('req-uppercase', hasUppercase);
            updateRequirement('req-lowercase', hasLowercase);
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength++;
            if (hasNumber) strength++;
            if (hasSymbol) strength++;
            if (hasUppercase) strength++;
            if (hasLowercase) strength++;
            
            const percentage = (strength / 5) * 100;
            bar.style.width = percentage + '%';
            
            if (strength <= 2) {
                bar.style.backgroundColor = 'var(--danger)';
            } else if (strength <= 3) {
                bar.style.backgroundColor = 'var(--warning)';
            } else {
                bar.style.backgroundColor = 'var(--success)';
            }
        }
        
        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            
            if (met) {
                icon.className = 'fas fa-check-circle';
                icon.style.color = '#10b981';
            } else {
                icon.className = 'fas fa-times-circle';
                icon.style.color = '#ef4444';
            }
        }
    </script>
    
    <?php include 'includes/logout_feedback_interceptor.php'; ?>
</body>
</html>
