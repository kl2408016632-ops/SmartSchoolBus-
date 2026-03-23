<?php
/**
 * SelamatRide SmartSchoolBus - Logout Feedback Collection
 * Production-Grade User Experience Feedback System
 */
session_start();
require_once 'config.php';

// Check if feedback data exists (user just logged out)
if (!isset($_SESSION['feedback_data'])) {
    // No feedback data - redirect to login
    header('Location: index.php');
    exit;
}

$feedbackData = $_SESSION['feedback_data'];
$message = '';
$messageType = '';
$feedbackSubmitted = false;

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['feedback_csrf']) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    } else {
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
        $feedbackMessage = trim($_POST['message'] ?? '');
        
        // Validate rating
        if ($rating === null || $rating < 0 || $rating > 10) {
            $message = 'Please select a rating from 0 to 10.';
            $messageType = 'error';
        } else {
            try {
                // Get user IP address
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
                }
                
                // Get user agent
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                
                // Insert feedback into database (anonymous - no user_id stored for privacy)
                $stmt = $pdo->prepare("
                    INSERT INTO logout_feedback 
                    (user_id, user_role, rating, message, ip_address, user_agent, session_duration, created_at) 
                    VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $feedbackData['role'],
                    $rating,
                    !empty($feedbackMessage) ? $feedbackMessage : null,
                    $ipAddress,
                    $userAgent,
                    $feedbackData['session_duration']
                ]);
                
                $feedbackSubmitted = true;
                $message = 'Thank you for your feedback! Your input helps us improve the system.';
                $messageType = 'success';
                
                // Clear feedback data and CSRF token
                unset($_SESSION['feedback_data']);
                unset($_SESSION['feedback_csrf']);
                
            } catch (Exception $e) {
                error_log("Feedback Submission Error: " . $e->getMessage());
                $message = 'An error occurred while saving your feedback. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Skip feedback option
if (isset($_GET['skip'])) {
    // Clear feedback session data
    unset($_SESSION['feedback_data']);
    unset($_SESSION['feedback_csrf']);
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - SelamatRide SmartSchoolBus</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .feedback-container {
            max-width: 600px;
            width: 100%;
        }

        .feedback-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            padding: 32px;
            text-align: center;
            color: white;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 28px;
            color: #3b82f6;
        }

        .card-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .card-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .card-body {
            padding: 40px;
        }

        .question {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .rating-container {
            margin-bottom: 32px;
        }

        .rating-scale {
            display: grid;
            grid-template-columns: repeat(11, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }

        .rating-button {
            aspect-ratio: 1;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rating-button:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: translateY(-2px);
        }

        .rating-button.selected {
            border-color: #3b82f6;
            background: #3b82f6;
            color: white;
            transform: scale(1.1);
        }

        .rating-labels {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #64748b;
            padding: 0 4px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #1e293b;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.2s;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .form-group textarea::placeholder {
            color: #94a3b8;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border-radius: 8px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-secondary {
            background: white;
            color: #64748b;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .success-icon {
            font-size: 48px;
            color: #10b981;
            text-align: center;
            margin-bottom: 16px;
        }

        .success-message {
            text-align: center;
        }

        .success-message h2 {
            font-size: 22px;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .success-message p {
            color: #64748b;
            margin-bottom: 24px;
        }

        @media (max-width: 640px) {
            .rating-scale {
                grid-template-columns: repeat(6, 1fr);
            }

            .card-body {
                padding: 24px;
            }

            .question {
                font-size: 16px;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <div class="feedback-card">
            <div class="card-header">
                <div class="logo">
                    <i class="fas fa-bus"></i>
                </div>
                <h1>SelamatRide SmartSchoolBus</h1>
                <p>Secure RFID Student Boarding Verification System</p>
            </div>

            <div class="card-body">
                <?php if ($feedbackSubmitted): ?>
                    <!-- Success State -->
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="success-message">
                        <h2>Thank You!</h2>
                        <p><?= htmlspecialchars($message) ?></p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Return to Login
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Feedback Form -->
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="question">
                        Based on your experience using the SmartSchoolBus system, how likely are you to recommend this system to others?
                    </div>

                    <form method="POST" id="feedbackForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['feedback_csrf'] ?>">
                        <input type="hidden" name="submit_feedback" value="1">
                        <input type="hidden" name="rating" id="ratingInput" value="">

                        <div class="rating-container">
                            <div class="rating-scale" id="ratingScale">
                                <?php for ($i = 0; $i <= 10; $i++): ?>
                                    <button type="button" class="rating-button" data-rating="<?= $i ?>" onclick="selectRating(<?= $i ?>)">
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-labels">
                                <span>Not at all likely</span>
                                <span>Extremely likely</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message">Additional Comments (Optional)</label>
                            <textarea 
                                name="message" 
                                id="message" 
                                placeholder="Tell us what we can improve (e.g. RFID issues, attendance accuracy, dashboard usability)"
                            ></textarea>
                        </div>

                        <div class="button-group">
                            <a href="?skip=1" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Skip Feedback
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="fas fa-paper-plane"></i> Submit Feedback
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let selectedRating = null;

        function selectRating(rating) {
            selectedRating = rating;
            document.getElementById('ratingInput').value = rating;
            
            // Update UI
            const buttons = document.querySelectorAll('.rating-button');
            buttons.forEach(btn => {
                if (parseInt(btn.dataset.rating) === rating) {
                    btn.classList.add('selected');
                } else {
                    btn.classList.remove('selected');
                }
            });
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
        }

        // Prevent accidental form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
