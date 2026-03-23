<!-- Top Navbar -->
<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-brand">
            SmartSchoolBus <span class="role-badge">Staff Panel</span>
        </div>
    </div>
    <div class="topbar-right">
        <div style="display: flex; align-items: center; gap: 8px; margin-right: 20px;">
            <i class="fas fa-clock" style="color: var(--primary-color); font-size: 16px;"></i>
            <div style="display: flex; flex-direction: column; line-height: 1.3;">
                <span style="font-size: 11px; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Login Time</span>
                <span style="font-size: 13px; color: var(--text-primary); font-weight: 600;">
                    <?php
                    // Get user's date/time format settings
                    $currentUser = getCurrentUser();
                    $userSettings = [];
                    if (isset($currentUser['user_id'])) {
                        try {
                            $stmt = $pdo->prepare("SELECT settings FROM users WHERE user_id = ?");
                            $stmt->execute([$currentUser['user_id']]);
                            $settingsData = $stmt->fetchColumn();
                            if ($settingsData) {
                                $userSettings = json_decode($settingsData, true);
                            }
                        } catch (Exception $e) {
                            error_log("Settings fetch error: " . $e->getMessage());
                        }
                    }
                    
                    // Use user's preferred formats or defaults
                    $dateFormat = $userSettings['date_format'] ?? 'd M Y';
                    $timeFormat = $userSettings['time_format'] ?? 'h:i A';
                    $timezone = $userSettings['timezone'] ?? 'Asia/Kuala_Lumpur';
                    
                    // Set timezone and display login time
                    date_default_timezone_set($timezone);
                    echo date($dateFormat . ', ' . $timeFormat);
                    ?>
                </span>
            </div>
        </div>
        <div class="user-menu">
            <div class="user-menu-trigger" id="userMenuTrigger">
                <?php
                // Fetch current user's avatar from database
                $currentUser = getCurrentUser();
                $userAvatar = null;
                
                if (isset($currentUser['user_id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE user_id = ?");
                        $stmt->execute([$currentUser['user_id']]);
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
                         alt="<?= htmlspecialchars($currentUser['full_name']) ?>">
                <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                    <span class="user-role">Staff Member</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="<?= SITE_URL ?>/staff/profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="<?= SITE_URL ?>/staff/settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>
