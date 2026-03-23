<?php
/**
 * SelamatRide SmartSchoolBus - Driver Settings
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['driver']);

$pageTitle = "Settings";
$currentPage = "settings";

$user = getCurrentUser();
$userId = $user['user_id'];

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $settings = [
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'browser_notifications' => isset($_POST['browser_notifications']) ? 1 : 0,
            'timezone' => $_POST['timezone'] ?? 'Asia/Kuala_Lumpur',
            'date_format' => $_POST['date_format'] ?? 'd/m/Y',
            'time_format' => $_POST['time_format'] ?? 'H:i',
            'theme' => $_POST['theme'] ?? 'dark',
            'sidebar_collapsed' => isset($_POST['sidebar_collapsed']) ? 1 : 0
        ];
        
        try {
            // Store as JSON in users table
            $settingsJson = json_encode($settings);
            $stmt = $pdo->prepare("UPDATE users SET settings = ?, updated_at = NOW() WHERE user_id = ?");
            $result = $stmt->execute([$settingsJson, $userId]);
            
            if ($result) {
                // Update session
                $_SESSION['user_settings'] = $settings;
                
                $message = 'Settings saved successfully! Changes will take effect immediately.';
                $messageType = 'success';
                
                // Reload user data
                $user = getCurrentUser();
            } else {
                $message = 'Failed to save settings. Please try again.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            error_log("Settings Save Error: " . $e->getMessage());
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get user settings
try {
    $stmt = $pdo->prepare("SELECT settings, mfa_enabled FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settingsRow = $stmt->fetch();
    $settingsData = $settingsRow['settings'] ?? null;
    $mfaEnabled = !empty($settingsRow['mfa_enabled']);
    
    if ($settingsData) {
        $userSettings = json_decode($settingsData, true);
    } else {
        // Default settings
        $userSettings = [
            'email_notifications' => 1,
            'browser_notifications' => 1,
            'timezone' => 'Asia/Kuala_Lumpur',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'theme' => 'dark',
            'sidebar_collapsed' => 0
        ];
    }
} catch (Exception $e) {
    error_log("Fetch Settings Error: " . $e->getMessage());
    $userSettings = [];
    $mfaEnabled = false;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
        .settings-container {
            max-width: 900px;
        }

        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
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

        .card-description {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-group small {
            display: block;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            background: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .switch-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .switch-group:last-child {
            border-bottom: none;
        }

        .switch-info {
            flex: 1;
        }

        .switch-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .switch-description {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-color);
            transition: .3s;
            border-radius: 28px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(24px);
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

        .radio-group {
            display: flex;
            gap: 16px;
            margin-top: 8px;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .radio-item label {
            font-size: 14px;
            color: var(--text-primary);
            cursor: pointer;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/driver_header.php'; ?>
    <?php include 'includes/driver_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-cog"></i> Settings</h1>
                <p>Manage your preferences and system settings</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="save_settings" value="1">

                <!-- Notifications -->
                <div class="settings-card">
                    <div class="card-header">
                        <i class="fas fa-bell"></i>
                        <div>
                            <h2 class="card-title">Notifications</h2>
                            <p class="card-description">Choose how you want to be notified</p>
                        </div>
                    </div>

                    <div class="switch-group">
                        <div class="switch-info">
                            <div class="switch-label">Email Notifications</div>
                            <div class="switch-description">Receive notifications via email</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="email_notifications" <?= !empty($userSettings['email_notifications']) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="switch-group">
                        <div class="switch-info">
                            <div class="switch-label">Browser Notifications</div>
                            <div class="switch-description">Receive push notifications in your browser</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="browser_notifications" <?= !empty($userSettings['browser_notifications']) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Regional Settings -->
                <div class="settings-card">
                    <div class="card-header">
                        <i class="fas fa-globe"></i>
                        <div>
                            <h2 class="card-title">Regional Settings</h2>
                            <p class="card-description">Set your timezone and date/time preferences</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Timezone</label>
                        <select name="timezone">
                            <option value="Asia/Kuala_Lumpur" <?= ($userSettings['timezone'] ?? '') === 'Asia/Kuala_Lumpur' ? 'selected' : '' ?>>Malaysia (Asia/Kuala_Lumpur)</option>
                            <option value="Asia/Singapore" <?= ($userSettings['timezone'] ?? '') === 'Asia/Singapore' ? 'selected' : '' ?>>Singapore (Asia/Singapore)</option>
                            <option value="Asia/Jakarta" <?= ($userSettings['timezone'] ?? '') === 'Asia/Jakarta' ? 'selected' : '' ?>>Indonesia (Asia/Jakarta)</option>
                            <option value="Asia/Bangkok" <?= ($userSettings['timezone'] ?? '') === 'Asia/Bangkok' ? 'selected' : '' ?>>Thailand (Asia/Bangkok)</option>
                            <option value="UTC" <?= ($userSettings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                        </select>
                        <small>All timestamps will be displayed in this timezone</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date Format</label>
                        <select name="date_format">
                            <option value="d/m/Y" <?= ($userSettings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (28/01/2026)</option>
                            <option value="m/d/Y" <?= ($userSettings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (01/28/2026)</option>
                            <option value="Y-m-d" <?= ($userSettings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2026-01-28)</option>
                            <option value="d M Y" <?= ($userSettings['date_format'] ?? '') === 'd M Y' ? 'selected' : '' ?>>DD Mon YYYY (28 Jan 2026)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Time Format</label>
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" id="time24" name="time_format" value="H:i" <?= ($userSettings['time_format'] ?? 'H:i') === 'H:i' ? 'checked' : '' ?>>
                                <label for="time24">24-hour (14:30)</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="time12" name="time_format" value="h:i A" <?= ($userSettings['time_format'] ?? '') === 'h:i A' ? 'checked' : '' ?>>
                                <label for="time12">12-hour (02:30 PM)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appearance -->
                <div class="settings-card">
                    <div class="card-header">
                        <i class="fas fa-palette"></i>
                        <div>
                            <h2 class="card-title">Appearance</h2>
                            <p class="card-description">Customize how your dashboard looks</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-moon"></i> Theme</label>
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" id="theme-dark" name="theme" value="dark" <?= ($userSettings['theme'] ?? 'dark') === 'dark' ? 'checked' : '' ?>>
                                <label for="theme-dark">Dark Mode</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="theme-light" name="theme" value="light" <?= ($userSettings['theme'] ?? '') === 'light' ? 'checked' : '' ?>>
                                <label for="theme-light">Light Mode</label>
                            </div>
                        </div>
                        <small>Note: Light mode is coming soon</small>
                    </div>

                    <div class="switch-group">
                        <div class="switch-info">
                            <div class="switch-label">Collapsed Sidebar</div>
                            <div class="switch-description">Start with sidebar minimized</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="sidebar_collapsed" <?= !empty($userSettings['sidebar_collapsed']) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Save Button -->
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>

            <div class="settings-card" style="margin-top: 24px;">
                <div class="card-header">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h2 class="card-title">MFA Security</h2>
                        <p class="card-description">Add an authenticator code on top of your password for extra protection</p>
                    </div>
                </div>

                <div class="switch-group" style="border-bottom: none; padding-bottom: 0;">
                    <div class="switch-info">
                        <div class="switch-label">Status: <?= $mfaEnabled ? 'Enabled' : 'Disabled' ?></div>
                        <div class="switch-description">
                            <?= $mfaEnabled ? 'Your account requires MFA at login.' : 'MFA is optional. Enable it to protect your account.' ?>
                        </div>
                    </div>
                    <a href="../mfa_setup.php" class="btn btn-primary">
                        <i class="fas fa-key"></i> <?= $mfaEnabled ? 'Manage / Disable MFA' : 'Enable MFA' ?>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/logout_feedback_interceptor.php'; ?>
    <?php include '../admin/includes/admin_scripts.php'; ?>
</body>
</html>
