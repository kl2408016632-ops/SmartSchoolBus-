<?php
/**
 * SelamatRide SmartSchoolBus
 * Setup & Testing Script
 * Helps configure RFID system
 */

require_once 'config.php';

echo "=== SelamatRide SmartSchoolBus - Setup Guide ===\n\n";

// 1. Check Database
echo "1. DATABASE CHECK:\n";
try {
    $result = $pdo->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch();
    echo "   ✓ Database connected. Users: " . $row['count'] . "\n";
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n2. SAMPLE LOGIN CREDENTIALS:\n";
echo "   Admin:  username=admin, password=admin (hash: check database)\n";
echo "   Staff:  username=staff1, password=staff1\n";
echo "   Driver: username=driver1, password=driver1\n";
echo "   NOTE: To verify passwords, check the password_hash in users table\n";

echo "\n3. RFID SETUP:\n";
echo "   - RFID Check Endpoint: http://localhost/SmartSchoolBus/check.php?uid=UID\n";
echo "   - Example: http://localhost/SmartSchoolBus/check.php?uid=4A3B2C1D\n";

echo "\n4. DATABASE TABLES:\n";
try {
    $tables = [
        'users', 'students', 'attendance_records', 'buses', 
        'payments', 'notifications', 'incidents'
    ];
    foreach ($tables as $table) {
        $result = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $row = $result->fetch();
        echo "   - $table: " . $row['count'] . " records\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n5. SAMPLE STUDENTS (for RFID testing):\n";
try {
    $result = $pdo->query("SELECT student_id, student_name, rfid_uid FROM students LIMIT 5");
    $students = $result->fetchAll();
    foreach ($students as $student) {
        echo "   - " . $student['student_name'] . " (UID: " . $student['rfid_uid'] . ")\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n6. BUSES:\n";
try {
    $result = $pdo->query("SELECT bus_id, bus_number, device_id FROM buses");
    $buses = $result->fetchAll();
    foreach ($buses as $bus) {
        echo "   - " . $bus['bus_number'] . " (Device: " . $bus['device_id'] . ")\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete ===\n";
echo "Visit: http://localhost/SmartSchoolBus/login.php\n";
?>
