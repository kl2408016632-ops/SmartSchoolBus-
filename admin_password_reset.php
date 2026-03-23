<?php
/**
 * SelamatRide SmartSchoolBus
 * Password Reset Tool (Setup Only)
 * 
 * SECURITY: Delete this file after generating passwords!
 */

require_once 'config.php';

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_password') {
        $username = $_POST['username'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (!$username || !$new_password) {
            $error = "Username and password required";
        } else if (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters";
        } else {
            try {
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
                $stmt->execute([$password_hash, $username]);
                
                if ($stmt->rowCount() > 0) {
                    $success = "Password updated successfully for user: $username";
                } else {
                    $error = "User not found: $username";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get all users
$users = [];
try {
    $result = $pdo->query("SELECT user_id, username, full_name, role_id FROM users ORDER BY username");
    $users = $result->fetchAll();
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>SmartSchoolBus - Password Reset Tool</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .container { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
        h1 { color: #333; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px; margin: 10px 0; border-radius: 3px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 3px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; }
        form { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        input, button { padding: 8px; margin: 5px 0; }
        input { width: 100%; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 SmartSchoolBus - Password Reset Tool</h1>
        
        <div class="warning">
            <strong>⚠ SECURITY WARNING:</strong> This file should ONLY be used during initial setup!<br>
            Delete this file after setting passwords using FTP or file manager.
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="info">
            <strong>ℹ️ Current Users in Database:</strong>
        </div>

        <table>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Role</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                <td><?php echo $user['role_id'] === 1 ? 'Admin' : ($user['role_id'] === 2 ? 'Staff' : 'Driver'); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Set New Password</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update_password">
            <label><strong>Username:</strong></label>
            <select name="username" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo htmlspecialchars($user['username']); ?>">
                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['full_name']); ?>)
                </option>
                <?php endforeach; ?>
            </select>

            <label><strong>New Password:</strong></label>
            <input type="password" name="new_password" required minlength="6" placeholder="Min 6 characters">

            <button type="submit">🔒 Update Password</button>
        </form>

        <h2>🚀 Quick Setup</h2>
        <ol>
            <li>Select a user from the dropdown</li>
            <li>Enter a new password (e.g., "admin123")</li>
            <li>Click "Update Password"</li>
            <li>Delete this file afterward</li>
            <li>Login at <a href="login.php">login.php</a></li>
        </ol>

        <div class="info">
            <strong>Suggested Test Passwords:</strong><br>
            • Admin: <code>admin123</code><br>
            • Staff: <code>staff123</code><br>
            • Driver: <code>driver123</code>
        </div>

        <hr style="margin: 30px 0;">
        <p><strong>After setup is complete:</strong></p>
        <ul>
            <li>Delete this file: <code>admin_password_reset.php</code></li>
            <li>Cannot be accessed after deletion</li>
            <li>Login at: <code>http://localhost/SmartSchoolBus/login.php</code></li>
        </ul>
    </div>
</body>
</html>
