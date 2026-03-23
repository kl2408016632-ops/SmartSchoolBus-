<?php
/**
 * Staff Issue Reporting / Notification Creation
 * Enterprise-grade complaint submission system
 */
session_start();
require_once '../config.php';
require_once '../includes/auth_middleware.php';

// Require staff role
requireRole(['staff']);

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? '';
        $messageText = trim($_POST['message'] ?? '');

        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Title is required';
        } elseif (strlen($title) < 10) {
            $errors[] = 'Title must be at least 10 characters';
        } elseif (strlen($title) > 200) {
            $errors[] = 'Title must not exceed 200 characters';
        }

        if (empty($category)) {
            $errors[] = 'Category is required';
        } elseif (!in_array($category, ['RFID', 'Bus', 'Student', 'System', 'Other'])) {
            $errors[] = 'Invalid category selected';
        }

        if (empty($messageText)) {
            $errors[] = 'Message is required';
        } elseif (strlen($messageText) < 20) {
            $errors[] = 'Message must be at least 20 characters';
        } elseif (strlen($messageText) > 5000) {
            $errors[] = 'Message must not exceed 5000 characters';
        }

        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        } else {
            try {
                // Insert notification
                    $typeMap = [
                        'RFID' => 'alert',
                        'Bus' => 'warning',
                        'Student' => 'warning',
                        'System' => 'alert',
                        'Other' => 'info'
                    ];
                    $notificationType = $typeMap[$category] ?? 'info';

                    $adminStmt = $pdo->prepare("\n                    SELECT u.user_id\n                    FROM users u\n                    JOIN roles r ON u.role_id = r.role_id\n                    WHERE r.role_name = 'admin' AND u.status = 'active'\n                ");
                    $adminStmt->execute();
                    $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (empty($adminIds)) {
                        throw new RuntimeException('No active admin recipient found.');
                    }

                    $insertStmt = $pdo->prepare("\n                    INSERT INTO notifications (sender_id, recipient_id, title, message, type, related_entity, is_read, created_at)\n                    VALUES (?, ?, ?, ?, ?, ?, 0, NOW())\n                ");

                    foreach ($adminIds as $adminId) {
                        $insertStmt->execute([
                            (int)$userId,
                            (int)$adminId,
                            $title,
                            $messageText,
                            $notificationType,
                            $category
                        ]);
                    }

                    // Log the action
                    error_log("Notification created by staff ID: {$userId}, Title: {$title}, Category: {$category}, Recipients: " . count($adminIds));
                // Log the action
                error_log("Notification created by staff ID: {$userId}, Title: {$title}, Category: {$category}");

                // Redirect with success message
                $_SESSION['success_message'] = 'Issue reported successfully! Admin will review it soon.';
                header('Location: notifications.php');
                exit;
                
            } catch (Exception $e) {
                error_log("Notification Creation Error: " . $e->getMessage());
                $message = 'Failed to submit notification. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Report Issue";
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --primary: #3b82f6;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --border: #334155;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 24px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .form-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 32px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-label .required {
            color: var(--danger);
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 150px;
            line-height: 1.6;
        }

        .form-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .char-counter {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: right;
            margin-top: 4px;
        }

        .category-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 12px;
            padding: 16px;
            background: var(--bg-primary);
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .category-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .category-item i {
            width: 20px;
            text-align: center;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            flex: 1;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:disabled {
            background: var(--border);
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #2d3b4e;
        }

        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-box h3 {
            color: var(--primary);
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box ul {
            list-style: none;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .info-box li {
            padding: 4px 0;
            padding-left: 20px;
            position: relative;
        }

        .info-box li:before {
            content: "•";
            position: absolute;
            left: 8px;
            color: var(--primary);
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .form-card {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-exclamation-triangle"></i> Report System Issue</h1>
            <p>Submit complaints, bugs, or operational issues to the admin team</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> When to Submit a Report</h3>
            <ul>
                <li>RFID card reader malfunctions or connectivity issues</li>
                <li>Bus mechanical problems or safety concerns</li>
                <li>Student boarding/attendance discrepancies</li>
                <li>System bugs or software errors</li>
                <li>Any operational issue affecting daily operations</li>
            </ul>
        </div>

        <form method="POST" action="" class="form-card" id="reportForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-heading"></i> Issue Title <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    name="title" 
                    class="form-input" 
                    placeholder="e.g., RFID Reader Not Working on Bus #03"
                    required
                    maxlength="200"
                    value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                    id="titleInput"
                >
                <div class="form-hint">Minimum 10 characters, maximum 200 characters</div>
                <div class="char-counter">
                    <span id="titleCounter">0</span> / 200
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-tag"></i> Category <span class="required">*</span>
                </label>
                <select name="category" class="form-select" required>
                    <option value="">-- Select Category --</option>
                    <option value="RFID" <?= ($_POST['category'] ?? '') === 'RFID' ? 'selected' : '' ?>>
                        RFID Issues
                    </option>
                    <option value="Bus" <?= ($_POST['category'] ?? '') === 'Bus' ? 'selected' : '' ?>>
                        Bus Issues
                    </option>
                    <option value="Student" <?= ($_POST['category'] ?? '') === 'Student' ? 'selected' : '' ?>>
                        Student Issues
                    </option>
                    <option value="System" <?= ($_POST['category'] ?? '') === 'System' ? 'selected' : '' ?>>
                        System Issues
                    </option>
                    <option value="Other" <?= ($_POST['category'] ?? '') === 'Other' ? 'selected' : '' ?>>
                        Other Issues
                    </option>
                </select>
                <div class="category-info">
                    <div class="category-item">
                        <i class="fas fa-id-card" style="color: #fb923c;"></i>
                        <span>RFID</span>
                    </div>
                    <div class="category-item">
                        <i class="fas fa-bus" style="color: #60a5fa;"></i>
                        <span>Bus</span>
                    </div>
                    <div class="category-item">
                        <i class="fas fa-user-graduate" style="color: #4ade80;"></i>
                        <span>Student</span>
                    </div>
                    <div class="category-item">
                        <i class="fas fa-exclamation-triangle" style="color: #f87171;"></i>
                        <span>System</span>
                    </div>
                    <div class="category-item">
                        <i class="fas fa-info-circle" style="color: #94a3b8;"></i>
                        <span>Other</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-align-left"></i> Detailed Description <span class="required">*</span>
                </label>
                <textarea 
                    name="message" 
                    class="form-textarea" 
                    placeholder="Provide detailed information about the issue:&#10;• What happened?&#10;• When did it occur?&#10;• Which bus/student/system was affected?&#10;• What was the impact?&#10;• Any other relevant details..."
                    required
                    maxlength="5000"
                    id="messageInput"
                ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                <div class="form-hint">Minimum 20 characters, maximum 5000 characters. Be as specific as possible.</div>
                <div class="char-counter">
                    <span id="messageCounter">0</span> / 5000
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        // Character counters
        const titleInput = document.getElementById('titleInput');
        const titleCounter = document.getElementById('titleCounter');
        const messageInput = document.getElementById('messageInput');
        const messageCounter = document.getElementById('messageCounter');
        const submitBtn = document.getElementById('submitBtn');

        function updateCounters() {
            titleCounter.textContent = titleInput.value.length;
            messageCounter.textContent = messageInput.value.length;
            
            // Validate
            const titleValid = titleInput.value.trim().length >= 10;
            const messageValid = messageInput.value.trim().length >= 20;
            
            submitBtn.disabled = !(titleValid && messageValid);
        }

        titleInput.addEventListener('input', updateCounters);
        messageInput.addEventListener('input', updateCounters);
        
        // Initialize counters
        updateCounters();

        // Form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const title = titleInput.value.trim();
            const message = messageInput.value.trim();
            
            if (title.length < 10) {
                e.preventDefault();
                alert('Title must be at least 10 characters long');
                return false;
            }
            
            if (message.length < 20) {
                e.preventDefault();
                alert('Message must be at least 20 characters long');
                return false;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        });
    </script>
</body>
</html>
