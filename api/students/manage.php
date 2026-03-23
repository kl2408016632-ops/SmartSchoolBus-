<?php
/**
 * SelamatRide SmartSchoolBus - Student Management API
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
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            requireRole(['admin']); // Only admin can delete
            handleDelete();
            break;
        case 'assign_bus':
            handleAssignBus();
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
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = min(100, max(10, (int)($_GET['per_page'] ?? RECORDS_PER_PAGE)));
    $search = $_GET['search'] ?? '';
    $bus_filter = $_GET['bus_id'] ?? '';
    $payment_filter = $_GET['payment_status'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $per_page;
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(s.student_name LIKE ? OR s.rfid_uid LIKE ? OR p.parent_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($bus_filter) && is_numeric($bus_filter)) {
        $where_conditions[] = "s.bus_id = ?";
        $params[] = $bus_filter;
    }
    
    if (!empty($payment_filter)) {
        $where_conditions[] = "s.payment_status = ?";
        $params[] = $payment_filter;
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
    }
    
    $where_sql = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM students s
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        $where_sql
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get students
    $sql = "
        SELECT 
            s.*,
            p.parent_name,
            p.phone_primary as parent_phone,
            p.email as parent_email,
            b.bus_number,
            b.bus_id
        FROM students s
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        LEFT JOIN buses b ON s.bus_id = b.bus_id
        $where_sql
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => $students,
        'pagination' => [
            'total' => $total_records,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_records / $per_page)
        ]
    ]);
}

function handleGet() {
    global $pdo;
    
    $student_id = (int)($_GET['id'] ?? 0);
    
    if ($student_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            p.parent_name,
            p.phone_primary as parent_phone,
            p.phone_secondary as parent_phone_secondary,
            p.email as parent_email,
            p.address as parent_address,
            b.bus_number,
            b.bus_id
        FROM students s
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        LEFT JOIN buses b ON s.bus_id = b.bus_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        return;
    }
    
    // Get attendance summary
    $attendance_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT DATE(timestamp)) as days_attended,
            MAX(timestamp) as last_scan
        FROM attendance_records
        WHERE student_id = ?
    ");
    $attendance_stmt->execute([$student_id]);
    $student['attendance_summary'] = $attendance_stmt->fetch();
    
    echo json_encode(['status' => 'success', 'data' => $student]);
}

function handleCreate() {
    global $pdo, $auth;
    
    $auth->requireCSRF();
    
    $input = [
        'student_name' => trim($_POST['student_name'] ?? ''),
        'rfid_uid' => trim($_POST['rfid_uid'] ?? ''),
        'parent_name' => trim($_POST['parent_name'] ?? ''),
        'parent_phone' => trim($_POST['parent_phone'] ?? ''),
        'parent_email' => trim($_POST['parent_email'] ?? ''),
        'bus_id' => !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null,
        'payment_status' => $_POST['payment_status'] ?? 'unpaid',
        'date_of_birth' => $_POST['date_of_birth'] ?? null,
        'address' => trim($_POST['address'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? '')
    ];
    
    $errors = $auth->validate($input, [
        'student_name' => 'required|min:3|max:100',
        'rfid_uid' => 'required|min:4|max:20',
        'parent_name' => 'required|min:3|max:100',
        'parent_phone' => 'required'
    ]);
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors]);
        return;
    }
    
    // Check RFID uniqueness
    $check = $pdo->prepare("SELECT student_id FROM students WHERE rfid_uid = ?");
    $check->execute([$input['rfid_uid']]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'RFID UID already exists']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Create or find parent
        $parent_stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE phone_primary = ?");
        $parent_stmt->execute([$input['parent_phone']]);
        $parent = $parent_stmt->fetch();
        
        if ($parent) {
            $parent_id = $parent['parent_id'];
        } else {
            $parent_insert = $pdo->prepare("
                INSERT INTO parents (parent_name, phone_primary, email, address)
                VALUES (?, ?, ?, ?)
            ");
            $parent_insert->execute([
                $input['parent_name'],
                $input['parent_phone'],
                $input['parent_email'],
                $input['address']
            ]);
            $parent_id = $pdo->lastInsertId();
        }
        
        // Create student
        $stmt = $pdo->prepare("
            INSERT INTO students (student_name, rfid_uid, parent_id, bus_id, payment_status, date_of_birth, address, emergency_contact)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['student_name'],
            $input['rfid_uid'],
            $parent_id,
            $input['bus_id'],
            $input['payment_status'],
            $input['date_of_birth'],
            $input['address'],
            $input['emergency_contact']
        ]);
        
        $student_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        logActivity('created_student', 'student', $student_id, $input);
        
        http_response_code(201);
        echo json_encode(['status' => 'success', 'message' => 'Student created successfully', 'data' => ['student_id' => $student_id]]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleUpdate() {
    global $pdo, $auth;
    
    $auth->requireCSRF();
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    
    if ($student_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
        return;
    }
    
    $input = [
        'student_name' => trim($_POST['student_name'] ?? ''),
        'bus_id' => !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null,
        'payment_status' => $_POST['payment_status'] ?? 'unpaid',
        'status' => $_POST['status'] ?? 'active',
        'date_of_birth' => $_POST['date_of_birth'] ?? null,
        'address' => trim($_POST['address'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? '')
    ];
    
    $stmt = $pdo->prepare("
        UPDATE students 
        SET student_name = ?, bus_id = ?, payment_status = ?, status = ?, date_of_birth = ?, address = ?, emergency_contact = ?, updated_at = NOW()
        WHERE student_id = ?
    ");
    
    $stmt->execute([
        $input['student_name'],
        $input['bus_id'],
        $input['payment_status'],
        $input['status'],
        $input['date_of_birth'],
        $input['address'],
        $input['emergency_contact'],
        $student_id
    ]);
    
    logActivity('updated_student', 'student', $student_id, $input);
    
    echo json_encode(['status' => 'success', 'message' => 'Student updated successfully']);
}

function handleDelete() {
    global $pdo, $auth;
    
    $auth->requireCSRF();
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    
    if ($student_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE students SET status = 'inactive', updated_at = NOW() WHERE student_id = ?");
    $stmt->execute([$student_id]);
    
    logActivity('deleted_student', 'student', $student_id);
    
    echo json_encode(['status' => 'success', 'message' => 'Student deleted successfully']);
}

function handleAssignBus() {
    global $pdo, $auth;
    
    $auth->requireCSRF();
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    $bus_id = !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null;
    
    if ($student_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE students SET bus_id = ?, updated_at = NOW() WHERE student_id = ?");
    $stmt->execute([$bus_id, $student_id]);
    
    logActivity('assigned_student_to_bus', 'student', $student_id, ['bus_id' => $bus_id]);
    
    echo json_encode(['status' => 'success', 'message' => 'Student assigned to bus successfully']);
}
