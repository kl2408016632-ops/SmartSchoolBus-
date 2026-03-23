<?php
/**
 * SelamatRide SmartSchoolBus
 * Login Page
 */
require_once 'config.php';
require_once __DIR__ . '/includes/MfaTotp.php';

$error = '';
$mfaRequired = isset($_SESSION['pending_mfa_user_id'], $_SESSION['pending_mfa_expires'])
    && $_SESSION['pending_mfa_expires'] >= time();

if (!$mfaRequired) {
    unset($_SESSION['pending_mfa_user_id'], $_SESSION['pending_mfa_expires'], $_SESSION['pending_mfa_attempts']);
}

function finalizeLogin(array $user, PDO $pdo, $auth): void
{
    $sessionData = [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'login_time' => time()
    ];

    $roleSessionId = $auth->createMultiRoleSession($user['user_id'], $user['role_name'], $sessionData);

    if (!$roleSessionId) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['login_time'] = time();
    }

    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $updateStmt->execute([$user['user_id']]);

    unset($_SESSION['pending_mfa_user_id'], $_SESSION['pending_mfa_expires'], $_SESSION['pending_mfa_attempts']);

    switch ($user['role_name']) {
        case 'admin':
            header('Location: ' . SITE_URL . '/admin/dashboard.php');
            exit();
        case 'staff':
            header('Location: ' . SITE_URL . '/staff/dashboard.php');
            exit();
        case 'driver':
            header('Location: ' . SITE_URL . '/driver/dashboard.php');
            exit();
        default:
            throw new RuntimeException('Invalid user role');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $step = $_POST['login_step'] ?? 'credentials';

        try {
            if ($step === 'mfa' && $mfaRequired) {
                $mfaCode = trim($_POST['mfa_code'] ?? '');
                $attempts = (int)($_SESSION['pending_mfa_attempts'] ?? 0);

                if ($attempts >= 5) {
                    unset($_SESSION['pending_mfa_user_id'], $_SESSION['pending_mfa_expires'], $_SESSION['pending_mfa_attempts']);
                    $error = 'Too many invalid MFA attempts. Please login again.';
                } else {
                    $stmt = $pdo->prepare("\n                        SELECT u.*, r.role_name\n                        FROM users u\n                        JOIN roles r ON u.role_id = r.role_id\n                        WHERE u.user_id = ? AND u.status = 'active'\n                    ");
                    $stmt->execute([(int)$_SESSION['pending_mfa_user_id']]);
                    $user = $stmt->fetch();

                    if (!$user || empty($user['mfa_enabled']) || empty($user['mfa_secret'])) {
                        unset($_SESSION['pending_mfa_user_id'], $_SESSION['pending_mfa_expires'], $_SESSION['pending_mfa_attempts']);
                        $error = 'MFA session invalid. Please login again.';
                    } elseif (!MfaTotp::verifyCode($user['mfa_secret'], $mfaCode)) {
                        $_SESSION['pending_mfa_attempts'] = $attempts + 1;
                        $error = 'Invalid authentication code.';
                    } else {
                        finalizeLogin($user, $pdo, $auth);
                    }
                }
            } else {
                $username = sanitize($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                $stmt = $pdo->prepare("\n                    SELECT u.*, r.role_name\n                    FROM users u\n                    JOIN roles r ON u.role_id = r.role_id\n                    WHERE u.username = ? AND u.status = 'active'\n                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!empty($user['mfa_enabled']) && !empty($user['mfa_secret'])) {
                        $_SESSION['pending_mfa_user_id'] = (int)$user['user_id'];
                        $_SESSION['pending_mfa_expires'] = time() + 300;
                        $_SESSION['pending_mfa_attempts'] = 0;
                        $mfaRequired = true;
                    } else {
                        finalizeLogin($user, $pdo, $auth);
                    }
                } else {
                    $error = !$user ? 'Username not found' : 'Invalid password';
                }
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <a href="index.php" class="close-btn" title="Back to home">×</a>

            <div class="login-header">
                <h1>Welcome Back</h1>
                <p>Login to SelamatRide SmartSchoolBus</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($mfaRequired): ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="login_step" value="mfa">

                    <div class="form-group">
                        <label for="mfa_code">Authentication Code</label>
                        <input
                            type="text"
                            id="mfa_code"
                            name="mfa_code"
                            class="form-input <?php echo $error ? 'error' : ''; ?>"
                            placeholder="Enter 6-digit code"
                            required
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                        >
                    </div>

                    <button type="submit" class="login-btn">Verify and Login</button>
                </form>
                <div class="login-footer">Enter code from your authenticator app</div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="login_step" value="credentials">

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input <?php echo $error ? 'error' : ''; ?>"
                            placeholder="Enter your username"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input <?php echo $error ? 'error' : ''; ?>"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <button type="submit" class="login-btn">Login</button>
                </form>
            <?php endif; ?>

            <div class="login-footer">
                Forgot password? <a href="#" style="color: var(--primary-blue);">Contact admin</a>
            </div>
        </div>
    </div>
</body>
</html>
