<?php
/**
 * SelamatRide SmartSchoolBus - Bus Management API
 * Production-Grade CRUD Operations
 * 
 * @author SelamatRide Development Team
 * @version 2.0
 */

require_once '../../config.php';
requireRole(['admin', 'staff']);

header('Content-Type: application/json');

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
            requireRole(['admin']); // Only admin can create
            handleCreate();
            break;
        case 'update':
            requireRole(['admin']); // Only admin can update
            handleUpdate();
            break;
        case 'delete':
            requireRole(['admin']); // Only admin can delete
            handleDelete();
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred', 'debug' => $e->getMessage()]);
}

function handleList() {
    global $pdo;
    
    $status_filter = $_GET['status'] ?? '';
    
    $where = $status_filter ? "WHERE b.status = ?" : "";
    $params = $status_filter ? [$status_filter] : [];
    
    $sql = "
        SELECT 
            b.*,
            u.full_name as driver_name,
            u.phone as driver_phone,
            COUNT(DISTINCT s.student_id) as student_count
        FROM buses b
        LEFT JOIN users u ON b.assigned_driver_id = u.user_id
        LEFT JOIN students s ON b.bus_id = s.bus_id AND s.status = 'active'
        $where
        GROUP BY b.bus_id
        ORDER BY b.bus_number
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $buses = $stmt->fetchAll();
    
    echo json_encode(['status' => 'success', 'data' => $buses]);
}

function handleGet() {
    global $pdo;
    
    $bus_id = (int)($_GET['id'] ?? 0);
    
    if ($bus_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid bus ID']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            u.full_name as driver_name,
            u.phone as driver_phone,
            COUNT(DISTINCT s.student_id) as student_count
        FROM buses b
        LEFT JOIN users u ON b.assigned_driver_id = u.user_id
        LEFT JOIN students s ON b.bus_id = s.bus_id AND s.status = 'active'
        WHERE b.bus_id = ?
        GROUP BY b.bus_id
    ");
    $stmt->execute([$bus_id]);
    $bus = $stmt->fetch();
    
    if (!$bus) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Bus not found']);
        return;
    }
    
    echo json_encode(['status' => 'success', 'data' => $bus]);
}

function handleCreate() {
    global $pdo, $auth;
    
    $auth->requireCSRF();
    
    $input = [
        'bus_number' => trim($_POST['bus_number'] ?? ''),
        'license_plate' => trim($_POST['license_plate'] ?? ''),
        'capacity' => (int)($_POST['capacity'] ?? 0),
        'assigned_driver_id' => !empty($_POST['assigned_driver_id']) ? (int)$_POST['assigned_driver_id'] : null,
        'route_description' => trim($_POST['route_description'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
        'device_id' => trim($_POST['device_id'] ?? '')
    ];
    
    $errors = $auth->validate($input, [
        'bus_number' => 'required|min:1|max:20',
        'capacity' => 'required|numeric'
    ]);
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors]);
        return;
    }
    
    // Check uniqueness
    $check = $pdo->prepare("SELECT bus_id FROM buses WHERE bus_number = ?");
    $check->execute([$input['bus_number']]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Bus number already exists']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO buses (bus_number, license_plate, capacity, assigned_driver_id, route_description, status, device_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $input['bus_number'],
        $input['license_plate'],
        $input['capacity'],
        $input['assigned_driver_id'],
        $input['route_description'],
        $input['status'],
        $input['device_id']
    ]);
    
    $bus_id = $pdo->lastInsertId();
    logActivity('created_bus', 'bus', $bus_id, $input);
    
    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Bus created successfully', 'data' => ['bus_id' => $bus_id]]);
}

function handleUpdate() {
    global $pdo, $auth;
    
    $auth->requireCSRF();
    
    $bus_id = (int)($_POST['bus_id'] ?? 0);
    
    if ($bus_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid bus ID']);
        return;
    }
    
    $input = [
        'license_plate' => trim($_POST['license_plate'] ?? ''),
        'capacity' => (int)($_POST['capacity'] ?? 0),
        'assigned_driver_id' => !empty($_POST['assigned_driver_id']) ? (int)$_POST['assigned_driver_id'] : null,
        'route_description' => trim($_POST['route_description'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
        'device_id' => trim($_POST['device_id'] ?? '')
    ];
    
    $stmt = $pdo->prepare("
        UPDATE buses 
        SET license_plate = ?, capacity = ?, assigned_driver_id = ?, route_description = ?, status = ?, device_id = ?, updated_at = NOW()
        WHERE bus_id = ?
    ");
    
    $stmt->execute([
        $input['license_plate'],
        $input['capacity'],
        $input['assigned_driver_id'],
        $input['route_description'],
        $input['status'],
        $input['device_id'],
        $bus_id
    ]);
    
    logActivity('updated_bus', 'bus', $bus_id, $input);
    
    echo json_encode(['status' => 'success', 'message' => 'Bus updated successfully']);
}

function handleDelete() {
    global $pdo, $auth;
    
    $auth->requireCSRF();
    
    $bus_id = (int)($_POST['bus_id'] ?? 0);
    
    if ($bus_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid bus ID']);
        return;
    }
    
    // Check if bus has assigned students
    $check = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE bus_id = ? AND status = 'active'");
    $check->execute([$bus_id]);
    if ($check->fetch()['count'] > 0) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete bus with assigned students']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE buses SET status = 'inactive', updated_at = NOW() WHERE bus_id = ?");
    $stmt->execute([$bus_id]);
    
    logActivity('deleted_bus', 'bus', $bus_id);
    
    echo json_encode(['status' => 'success', 'message' => 'Bus deleted successfully']);
}
