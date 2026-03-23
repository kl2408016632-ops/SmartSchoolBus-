<?php
/**
 * SelamatRide SmartSchoolBus
 * RFID Card Check Endpoint
 * Receives RFID UID from ESP32 and returns GRANTED/DENIED
 */

require_once 'config.php';

header('Content-Type: text/plain');

// Get UID from query parameter
$uid = isset($_GET['uid']) ? trim(strtoupper($_GET['uid'])) : null;

if (!$uid) {
    echo "ERROR";
    exit;
}

try {
    // Check if student exists with this RFID UID
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.student_name, s.bus_id, s.status, s.payment_status
        FROM students s
        WHERE s.rfid_uid = ? AND s.status = 'active'
    ");
    $stmt->execute([$uid]);
    $student = $stmt->fetch();

    if ($student) {
        // Student found - record attendance and return GRANTED
        $busId = $student['bus_id'];
        
        // Get device ID from request (optional)
        $deviceId = isset($_GET['device']) ? $_GET['device'] : 'ESP32_UNKNOWN';
        
        // Determine action based on last record for this student today
        $todayStmt = $pdo->prepare("
            SELECT action FROM attendance_records 
            WHERE student_id = ? AND DATE(timestamp) = CURDATE()
            ORDER BY timestamp DESC
            LIMIT 1
        ");
        $todayStmt->execute([$student['student_id']]);
        $lastRecord = $todayStmt->fetch();
        
        // Toggle action: if last was 'boarded', next is 'dropped_off', else 'boarded'
        $action = ($lastRecord && $lastRecord['action'] === 'boarded') ? 'dropped_off' : 'boarded';

        // Record attendance
        $recordStmt = $pdo->prepare("
            INSERT INTO attendance_records 
            (student_id, bus_id, rfid_uid, action, timestamp, device_id, verification_status)
            VALUES (?, ?, ?, ?, NOW(), ?, 'verified')
        ");
        $recordStmt->execute([
            $student['student_id'],
            $busId,
            $uid,
            $action,
            $deviceId
        ]);

        // Log activity
        logActivity('rfid_scan', 'student', $student['student_id'], [
            'action' => $action,
            'rfid_uid' => $uid,
            'device_id' => $deviceId
        ]);

        echo "GRANTED";
        error_log("RFID Access: GRANTED - Student: {$student['student_name']} (UID: {$uid}) - Action: {$action}");
    } else {
        // Student not found - return DENIED
        echo "DENIED";
        error_log("RFID Access: DENIED - UID: {$uid} - Student not found or inactive");
    }
} catch (Exception $e) {
    error_log("RFID Check Error: " . $e->getMessage());
    echo "ERROR";
}
?>
