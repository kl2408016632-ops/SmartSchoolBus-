<?php
require_once 'config.php';
require_once __DIR__ . '/includes/MfaTotp.php';

requireLogin();

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$message = '';
$messageType = '';

if (!isset($_SESSION['mfa_setup_secret'])) {
    $_SESSION['mfa_setup_secret'] = MfaTotp::generateSecret();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'enable') {
            $secret = $_SESSION['mfa_setup_secret'] ?? '';
            $code = $_POST['mfa_code'] ?? '';

            if (empty($secret) || !MfaTotp::verifyCode($secret, $code)) {
                $message = 'Invalid authenticator code.';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET mfa_enabled = 1, mfa_secret = ? WHERE user_id = ?");
                $stmt->execute([$secret, $user['user_id']]);
                unset($_SESSION['mfa_setup_secret']);
                $user['mfa_enabled'] = 1;
                $user['mfa_secret'] = $secret;
                $message = 'MFA enabled successfully.';
                $messageType = 'success';
            }
        }

        if ($action === 'disable') {
            $code = $_POST['mfa_code'] ?? '';
            if (empty($user['mfa_secret']) || !MfaTotp::verifyCode($user['mfa_secret'], $code)) {
                $message = 'Invalid authenticator code.';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET mfa_enabled = 0, mfa_secret = NULL WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $user['mfa_enabled'] = 0;
                $user['mfa_secret'] = null;
                $_SESSION['mfa_setup_secret'] = MfaTotp::generateSecret();
                $message = 'MFA disabled successfully.';
                $messageType = 'success';
            }
        }
    }
}

$setupSecret = $_SESSION['mfa_setup_secret'] ?? MfaTotp::generateSecret();
$otpauth = MfaTotp::buildOtpAuthUri('SmartSchoolBus', $user['username'], $setupSecret);
$qrUrls = [
    'https://quickchart.io/qr?size=220&text=' . rawurlencode($otpauth),
    'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($otpauth)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA Security - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: Inter, sans-serif; background: #0f172a; color: #e2e8f0; margin:0; }
        .wrap { max-width: 720px; margin: 40px auto; background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; }
        .msg { padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .msg.error { background: rgba(239,68,68,.15); border: 1px solid #ef4444; }
        .msg.success { background: rgba(16,185,129,.15); border: 1px solid #10b981; }
        .row { display: grid; gap: 16px; }
        .secret { font-family: monospace; letter-spacing: 1px; background:#0b1220; border:1px solid #334155; padding:10px; border-radius:8px; word-break: break-all; }
        input { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #334155; background: #0b1220; color: #e2e8f0; }
        button { padding: 10px 14px; border: none; border-radius: 8px; background:#3b82f6; color:#fff; cursor:pointer; }
        .muted { color: #94a3b8; font-size: 14px; }
        a { color: #60a5fa; }
    </style>
</head>
<body>
    <div class="wrap">
        <h2>Multi-Factor Authentication (MFA)</h2>
        <p class="muted">Use Google Authenticator, Microsoft Authenticator, or Authy.</p>

        <?php if ($message): ?>
            <div class="msg <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($user['mfa_enabled']) && !empty($user['mfa_secret'])): ?>
            <p><strong>Status:</strong> Enabled</p>
            <form method="POST" class="row">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="action" value="disable">
                <label>Enter current 6-digit MFA code to disable:</label>
                <input type="text" name="mfa_code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required>
                <button type="submit">Disable MFA</button>
            </form>
        <?php else: ?>
            <p><strong>Status:</strong> Disabled</p>
            <ol class="muted">
                <li>Scan this QR code in your authenticator app.</li>
                <li>Enter the 6-digit code to confirm.</li>
            </ol>
            <p>
                <img id="mfaQrImage" src="<?= htmlspecialchars($qrUrls[0]) ?>" alt="MFA QR" width="220" height="220">
            </p>
            <p class="muted">Manual key:</p>
            <div class="secret"><?= htmlspecialchars($setupSecret) ?></div>
            <p class="muted" style="margin-top: 10px;">If QR does not load, use the manual key above or this OTP link:</p>
            <div class="secret" style="font-size: 12px;"><?= htmlspecialchars($otpauth) ?></div>

            <form method="POST" class="row" style="margin-top:16px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="action" value="enable">
                <label>Authenticator code:</label>
                <input type="text" name="mfa_code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required>
                <button type="submit">Enable MFA</button>
            </form>
        <?php endif; ?>

        <p style="margin-top:18px;"><a href="javascript:history.back()">Back</a></p>
    </div>

    <script>
        (function () {
            var qrSources = [
                <?= json_encode($qrUrls[0]) ?>,
                <?= json_encode($qrUrls[1]) ?>
            ];
            var idx = 0;
            var img = document.getElementById('mfaQrImage');
            if (!img) {
                return;
            }

            img.addEventListener('error', function () {
                idx += 1;
                if (idx < qrSources.length) {
                    img.src = qrSources[idx];
                }
            });
        })();
    </script>
</body>
</html>
