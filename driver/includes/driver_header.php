<!-- Top Navbar -->
<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-brand">
            SmartSchoolBus <span class="role-badge">Driver Panel</span>
        </div>
    </div>
    <div class="topbar-right">
        <div style="display: flex; align-items: center; gap: 8px; margin-right: 20px;">
            <i class="fas fa-clock" style="color: var(--primary-color); font-size: 16px;"></i>
            <div style="display: flex; flex-direction: column; line-height: 1.3;">
                <span style="font-size: 11px; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Login Time</span>
                <span style="font-size: 13px; color: var(--text-primary); font-weight: 600;">
                    <?= date('d M Y, h:i A') ?>
                </span>
            </div>
        </div>
        <div class="user-menu">
            <div class="user-menu-trigger" id="userMenuTrigger">
                <?php
                // Fetch current user's avatar from database
                $userAvatar = null;
                
                if (isset($_SESSION['user_id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $userAvatarData = $stmt->fetch();
                        $userAvatar = $userAvatarData['avatar_url'] ?? null;
                    } catch (Exception $e) {
                        error_log("Avatar fetch error: " . $e->getMessage());
                    }
                }
                ?>
                
                <?php if (!empty($userAvatar) && file_exists(__DIR__ . '/../../' . $userAvatar)): ?>
                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars($userAvatar) ?>?v=<?= time() ?>" 
                         class="user-avatar" 
                         style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;"
                         alt="<?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?>">
                <?php else: ?>
                    <div class="user-avatar">
                        <?= isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 1)) : 'D' ?>
                    </div>
                <?php endif; ?>
                
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?></span>
                    <span class="user-role">Driver</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="<?= SITE_URL ?>/driver/profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="<?= SITE_URL ?>/driver/settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>
