<?php
/**
 * SelamatRide SmartSchoolBus - User Management API
 * Production-Grade CRUD Operations
 * 
 * Endpoints:
 * - GET    /api/users/manage.php?action=list
 * - GET    /api/users/manage.php?action=get&id=1
 * - POST   /api/users/manage.php?action=create
 * - POST   /api/users/manage.php?action=update
 * - POST   /api/users/manage.php?action=delete
 * 
 * @author SelamatRide Development Team
 * @version 2.0
 */

require_once '../../config.php';
requireRole(['admin']);

header('Content-Type: application/json');

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'get':
            handleGet();
            break;
        case 'create':
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            handleDelete();
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred',
        'debug' => $e->getMessage() // Remove in production
    ]);
}

/**
 * List all users with pagination and filtering
 */
function handleList() {
    global $pdo;
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = min(100, max(10, (int)($_GET['per_page'] ?? RECORDS_PER_PAGE)));
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $per_page;
    
    // Build query
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($role_filter) && is_numeric($role_filter)) {
        $where_conditions[] = "u.role_id = ?";
        $params[] = $role_filter;
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "u.status = ?";
        $params[] = $status_filter;
    }
    
    $where_sql = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM users u $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get users
    $sql = "
        SELECT 
            u.user_id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.avatar_url,
            u.status,
            u.last_login,
            u.created_at,
            r.role_id,
            r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        $where_sql
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => $users,
        'pagination' => [
            'total' => $total_records,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_records / $per_page)
        ]
    ]);
}

/**
 * Get single user by ID
 */
function handleGet() {
    global $pdo;
    
    $user_id = (int)($_GET['id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.avatar_url,
            u.status,
            u.last_login,
            u.created_at,
            r.role_id,
            r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        return;
    }
    
    echo json_encode(['status' => 'success', 'data' => $user]);
}

/**
 * Create new user
 */
function handleCreate() {
    global $pdo, $auth;
    
    // Validate CSRF
    $auth->requireCSRF();
    
    // Get input
    $input = [
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'role_id' => (int)($_POST['role_id'] ?? 0),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'status' => $_POST['status'] ?? 'active'
    ];
    
    // Validate input
    $errors = $auth->validate($input, [
        'username' => 'required|min:3|max:50',
        'password' => 'required|min:8',
        'full_name' => 'required|min:3|max:100',
        'role_id' => 'required|numeric',
        'email' => 'email'
    ]);
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors]);
        return;
    }
    
    // Check username uniqueness
    $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $check_stmt->execute([$input['username']]);
    if ($check_stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        return;
    }
    
    // Hash password
    $password_hash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, full_name, role_id, email, phone, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $input['username'],
        $password_hash,
        $input['full_name'],
        $input['role_id'],
        $input['email'],
        $input['phone'],
        $input['status']
    ]);
    
    $new_user_id = $pdo->lastInsertId();
    
    // Log activity
    logActivity('created_user', 'user', $new_user_id, [
        'username' => $input['username'],
        'role_id' => $input['role_id']
    ]);
    
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'User created successfully',
        'data' => ['user_id' => $new_user_id]
    ]);
}

/**
 * Update existing user
 */
function handleUpdate() {
    global $pdo, $auth;
    
    // Validate CSRF
    $auth->requireCSRF();
    
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        return;
    }
    
    // Get input
    $input = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'role_id' => (int)($_POST['role_id'] ?? 0),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'status' => $_POST['status'] ?? 'active'
    ];
    
    // Validate input
    $errors = $auth->validate($input, [
        'full_name' => 'required|min:3|max:100',
        'role_id' => 'required|numeric',
        'email' => 'email'
    ]);
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors]);
        return;
    }
    
    // Update user
    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, role_id = ?, email = ?, phone = ?, status = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    
    $stmt->execute([
        $input['full_name'],
        $input['role_id'],
        $input['email'],
        $input['phone'],
        $input['status'],
        $user_id
    ]);
    
    // Update password if provided
    if (!empty($_POST['new_password'])) {
        $password_hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $pwd_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $pwd_stmt->execute([$password_hash, $user_id]);
    }
    
    // Log activity
    logActivity('updated_user', 'user', $user_id, $input);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User updated successfully'
    ]);
}

/**
 * Delete user (soft delete by setting status to inactive)
 */
function handleDelete() {
    global $pdo, $auth;
    
    // Validate CSRF
    $auth->requireCSRF();
    
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        return;
    }
    
    // Prevent self-deletion
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Soft delete
    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Log activity
    logActivity('deleted_user', 'user', $user_id);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User deleted successfully'
    ]);
}
