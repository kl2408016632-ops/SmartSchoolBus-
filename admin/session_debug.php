<?php
/**
 * Session Debugging Page - FOR ADMIN USE ONLY
 * Shows current session state and role-specific cookies
 */
require_once '../config.php';
requireRole(['admin']);

if (empty($_SESSION) || !isset($_SESSION['user_id'])) {
    die('No session data');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Debug - Admin Only</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; }
        h2 { color: #569cd6; border-bottom: 2px solid #569cd6; padding-bottom: 10px; }
        .section { margin-bottom: 30px; background: #252526; padding: 15px; border-radius: 5px; border-left: 4px solid #007acc; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3e3e42; }
        th { background: #2d2d30; color: #ce9178; font-weight: bold; }
        tr:hover { background: #2d2d30; }
        .warning { color: #f48771; font-weight: bold; }
        .success { color: #6a9955; font-weight: bold; }
        .info { color: #9cdcfe; }
        code { background: #1e1e1e; padding: 2px 6px; border-radius: 3px; color: #ce9178; }
        a { color: #569cd6; text-decoration: none; }
        a:hover { underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Session Debug Information</h1>
        <p><a href="dashboard.php">← Back to Dashboard</a></p>

        <!-- Current Session Info -->
        <div class="section">
            <h2>Current Global $_SESSION</h2>
            <table>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Type</th>
                </tr>
                <?php foreach ($_SESSION as $key => $value): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($key) ?></code></td>
                        <td>
                            <?php 
                                if (is_array($value) || is_object($value)) {
                                    echo '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>';
                                } else {
                                    echo '<code>' . htmlspecialchars((string)$value) . '</code>';
                                }
                            ?>
                        </td>
                        <td><?= gettype($value) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Role-Specific Cookies -->
        <div class="section">
            <h2>Role-Specific Cookies</h2>
            <table>
                <tr>
                    <th>Cookie Name</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <?php
                $roles = ['admin', 'staff', 'driver'];
                foreach ($roles as $role):
                    $cookie_name = 'ROLE_SESSION_' . strtoupper($role);
                    $value = $_COOKIE[$cookie_name] ?? 'NOT_SET';
                    $status = (isset($_COOKIE[$cookie_name])) ? '<span class="success">✓ Active</span>' : '<span class="warning">✗ Missing</span>';
                ?>
                    <tr>
                        <td><code><?= $cookie_name ?></code></td>
                        <td><?= substr($value, 0, 20) ?>...</td>
                        <td><?= $status ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Current User Info -->
        <div class="section">
            <h2>Current User Information</h2>
            <?php
            $currentUser = getCurrentUser();
            if ($currentUser):
            ?>
                <table>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                    <?php foreach ($currentUser as $key => $value): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($key) ?></code></td>
                            <td><?= is_array($value) ? '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>' : htmlspecialchars((string)$value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p class="warning">⚠ Could not retrieve current user information</p>
            <?php endif; ?>
        </div>

        <!-- Server Info -->
        <div class="section">
            <h2>Server Information</h2>
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Session ID</td>
                    <td><code><?= session_id() ?></code></td>
                </tr>
                <tr>
                    <td>Session Status</td>
                    <td><?= session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive' ?></td>
                </tr>
                <tr>
                    <td>Session Name</td>
                    <td><code><?= session_name() ?></code></td>
                </tr>
                <tr>
                    <td>Remote Address</td>
                    <td><code><?= $_SERVER['REMOTE_ADDR'] ?? 'N/A' ?></code></td>
                </tr>
                <tr>
                    <td>User Agent</td>
                    <td><code><?= substr($_SERVER['HTTP_USER_AGENT'] ?? 'N/A', 0, 50) ?>...</code></td>
                </tr>
                <tr>
                    <td>Request URI</td>
                    <td><code><?= $_SERVER['REQUEST_URI'] ?? 'N/A' ?></code></td>
                </tr>
            </table>
        </div>

        <!-- All Cookies -->
        <div class="section">
            <h2>All Available Cookies</h2>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Value (first 50 chars)</th>
                </tr>
                <?php foreach ($_COOKIE as $name => $value): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($name) ?></code></td>
                        <td><code><?= htmlspecialchars(substr($value, 0, 50)) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Instructions for Testing</h2>
            <ol>
                <li>Open <strong>Tab 1</strong> and login as <strong>Admin</strong></li>
                <li>Visit this page (<code>/admin/session_debug.php</code>) in Tab 1 and take a screenshot</li>
                <li>Open <strong>Tab 2</strong> and login as <strong>Staff</strong></li>
                <li>Visit <code>/staff/session_debug.php</code> in Tab 2 and take a screenshot</li>
                <li>Go back to <strong>Tab 1</strong> (admin) and click a link</li>
                <li>Take another screenshot of <code>/admin/session_debug.php</code></li>
                <li>Share all screenshots to debug the issue</li>
            </ol>
        </div>
    </div>
</body>
</html>
