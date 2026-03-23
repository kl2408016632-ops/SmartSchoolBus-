<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-menu">
        <!-- Dashboard -->
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- Daily Operations -->
        <div class="menu-section-title" style="padding: 16px 20px 8px; font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.5px;">
            Operations
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/daily_checklist.php" class="<?= $currentPage === 'checklist' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Daily Checklist</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/attendance.php" class="<?= $currentPage === 'attendance' ? 'active' : '' ?>">
                <i class="fas fa-qrcode"></i>
                <span>Attendance Records</span>
            </a>
        </div>
        
        <!-- Students & Management -->
        <div class="menu-section-title" style="padding: 16px 20px 8px; font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.5px;">
            Management
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/students.php" class="<?= $currentPage === 'students' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/payments.php" class="<?= $currentPage === 'payments' ? 'active' : '' ?>">
                <i class="fas fa-money-check-alt"></i>
                <span>Payment Management</span>
            </a>
        </div>
        
        <!-- Reports & Communication -->
        <div class="menu-section-title" style="padding: 16px 20px 8px; font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.5px;">
            Reports & Communication
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/reports.php" class="<?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/notifications_create.php" class="<?= $currentPage === 'notifications_create' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Report Issue</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/staff/notifications.php" class="<?= $currentPage === 'notifications' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i>
                <span>My Reports</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
