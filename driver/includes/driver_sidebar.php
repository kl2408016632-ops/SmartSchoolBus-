<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-menu">
        <!-- Dashboard -->
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/driver/dashboard.php" class="<?= $currentPage == 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- Operations Section -->
        <div class="menu-section-title" style="padding: 16px 20px 8px; font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.5px;">
            Operations
        </div>
        
        <div class="menu-item">
            <a href="<?= SITE_URL ?>/driver/students.php" class="<?= $currentPage == 'students' ? 'active' : '' ?>">
                <i class="fas fa-map-marked-alt"></i>
                <span>Student Addresses</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
