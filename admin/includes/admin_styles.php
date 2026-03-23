<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --sidebar-bg: #1e2226;
            --sidebar-hover: #2f343a;
            --topbar-bg: #ffffff;
            --content-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 260px;
            --topbar-height: 64px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--content-bg);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* ============================================
           TOP NAVBAR
        ============================================ */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--topbar-height);
            background: var(--topbar-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 999;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .hamburger-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            font-size: 20px;
            transition: background 0.2s;
        }

        .hamburger-btn:hover {
            background: var(--content-bg);
        }

        .topbar-brand {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .topbar-brand .role-badge {
            font-size: 12px;
            font-weight: 500;
            color: var(--primary-color);
            margin-left: 8px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-menu {
            position: relative;
        }

        .user-menu-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            transition: all 0.2s;
        }

        .user-menu-trigger:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 200px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
        }

        .user-dropdown.active {
            display: block;
        }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-primary);
            text-decoration: none;
            transition: background 0.2s;
        }

        .user-dropdown a:hover {
            background: var(--content-bg);
        }

        .user-dropdown a i {
            width: 16px;
            color: var(--text-secondary);
        }

        /* ============================================
           SIDEBAR
        ============================================ */
        .sidebar {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: #ffffff;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 998;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-menu {
            padding: 24px 0;
        }

        .menu-item {
            position: relative;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: #a0aec0;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item a:hover {
            background: var(--sidebar-hover);
            color: #ffffff;
        }

        .menu-item a.active {
            background: var(--sidebar-hover);
            color: #ffffff;
            border-left-color: var(--primary-color);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        /* ============================================
           MAIN CONTENT
        ============================================ */
        .main-content {
            margin-top: var(--topbar-height);
            margin-left: var(--sidebar-width);
            padding: 32px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: calc(100vh - var(--topbar-height));
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .page-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* ============================================
           STATS CARDS
        ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .stat-card-title {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-card-icon.blue { background: rgba(59, 130, 246, 0.1); color: var(--primary-color); }
        .stat-card-icon.green { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-card-icon.orange { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-card-icon.red { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* ============================================
           CONTENT CARDS
        ============================================ */
        .content-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .activity-item:hover {
            background: var(--content-bg);
        }

        .activity-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .activity-info h4 {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .activity-info p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .activity-time {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        /* ============================================
           NOTIFICATION WIDGET
        ============================================ */
        .notification-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 8px;
            background: var(--danger);
            color: white;
            font-size: 12px;
            font-weight: 700;
            border-radius: 12px;
            margin-left: 8px;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
        }

        .notification-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            text-decoration: none;
            transition: all 0.2s;
        }

        .notification-item:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .notification-item.unread {
            background: rgba(59, 130, 246, 0.08);
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notification-icon.rfid {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
        }

        .notification-icon.bus {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .notification-icon.student {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .notification-icon.system {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .notification-icon.other {
            background: rgba(107, 114, 128, 0.2);
            color: #6b7280;
        }

        .notification-info {
            flex: 1;
            min-width: 0;
        }

        .notification-info h4 {
            margin: 0 0 6px 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .notification-info p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-info .category-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .notification-info .category-badge.rfid {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
        }

        .notification-info .category-badge.bus {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .notification-info .category-badge.student {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .notification-info .category-badge.system {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .notification-info .category-badge.other {
            background: rgba(107, 114, 128, 0.2);
            color: #6b7280;
        }

        .notification-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background: var(--danger);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .notification-time {
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table,
        .data-table {
            min-width: 760px;
        }

        .stat-card-icon.purple {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        /* ============================================
           RESPONSIVE
        ============================================ */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content,
            .main-content.expanded {
                margin-left: 0;
                padding: 24px;
            }

            .topbar {
                padding: 0 16px;
            }

            .topbar-brand {
                font-size: 16px;
                white-space: nowrap;
            }

            .topbar-right > div:first-child {
                display: none !important;
            }

            .page-header {
                flex-wrap: wrap;
                gap: 12px;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .content-card {
                padding: 18px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .topbar {
                padding: 0 16px;
            }

            .topbar-brand {
                font-size: 15px;
            }

            .topbar-brand .role-badge {
                display: none;
            }

            .user-info {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-card {
                padding: 14px;
            }

            .card-header {
                flex-wrap: wrap;
                gap: 10px;
                align-items: flex-start;
            }

            .notification-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 14px;
            }

            .notification-left,
            .notification-right {
                width: 100%;
            }

            .notification-right {
                justify-content: space-between;
            }

            table,
            .data-table {
                min-width: 680px;
            }
        }

        @media (max-width: 520px) {
            .topbar {
                height: 58px;
            }

            :root {
                --topbar-height: 58px;
            }

            .hamburger-btn {
                width: 36px;
                height: 36px;
            }

            .topbar-brand {
                max-width: 160px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .user-menu-trigger {
                padding: 6px 8px;
                gap: 8px;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .main-content,
            .main-content.expanded {
                padding: 12px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card-value {
                font-size: 24px;
            }

            table,
            .data-table {
                min-width: 620px;
            }
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 997;
        }

        .sidebar-overlay.active {
            display: block;
        }
    </style>
