<?php
/**
 * SelamatRide SmartSchoolBus - Create Notification
 * Staff Issue Reporting System
 * Production-Grade IoT SaaS System
 */
require_once '../config.php';
requireRole(['staff']); // Only staff can create notifications

$pageTitle = "Submit Issue Report";
$currentPage = "notifications";

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Collect and sanitize input
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // Validation
        if (empty($title)) {
            $errors[] = 'Issue title is required.';
        } elseif (strlen($title) < 5) {
            $errors[] = 'Issue title must be at least 5 characters.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Issue title must not exceed 255 characters.';
        }
        
        if (empty($category)) {
            $errors[] = 'Please select a category.';
        } elseif (!in_array($category, ['RFID', 'Bus', 'Student', 'System', 'Other'])) {
            $errors[] = 'Invalid category selected.';
        }
        
        if (empty($message)) {
            $errors[] = 'Issue description is required.';
        } elseif (strlen($message) < 20) {
            $errors[] = 'Issue description must be at least 20 characters for clarity.';
        } elseif (strlen($message) > 5000) {
            $errors[] = 'Issue description must not exceed 5000 characters.';
        }
        
        // If no errors, insert into database
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (sender_id, title, message, category, is_read, created_at) 
                    VALUES (?, ?, ?, ?, 0, NOW())
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $message,
                    $category
                ]);
                
                $success = true;
                
                // Redirect to staff dashboard with success message
                header('Location: ' . SITE_URL . '/staff/dashboard.php?notification_sent=1');
                exit;
                
            } catch (Exception $e) {
                error_log("Notification Creation Error: " . $e->getMessage());
                $errors[] = 'Failed to submit issue report. Please try again or contact system administrator.';
            }
        }
    }
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
    
    <style>
        :root {
            --primary-blue: #3b82f6;
            --primary-dark: #1e40af;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --border-color: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
        }

        .dashboard-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .dashboard-title h1 {
            font-size: 24px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .header-right {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .main-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 40px;
        }

        .page-title {
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 700;
        }

        .page-description {
            color: var(--text-secondary);
            margin-bottom: 32px;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header i {
            width: 40px;
            height: 40px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-error strong {
            color: var(--danger);
        }

        .alert ul {
            margin: 12px 0 0 20px;
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

        .form-group label .required {
            color: var(--danger);
        }

        .form-group small {
            display: block;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .category-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-rfid {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-blue);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .badge-bus {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-student {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-system {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .badge-other {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-secondary);
            border: 1px solid rgba(107, 114, 128, 0.2);
        }

        .form-actions {
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
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .info-card ul {
            margin: 0;
            padding-left: 20px;
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .info-card li strong {
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 20px;
                margin: 20px auto;
            }

            .form-card {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1><i class="fas fa-paper-plane"></i> Submit Issue Report</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span style="color: var(--text-secondary);">Logged in as:</span>
                <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
            </div>
            <a href="dashboard.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="main-container">
        <h2 class="page-title">Report an Issue</h2>
        <p class="page-description">
            Report RFID issues, bus delays, student problems, or system errors to administrators. 
            Provide detailed information to help us resolve the issue quickly.
        </p>

        <!-- Errors Display -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong><i class="fas fa-exclamation-circle"></i> Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Notification Form -->
        <div class="form-card">
            <div class="card-header">
                <i class="fas fa-edit"></i>
                <h3 class="card-title">Issue Report Form</h3>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Issue Title -->
                <div class="form-group">
                    <label>
                        Issue Title <span class="required">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           class="form-control" 
                           placeholder="e.g., RFID Card Not Detected on Bus 101"
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                           required
                           maxlength="255">
                    <small>Brief summary of the issue (5-255 characters)</small>
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label>
                        Issue Category <span class="required">*</span>
                    </label>
                    <select name="category" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <option value="RFID" <?= ($_POST['category'] ?? '') === 'RFID' ? 'selected' : '' ?>>
                            RFID - Card Detection Issues
                        </option>
                        <option value="Bus" <?= ($_POST['category'] ?? '') === 'Bus' ? 'selected' : '' ?>>
                            Bus - Vehicle or Route Issues
                        </option>
                        <option value="Student" <?= ($_POST['category'] ?? '') === 'Student' ? 'selected' : '' ?>>
                            Student - Boarding or Verification Issues
                        </option>
                        <option value="System" <?= ($_POST['category'] ?? '') === 'System' ? 'selected' : '' ?>>
                            System - Software Bugs or Errors
                        </option>
                        <option value="Other" <?= ($_POST['category'] ?? '') === 'Other' ? 'selected' : '' ?>>
                            Other - General Issues
                        </option>
                    </select>
                    <div class="category-badges">
                        <span class="badge badge-rfid">RFID Issues</span>
                        <span class="badge badge-bus">Bus Issues</span>
                        <span class="badge badge-student">Student Issues</span>
                        <span class="badge badge-system">System Bugs</span>
                        <span class="badge badge-other">Other Issues</span>
                    </div>
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label>
                        Detailed Description <span class="required">*</span>
                    </label>
                    <textarea name="message" 
                              class="form-control" 
                              rows="10"
                              placeholder="Provide detailed information about the issue:&#10;&#10;- What happened?&#10;- When did it occur?&#10;- Which student/bus/RFID card is affected?&#10;- Steps to reproduce (if applicable)&#10;- Any error messages seen"
                              required
                              maxlength="5000"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <small>Detailed explanation of the issue (20-5000 characters). Include student IDs, bus numbers, or RFID UIDs if applicable.</small>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Issue Report
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Info Card -->
        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-lightbulb"></i>
                <span>How to Write a Good Issue Report</span>
            </div>
            <ul>
                <li><strong>Be specific:</strong> Include student names, RFID UIDs, bus numbers, and timestamps</li>
                <li><strong>Describe the problem:</strong> What happened vs. what should have happened?</li>
                <li><strong>Include context:</strong> When did it start? Does it happen consistently?</li>
                <li><strong>Add details:</strong> Any error messages, unusual behavior, or system responses</li>
                <li><strong>Suggest urgency:</strong> If it's blocking operations, mention it clearly</li>
            </ul>
        </div>
    </div>
</body>
</html>
