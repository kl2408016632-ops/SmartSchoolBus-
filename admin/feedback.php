<?php
/**
 * SelamatRide SmartSchoolBus - Admin Feedback Management
 * Production-Grade Feedback Review & Analysis Dashboard
 */
require_once '../config.php';
requireRole(['admin']);

$pageTitle = "User Feedback";
$currentPage = "feedback";

// Filters
$filterRole = $_GET['role'] ?? 'all';
$filterRating = $_GET['rating'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'date_desc';

// Build query - Anonymous feedback (no user identification)
$query = "
    SELECT 
        lf.*
    FROM logout_feedback lf
    WHERE 1=1
";

$params = [];

// Apply role filter
if ($filterRole !== 'all') {
    $query .= " AND lf.user_role = ?";
    $params[] = $filterRole;
}

// Apply rating filter
if ($filterRating === 'low') {
    $query .= " AND lf.rating BETWEEN 0 AND 4";
} elseif ($filterRating === 'medium') {
    $query .= " AND lf.rating BETWEEN 5 AND 7";
} elseif ($filterRating === 'high') {
    $query .= " AND lf.rating BETWEEN 8 AND 10";
}

// Apply sorting
switch ($sortBy) {
    case 'date_asc':
        $query .= " ORDER BY lf.created_at ASC";
        break;
    case 'rating_asc':
        $query .= " ORDER BY lf.rating ASC, lf.created_at DESC";
        break;
    case 'rating_desc':
        $query .= " ORDER BY lf.rating DESC, lf.created_at DESC";
        break;
    case 'date_desc':
    default:
        $query .= " ORDER BY lf.created_at DESC";
        break;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $feedbacks = $stmt->fetchAll();
    
    // Calculate statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_feedbacks,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating >= 9 THEN 1 ELSE 0 END) as promoters,
            SUM(CASE WHEN rating <= 6 THEN 1 ELSE 0 END) as detractors,
            SUM(CASE WHEN rating BETWEEN 0 AND 4 THEN 1 ELSE 0 END) as low_ratings,
            SUM(CASE WHEN rating BETWEEN 5 AND 7 THEN 1 ELSE 0 END) as medium_ratings,
            SUM(CASE WHEN rating BETWEEN 8 AND 10 THEN 1 ELSE 0 END) as high_ratings
        FROM logout_feedback
    ";
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch();
    
    // Calculate NPS (Net Promoter Score)
    $nps = 0;
    if ($stats['total_feedbacks'] > 0) {
        $promoterPercent = ($stats['promoters'] / $stats['total_feedbacks']) * 100;
        $detractorPercent = ($stats['detractors'] / $stats['total_feedbacks']) * 100;
        $nps = round($promoterPercent - $detractorPercent);
    }
    
} catch (Exception $e) {
    error_log("Feedback Query Error: " . $e->getMessage());
    $feedbacks = [];
    $stats = [];
    $nps = 0;
}

// Get role breakdown
try {
    $roleStmt = $pdo->query("
        SELECT 
            user_role,
            COUNT(*) as count,
            AVG(rating) as avg_rating
        FROM logout_feedback
        GROUP BY user_role
    ");
    $roleStats = $roleStmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roleStats = [];
}

function formatSessionDuration($seconds) {
    if ($seconds === null) return 'N/A';
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return sprintf("%dh %dm", $hours, $minutes);
    }
    return sprintf("%dm", $minutes);
}

function getRatingColor($rating) {
    if ($rating >= 9) return '#10b981'; // Green
    if ($rating >= 7) return '#f59e0b'; // Orange
    return '#ef4444'; // Red
}

function getRatingLabel($rating) {
    if ($rating >= 9) return 'Promoter';
    if ($rating >= 7) return 'Passive';
    return 'Detractor';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php include 'includes/admin_styles.php'; ?>
    
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-value.nps {
            color: #3b82f6;
        }

        .stat-value.positive {
            color: var(--success);
        }

        .stat-value.negative {
            color: var(--danger);
        }

        .filter-bar {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .filter-group select {
            padding: 8px 12px;
            background: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 14px;
        }

        .feedback-table {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .feedback-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .feedback-item {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }

        .feedback-item:hover {
            background: var(--content-bg);
        }

        .feedback-item:last-child {
            border-bottom: none;
        }

        .feedback-item.low-rating {
            background: rgba(239, 68, 68, 0.05);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-details h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .user-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .rating-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 700;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .feedback-message {
            margin-top: 12px;
            padding: 12px;
            background: var(--content-bg);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-badge.admin {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .role-badge.staff {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .role-badge.driver {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                color: black;
            }

            .sidebar, 
            .topbar,
            .filter-bar,
            .btn,
            button {
                display: none !important;
            }

            .main-content {
                margin: 0;
                padding: 0;
            }

            .feedback-table {
                border: 1px solid #ddd;
            }

            .feedback-item {
                page-break-inside: avoid;
                border-bottom: 1px solid #ddd;
            }

            .feedback-list {
                max-height: none;
                overflow: visible;
            }

            .stat-card {
                border: 1px solid #ddd;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .feedback-header {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-comments"></i> User Feedback</h1>
                <p>Monitor and analyze system feedback from users</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Feedback</div>
                <div class="stat-value"><?= number_format($stats['total_feedbacks'] ?? 0) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Average Rating</div>
                <div class="stat-value positive"><?= number_format($stats['avg_rating'] ?? 0, 1) ?>/10</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Net Promoter Score</div>
                <div class="stat-value nps"><?= $nps ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Low Ratings (0-4)</div>
                <div class="stat-value negative"><?= $stats['low_ratings'] ?? 0 ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Role:</label>
                <select id="roleFilter" onchange="applyFilters()">
                    <option value="all" <?= $filterRole === 'all' ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="driver" <?= $filterRole === 'driver' ? 'selected' : '' ?>>Driver</option>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-star"></i> Rating:</label>
                <select id="ratingFilter" onchange="applyFilters()">
                    <option value="all" <?= $filterRating === 'all' ? 'selected' : '' ?>>All Ratings</option>
                    <option value="high" <?= $filterRating === 'high' ? 'selected' : '' ?>>High (8-10)</option>
                    <option value="medium" <?= $filterRating === 'medium' ? 'selected' : '' ?>>Medium (5-7)</option>
                    <option value="low" <?= $filterRating === 'low' ? 'selected' : '' ?>>Low (0-4)</option>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-sort"></i> Sort By:</label>
                <select id="sortFilter" onchange="applyFilters()">
                    <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="date_asc" <?= $sortBy === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="rating_desc" <?= $sortBy === 'rating_desc' ? 'selected' : '' ?>>Highest Rating</option>
                    <option value="rating_asc" <?= $sortBy === 'rating_asc' ? 'selected' : '' ?>>Lowest Rating</option>
                </select>
            </div>
        </div>

        <!-- Feedback Table -->
        <div class="feedback-table">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i> Feedback Entries (<?= count($feedbacks) ?>)
                </div>
            </div>

            <div class="feedback-list">
                <?php if (empty($feedbacks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No feedback entries found matching your filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($feedbacks as $feedback): ?>
                        <div class="feedback-item <?= $feedback['rating'] <= 4 ? 'low-rating' : '' ?>">
                            <div class="feedback-header">
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($feedback['user_role'], 0, 1)) ?>
                                    </div>
                                    <div class="user-details">
                                        <h4>Anonymous <?= ucfirst($feedback['user_role']) ?></h4>
                                        <div class="user-meta">
                                            <span class="role-badge <?= $feedback['user_role'] ?>">
                                                <?= ucfirst($feedback['user_role']) ?>
                                            </span>
                                            <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($feedback['created_at'])) ?></span>
                                            <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($feedback['created_at'])) ?></span>
                                            <span><i class="fas fa-stopwatch"></i> Session: <?= formatSessionDuration($feedback['session_duration']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="rating-badge" style="background: <?= getRatingColor($feedback['rating']) ?>">
                                    <?= $feedback['rating'] ?>/10
                                    <span style="font-size: 11px; opacity: 0.9;"><?= getRatingLabel($feedback['rating']) ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($feedback['message'])): ?>
                                <div class="feedback-message">
                                    <i class="fas fa-quote-left" style="opacity: 0.5; margin-right: 8px;"></i>
                                    <?= nl2br(htmlspecialchars($feedback['message'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        function applyFilters() {
            const role = document.getElementById('roleFilter').value;
            const rating = document.getElementById('ratingFilter').value;
            const sort = document.getElementById('sortFilter').value;
            
            window.location.href = `?role=${role}&rating=${rating}&sort=${sort}`;
        }
    </script>
</body>
</html>
