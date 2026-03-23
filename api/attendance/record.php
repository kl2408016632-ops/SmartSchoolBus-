<?php
/**
 * SelamatRide SmartSchoolBus
 * IoT API Endpoint - Record Attendance
 * Production-Grade with Rate Limiting & Enhanced Logging
 * 
 * This endpoint receives RFID scan data from ESP32 devices
 * and records attendance in the database.
 * 
 * @author SelamatRide Development Team
 * @version 2.0
 */

header('Content-Type: application/json');
require_once '../../config.php';

// Rate Limiting Configuration
define('RATE_LIMIT_REQUESTS', 100); // Max requests per hour
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

/**
 * Check rate limit for IP address
 */
function checkRateLimit($pdo, $ip_address) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as request_count 
        FROM audit_logs 
        WHERE ip_address = ? 
        AND action = 'api_request' 
        AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$ip_address, RATE_LIMIT_WINDOW]);
    $result = $stmt->fetch();
    
    return $result['request_count'] < RATE_LIMIT_REQUESTS;
}

/**
 * Log API request
 */
function logAPIRequest($pdo, $device_id, $action, $details) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (NULL, ?, 'api', NULL, ?, ?)
        ");
        $stmt->execute([
            $action,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("API logging error: " . $e->getMessage());
    }
}

// Get client IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Check rate limit
if (!checkRateLimit($pdo, $client_ip)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Rate limit exceeded. Please try again later.',
        'retry_after' => RATE_LIMIT_WINDOW
    ]);
    logAPIRequest($pdo, 'unknown', 'rate_limit_exceeded', ['ip' => $client_ip]);
    exit();
}

// API Key Authentication
$headers = getallheaders();
$api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';

if (empty($api_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'API key required']);
    logAPIRequest($pdo, 'unknown', 'missing_api_key', ['ip' => $client_ip]);
    exit();
}

// Verify API key
try {
    $stmt = $pdo->prepare("SELECT device_id, bus_id, device_name FROM iot_devices WHERE api_key = ? AND status = 'online'");
    $stmt->execute([hash('sha256', $api_key)]);
    $device = $stmt->fetch();
    
    if (!$device) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive API key']);
        logAPIRequest($pdo, 'unknown', 'invalid_api_key', ['ip' => $client_ip]);
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    error_log("API DB Error: " . $e->getMessage());
    exit();
}

// Log successful API request
logAPIRequest($pdo, $device['device_id'], 'api_request', [
    'device_name' => $device['device_name'],
    'bus_id' => $device['bus_id']
]);

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$rfid_uid = isset($input['rfid_uid']) ? trim($input['rfid_uid']) : '';
$action = isset($input['action']) ? trim($input['action']) : '';
$timestamp = isset($input['timestamp']) ? $input['timestamp'] : date('Y-m-d H:i:s');
$bus_id = isset($input['bus_id']) ? $input['bus_id'] : $device['bus_id'];
$device_id = $input['device_id'] ?? $device['device_id'];

// Validate required fields
if (empty($rfid_uid) || empty($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: rfid_uid and action']);
    logAPIRequest($pdo, $device_id, 'validation_error', ['reason' => 'missing_fields']);
    exit();
}

// Validate RFID UID format (alphanumeric, 4-20 characters)
if (!preg_match('/^[A-Za-z0-9]{4,20}$/', $rfid_uid)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid RFID UID format']);
    logAPIRequest($pdo, $device_id, 'validation_error', ['reason' => 'invalid_rfid_format', 'rfid_uid' => $rfid_uid]);
    exit();
}

// Validate action
if (!in_array($action, ['boarded', 'dropped_off'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action. Use "boarded" or "dropped_off"']);
    logAPIRequest($pdo, $device_id, 'validation_error', ['reason' => 'invalid_action', 'action' => $action]);
    exit();
}

try {
    // Find student by RFID UID
    $stmt = $pdo->prepare("SELECT student_id, student_name, bus_id, status FROM students WHERE rfid_uid = ?");
    $stmt->execute([$rfid_uid]);
    $student = $stmt->fetch();
    
    if (!$student) {
        http_response_code(404);
        $response = [
            'status' => 'error',
            'message' => 'Student not found',
            'rfid_uid' => $rfid_uid,
            'device_id' => $device_id,
            'suggestion' => 'Please register this RFID card in the system'
        ];
        echo json_encode($response);
        logAPIRequest($pdo, $device_id, 'student_not_found', ['rfid_uid' => $rfid_uid]);
        exit();
    }
    
    // Check if student is active
    if ($student['status'] !== 'active') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Student account is inactive',
            'student_name' => $student['student_name']
        ]);
        logAPIRequest($pdo, $device_id, 'inactive_student', ['student_id' => $student['student_id']]);
        exit();
    }
    
    // Get bus information
    $busStmt = $pdo->prepare("SELECT bus_id, bus_number FROM buses WHERE bus_id = ? AND status = 'active'");
    $busStmt->execute([$bus_id]);
    $busInfo = $busStmt->fetch();
    
    if (!$busInfo) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Bus not found or inactive']);
        logAPIRequest($pdo, $device_id, 'bus_not_found', ['bus_id' => $bus_id]);
        exit();
    }
    
    // Verify student is assigned to this bus (optional warning)
    $bus_mismatch = false;
    if ($student['bus_id'] && $student['bus_id'] != $bus_id) {
        $bus_mismatch = true;
    }
    
    // Anti-duplicate check: Prevent spam within 60 seconds
    $checkStmt = $pdo->prepare("
        SELECT record_id, timestamp 
        FROM attendance_records 
        WHERE student_id = ? 
        AND action = ? 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $checkStmt->execute([$student['student_id'], $action]);
    $recentRecord = $checkStmt->fetch();
    
    if ($recentRecord) {
        http_response_code(409);
        echo json_encode([
            'status' => 'duplicate',
            'message' => 'Duplicate scan detected. Please wait 60 seconds between scans.',
            'student_name' => $student['student_name'],
            'last_scan' => $recentRecord['timestamp'],
            'wait_seconds' => 60 - (time() - strtotime($recentRecord['timestamp']))
        ]);
        logAPIRequest($pdo, $device_id, 'duplicate_scan', [
            'student_id' => $student['student_id'],
            'action' => $action
        ]);
        exit();
    }
    
    // Insert attendance record
    $insertStmt = $pdo->prepare("
        INSERT INTO attendance_records 
        (student_id, bus_id, rfid_uid, action, timestamp, device_id, verification_status)
        VALUES (?, ?, ?, ?, ?, ?, 'verified')
    ");
    
    $insertStmt->execute([
        $student['student_id'],
        $bus_id,
        $rfid_uid,
        $action,
        $timestamp,
        $device_id
    ]);
    
    $record_id = $pdo->lastInsertId();
    
    // Update device heartbeat
    $updateDevice = $pdo->prepare("UPDATE iot_devices SET last_heartbeat = NOW() WHERE device_id = ?");
    $updateDevice->execute([$device_id]);
    
    // Log successful attendance record
    logAPIRequest($pdo, $device_id, 'attendance_recorded', [
        'record_id' => $record_id,
        'student_id' => $student['student_id'],
        'action' => $action,
        'bus_id' => $bus_id
    ]);
    
    // Success response
    http_response_code(201);
    $response = [
        'status' => 'success',
        'message' => 'Attendance recorded successfully',
        'data' => [
            'record_id' => $record_id,
            'student_id' => $student['student_id'],
            'student_name' => $student['student_name'],
            'bus_number' => $busInfo['bus_number'],
            'action' => $action,
            'timestamp' => $timestamp,
            'device_id' => $device_id
        ]
    ];
    
    if ($bus_mismatch) {
        $response['warning'] = 'Student scanned on different bus than assigned';
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("API Database Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred. Please try again.'
    ]);
    logAPIRequest($pdo, $device_id ?? 'unknown', 'database_error', [
        'error' => $e->getMessage()
    ]);
}
