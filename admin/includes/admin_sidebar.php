<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-menu">
        <!-- Dashboard -->
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- Management -->
        <div class="menu-section-title" style="padding: 16px 20px 8px; font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.5px;">
            Management
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/users.php" class="<?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
        </div>
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/buses.php" class="<?= $currentPage === 'buses' ? 'active' : '' ?>">
                <i class="fas fa-bus"></i>
                <span>Bus Management</span>
            </a>
        </div>
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/students.php" class="<?= $currentPage === 'students' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Student Management</span>
            </a>
        </div>
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/payments.php" class="<?= $currentPage === 'payments' ? 'active' : '' ?>">
                <i class="fas fa-money-check-alt"></i>
                <span>Payment Management</span>
            </a>
        </div>
        
        <!-- Reports & Communication -->
        <div class="menu-section-title" style="padding: 16px 20px 8px; font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.5px;">
            Reports & Communication
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/notifications.php" class="<?= $currentPage === 'notifications' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i>
                <span>Staff Notifications</span>
                <?php
                try {
                    $unreadStmt = $GLOBALS['pdo']->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
                    $unreadCount = $unreadStmt->fetch()['count'];
                    if ($unreadCount > 0): ?>
                        <span style="margin-left: auto; background: var(--danger); color: white; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; min-width: 20px; text-align: center;">
                            <?= $unreadCount ?>
                        </span>
                    <?php endif;
                } catch (Exception $e) {
                    error_log("Sidebar Notification Count Error: " . $e->getMessage());
                }
                ?>
            </a>
        </div>
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/reports.php" class="<?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
        </div>
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/admin/feedback.php" class="<?= $currentPage === 'feedback' ? 'active' : '' ?>">
                <i class="fas fa-comments"></i>
                <span>User Feedback</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
