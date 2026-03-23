-- ============================================
-- SelamatRide SmartSchoolBus Database
-- Complete Database Schema with Sample Data
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS smartschoolbus_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartschoolbus_db;

-- ============================================
-- Table 1: roles
-- ============================================
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    role_description TEXT,
    permissions JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default roles
INSERT INTO roles (role_name, role_description, permissions) VALUES
('admin', 'Full system access and management', '{"user_management": true, "delete_records": true, "view_all": true, "export_data": true}'),
('staff', 'View and manage students and attendance', '{"view_students": true, "assign_students": true, "view_reports": true, "delete_records": false}'),
('driver', 'View own bus and students only', '{"view_own_bus": true, "view_students": false, "delete_records": false}');

-- ============================================
-- Table 2: users
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    avatar_url VARCHAR(255),
    settings LONGTEXT,
    mfa_enabled TINYINT(1) DEFAULT 0,
    mfa_secret VARCHAR(64),
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT,
    INDEX idx_username (username),
    INDEX idx_role (role_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Insert sample users with individual passwords
INSERT INTO users (username, password_hash, full_name, role_id, email, phone, status) VALUES
('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Admin SelamatRide', 1, 'admin@selamatride.com', '+60129485240', 'active'),
('staff1', '$2y$10$3i9/lRt.E36JhJzd3fXxXuY1zjEcGVCh6qBkKJfF.8BqJVj3h2x0u', 'Sarah Lee', 2, 'sarah@selamatride.com', '+60123456789', 'active'),
('staff2', '$2y$10$3i9/lRt.E36JhJzd3fXxXuY1zjEcGVCh6qBkKJfF.8BqJVj3h2x0u', 'Ahmad Firdaus', 2, 'ahmad@selamatride.com', '+60123456790', 'active'),
('driver1', '$2y$10$8xhLqQr5yB2d8FZGvLJZvOXK0n4J5zQH3xQF9G8HnJ3K5L6M7N8O9', 'John Tan', 3, 'john@selamatride.com', '+60123456791', 'active'),
('driver2', '$2y$10$8xhLqQr5yB2d8FZGvLJZvOXK0n4J5zQH3xQF9G8HnJ3K5L6M7N8O9', 'Kumar Raju', 3, 'kumar@selamatride.com', '+60123456792', 'active');

-- ============================================
-- Table 3: parents
-- ============================================
CREATE TABLE parents (
    parent_id INT AUTO_INCREMENT PRIMARY KEY,
    parent_name VARCHAR(100) NOT NULL,
    phone_primary VARCHAR(20) NOT NULL,
    phone_secondary VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    relationship VARCHAR(50),
    notification_preference ENUM('sms', 'email', 'both', 'none') DEFAULT 'sms',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_primary)
) ENGINE=InnoDB;

-- Insert sample parents
INSERT INTO parents (parent_name, phone_primary, phone_secondary, email, address, relationship, notification_preference) VALUES
('Ali bin Ahmad', '+60123456801', NULL, 'ali.ahmad@email.com', 'Lot 123, Taman Sepang, 43900 Sepang', 'father', 'sms'),
('Siti binti Hassan', '+60123456802', '+60123456803', 'siti.hassan@email.com', 'No 45, Jalan Persiaran, 43900 Sepang', 'mother', 'both'),
('Wong Mei Ling', '+60123456804', NULL, 'wong.mei@email.com', 'Blok C-3-5, Taman Cyberjaya, 43900 Sepang', 'mother', 'sms'),
('Rajesh Kumar', '+60123456805', '+60123456806', 'rajesh.k@email.com', '78 Jalan Bukit, 43900 Sepang', 'father', 'email'),
('Fatimah binti Omar', '+60123456807', NULL, 'fatimah.omar@email.com', 'No 12, Taman Indah, 43900 Sepang', 'mother', 'sms');

-- ============================================
-- Table 4: buses
-- ============================================
CREATE TABLE buses (
    bus_id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number VARCHAR(20) UNIQUE NOT NULL,
    license_plate VARCHAR(20),
    capacity INT NOT NULL,
    assigned_driver_id INT,
    route_description TEXT,
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    device_id VARCHAR(50) UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_driver_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_device (device_id)
) ENGINE=InnoDB;

-- Insert sample buses
INSERT INTO buses (bus_number, license_plate, capacity, assigned_driver_id, route_description, status, device_id) VALUES
('Bus #01', 'WA1234B', 30, 4, 'Sepang - Putrajaya Route', 'active', 'ESP32_BUS01'),
('Bus #02', 'WA5678C', 30, 5, 'Sepang - Cyberjaya Route', 'active', 'ESP32_BUS02'),
('Bus #03', 'WB1234D', 25, NULL, 'Sepang - KLIA Route', 'active', 'ESP32_BUS03');

-- ============================================
-- Table 5: students
-- ============================================
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(100) NOT NULL,
    rfid_uid VARCHAR(20) UNIQUE NOT NULL,
    parent_id INT,
    bus_id INT,
    payment_status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    status ENUM('active', 'inactive') DEFAULT 'active',
    photo_url VARCHAR(255),
    address TEXT,
    emergency_contact VARCHAR(20),
    date_of_birth DATE,
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(parent_id) ON DELETE SET NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(bus_id) ON DELETE SET NULL,
    INDEX idx_rfid (rfid_uid),
    INDEX idx_bus (bus_id),
    INDEX idx_status (status),
    INDEX idx_payment (payment_status)
) ENGINE=InnoDB;

-- Insert sample students
INSERT INTO students (student_name, rfid_uid, parent_id, bus_id, payment_status, status, emergency_contact, date_of_birth, enrollment_date) VALUES
('Ahmad bin Ali', '4A3B2C1D', 1, 1, 'paid', 'active', '+60123456801', '2012-05-15', '2024-01-10'),
('Nur Aisyah binti Hassan', 'E8F94A23', 2, 1, 'paid', 'active', '+60123456802', '2013-08-22', '2024-01-10'),
('Wong Li Ming', '3F8A9B4C', 3, 1, 'unpaid', 'active', '+60123456804', '2014-03-10', '2024-01-10'),
('Priya Rajesh', '7B2D4E6F', 4, 2, 'paid', 'active', '+60123456805', '2012-11-30', '2024-01-10'),
('Muhammad Amin bin Omar', '9C3E5F1A', 5, 2, 'unpaid', 'active', '+60123456807', '2013-07-18', '2024-01-10');

-- ============================================
-- Table 6: attendance_records
-- ============================================
CREATE TABLE attendance_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    bus_id INT NOT NULL,
    rfid_uid VARCHAR(20) NOT NULL,
    action ENUM('boarded', 'dropped_off') NOT NULL,
    timestamp DATETIME NOT NULL,
    device_id VARCHAR(50) NOT NULL,
    verification_status ENUM('verified', 'pending', 'flagged') DEFAULT 'verified',
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(bus_id) ON DELETE CASCADE,
    INDEX idx_timestamp (timestamp),
    INDEX idx_student (student_id),
    INDEX idx_date_action (timestamp, action),
    INDEX idx_composite (student_id, timestamp)
) ENGINE=InnoDB;

-- Insert sample attendance records for today
INSERT INTO attendance_records (student_id, bus_id, rfid_uid, action, timestamp, device_id, verification_status) VALUES
(1, 1, '4A3B2C1D', 'boarded', NOW() - INTERVAL 2 HOUR, 'ESP32_BUS01', 'verified'),
(2, 1, 'E8F94A23', 'boarded', NOW() - INTERVAL 2 HOUR, 'ESP32_BUS01', 'verified'),
(3, 1, '3F8A9B4C', 'boarded', NOW() - INTERVAL 2 HOUR, 'ESP32_BUS01', 'verified'),
(4, 2, '7B2D4E6F', 'boarded', NOW() - INTERVAL 1 HOUR, 'ESP32_BUS02', 'verified'),
(5, 2, '9C3E5F1A', 'boarded', NOW() - INTERVAL 1 HOUR, 'ESP32_BUS02', 'verified'),
(1, 1, '4A3B2C1D', 'dropped_off', NOW() - INTERVAL 30 MINUTE, 'ESP32_BUS01', 'verified'),
(2, 1, 'E8F94A23', 'dropped_off', NOW() - INTERVAL 30 MINUTE, 'ESP32_BUS01', 'verified');

-- ============================================
-- Table 7: payments
-- ============================================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'online') DEFAULT 'cash',
    month_paid_for VARCHAR(20) NOT NULL,
    status ENUM('completed', 'pending', 'failed') DEFAULT 'completed',
    reference_number VARCHAR(50),
    notes TEXT,
    recorded_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_date (payment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Insert sample payments
INSERT INTO payments (student_id, amount, payment_date, payment_method, month_paid_for, status, reference_number, recorded_by) VALUES
(1, 150.00, '2026-01-05', 'bank_transfer', 'January 2026', 'completed', 'PAY20260105001', 1),
(2, 150.00, '2026-01-07', 'cash', 'January 2026', 'completed', 'PAY20260107001', 2),
(4, 150.00, '2026-01-10', 'online', 'January 2026', 'completed', 'PAY20260110001', 1);

-- ============================================
-- Table 8: notifications
-- ============================================
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    recipient_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'alert', 'request') DEFAULT 'info',
    related_entity VARCHAR(50),
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_recipient_read (recipient_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Insert sample notifications
INSERT INTO notifications (sender_id, recipient_id, title, message, type, related_entity, related_id, is_read) VALUES
(2, 1, 'Student Assignment Request', 'Sarah Lee requested to reassign Student #0003 (Wong Li Ming) to Bus #02', 'request', 'student', 3, FALSE),
(3, 1, 'Payment Reminder', 'Ahmad Firdaus noted that 2 students have unpaid fees for January 2026', 'alert', 'payment', NULL, FALSE);

-- ============================================
-- Table 9: iot_devices
-- ============================================
CREATE TABLE iot_devices (
    device_id VARCHAR(50) PRIMARY KEY,
    device_name VARCHAR(100) NOT NULL,
    bus_id INT,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('online', 'offline', 'error') DEFAULT 'offline',
    firmware_version VARCHAR(20),
    last_heartbeat DATETIME,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(bus_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_heartbeat (last_heartbeat)
) ENGINE=InnoDB;

-- Insert sample IoT devices (API keys are hashed SHA256)
INSERT INTO iot_devices (device_id, device_name, bus_id, api_key, status, firmware_version, last_heartbeat, ip_address) VALUES
('ESP32_BUS01', 'Bus #01 RFID Reader', 1, 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'online', 'v1.0.2', NOW(), '192.168.1.101'),
('ESP32_BUS02', 'Bus #02 RFID Reader', 2, 'fcde2b2edba56bf408601fb721fe9b5c338d10ee429ea04fae5511b68fbf8fb9', 'online', 'v1.0.2', NOW(), '192.168.1.102'),
('ESP32_BUS03', 'Bus #03 RFID Reader', 3, 'bef57ec7f53a6d40beb640a780a639c83bc29ac8a9816f1fc6c5c6dcd93c4721', 'offline', 'v1.0.1', NOW() - INTERVAL 2 HOUR, '192.168.1.103');

-- ============================================
-- Table 10: audit_logs
-- ============================================
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_timestamp (timestamp),
    INDEX idx_user_action (user_id, action),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;

-- Insert sample audit logs
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES
(1, 'created_user', 'user', 4, '{"username": "driver1", "role": "driver"}', '127.0.0.1'),
(1, 'created_bus', 'bus', 1, '{"bus_number": "Bus #01", "capacity": 30}', '127.0.0.1'),
(2, 'assigned_student', 'student', 1, '{"student_id": 1, "bus_id": 1}', '127.0.0.1');

-- ============================================
-- VIEWS FOR COMMON QUERIES
-- ============================================

-- View: Student Overview
CREATE OR REPLACE VIEW vw_student_overview AS
SELECT 
    s.student_id,
    s.student_name,
    s.rfid_uid,
    s.payment_status,
    s.status,
    p.parent_name,
    p.phone_primary,
    b.bus_number,
    b.bus_id,
    u.full_name as driver_name
FROM students s
LEFT JOIN parents p ON s.parent_id = p.parent_id
LEFT JOIN buses b ON s.bus_id = b.bus_id
LEFT JOIN users u ON b.assigned_driver_id = u.user_id;

-- View: Today's Attendance Summary
CREATE OR REPLACE VIEW vw_today_attendance AS
SELECT 
    DATE(ar.timestamp) as attendance_date,
    ar.action,
    COUNT(*) as total_records,
    COUNT(DISTINCT ar.student_id) as unique_students,
    COUNT(DISTINCT ar.bus_id) as buses_used
FROM attendance_records ar
WHERE DATE(ar.timestamp) = CURDATE()
GROUP BY DATE(ar.timestamp), ar.action;

-- ============================================
-- Table 11: user_sessions (for multi-concurrent logins)
-- ============================================
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    session_data LONGTEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_role (user_id, role_name),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- ============================================
-- STORED PROCEDURES
-- ============================================

DELIMITER //

-- Procedure: Get Student Attendance History
CREATE PROCEDURE sp_get_student_attendance(
    IN p_student_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT 
        ar.timestamp,
        ar.action,
        b.bus_number,
        u.full_name as driver_name,
        ar.verification_status
    FROM attendance_records ar
    JOIN buses b ON ar.bus_id = b.bus_id
    LEFT JOIN users u ON b.assigned_driver_id = u.user_id
    WHERE ar.student_id = p_student_id
    AND DATE(ar.timestamp) BETWEEN p_start_date AND p_end_date
    ORDER BY ar.timestamp DESC;
END //

-- Procedure: Get Bus Daily Report
CREATE PROCEDURE sp_get_bus_report(
    IN p_bus_id INT,
    IN p_date DATE
)
BEGIN
    SELECT 
        ar.timestamp,
        s.student_name,
        s.rfid_uid,
        ar.action,
        ar.verification_status
    FROM attendance_records ar
    JOIN students s ON ar.student_id = s.student_id
    WHERE ar.bus_id = p_bus_id
    AND DATE(ar.timestamp) = p_date
    ORDER BY ar.timestamp;
END //

DELIMITER ;

-- ============================================
-- TRIGGERS
-- ============================================

DELIMITER //

-- Trigger: Update student payment status after payment
CREATE TRIGGER trg_after_payment_insert
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' THEN
        UPDATE students 
        SET payment_status = 'paid' 
        WHERE student_id = NEW.student_id;
    END IF;
END //

-- Trigger: Log user deletions
CREATE TRIGGER trg_before_user_delete
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (OLD.user_id, 'deleted_user', 'user', OLD.user_id, 
            JSON_OBJECT('username', OLD.username, 'full_name', OLD.full_name));
END //

DELIMITER ;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Additional composite indexes for common queries
CREATE INDEX idx_attendance_date_student ON attendance_records(timestamp, student_id);
CREATE INDEX idx_students_bus_status ON students(bus_id, status);
CREATE INDEX idx_notifications_recipient_date ON notifications(recipient_id, created_at);

-- ============================================
-- GRANT PRIVILEGES (adjust as needed)
-- ============================================
-- GRANT ALL PRIVILEGES ON smartschoolbus_db.* TO 'root'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================
-- Table 11: logout_feedback
-- User feedback on logout
-- ============================================
CREATE TABLE IF NOT EXISTS logout_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_role ENUM('admin', 'staff', 'driver') NOT NULL,
    rating TINYINT(2) NOT NULL,
    message TEXT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255),
    session_duration INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_role (user_role),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================
-- Table 12: daily_checklists
-- Morning and evening operational checklists
-- ============================================
CREATE TABLE IF NOT EXISTS daily_checklists (
    checklist_id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_date DATE NOT NULL,
    shift_type ENUM('morning', 'evening') NOT NULL,
    staff_id INT NOT NULL,
    buses_inspected BOOLEAN DEFAULT FALSE,
    drivers_present BOOLEAN DEFAULT FALSE,
    rfid_readers_online BOOLEAN DEFAULT FALSE,
    emergency_kits_checked BOOLEAN DEFAULT FALSE,
    all_students_accounted BOOLEAN DEFAULT FALSE,
    buses_returned BOOLEAN DEFAULT FALSE,
    incidents_reported BOOLEAN DEFAULT FALSE,
    handover_completed BOOLEAN DEFAULT FALSE,
    notes TEXT,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_date (checklist_date),
    INDEX idx_staff (staff_id),
    UNIQUE KEY unique_checklist (checklist_date, shift_type, staff_id)
) ENGINE=InnoDB;

-- ============================================
-- Table 13: student_absences
-- Track student absences and reasons
-- ============================================
CREATE TABLE IF NOT EXISTS student_absences (
    absence_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    absence_date DATE NOT NULL,
    reason ENUM('sick', 'vacation', 'emergency', 'other') NOT NULL,
    reason_details TEXT,
    marked_by INT NOT NULL,
    parent_notified BOOLEAN DEFAULT FALSE,
    notification_sent_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_student_date (student_id, absence_date),
    INDEX idx_absence_date (absence_date)
) ENGINE=InnoDB;

-- ============================================
-- Table 14: incidents
-- Staff incident reporting to admin
-- ============================================
CREATE TABLE IF NOT EXISTS incidents (
    incident_id INT AUTO_INCREMENT PRIMARY KEY,
    reported_by INT NOT NULL,
    incident_type ENUM('accident', 'equipment_failure', 'student_behavior', 'driver_issue', 'safety_concern', 'other') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    bus_id INT,
    student_id INT,
    driver_id INT,
    status ENUM('open', 'investigating', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT,
    resolution_notes TEXT,
    resolved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(bus_id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================
-- Table 15: notifications_log
-- SMS/WhatsApp notification history to parents
-- ============================================
CREATE TABLE IF NOT EXISTS notifications_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    sent_by INT NOT NULL,
    recipient_type ENUM('single', 'bulk', 'broadcast') NOT NULL,
    recipient_phone VARCHAR(20),
    recipient_count INT DEFAULT 1,
    message_type ENUM('absence_alert', 'payment_reminder', 'emergency', 'general', 'custom') NOT NULL,
    message_content TEXT NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    sent_at DATETIME,
    delivered_at DATETIME,
    failure_reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_type (message_type),
    INDEX idx_sent_date (sent_at)
) ENGINE=InnoDB;

-- ============================================
-- Table 16: bulk_operations
-- Track bulk operations for auditing
-- ============================================
CREATE TABLE IF NOT EXISTS bulk_operations (
    operation_id INT AUTO_INCREMENT PRIMARY KEY,
    performed_by INT NOT NULL,
    operation_type ENUM('bulk_verify', 'bulk_absence', 'bulk_notification', 'bulk_payment_status') NOT NULL,
    records_affected INT NOT NULL,
    operation_details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_type (operation_type),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- ============================================
-- SAMPLE DATA FOR NEW TABLES
-- ============================================

-- Sample daily checklists
INSERT INTO daily_checklists (checklist_date, shift_type, staff_id, buses_inspected, drivers_present, rfid_readers_online, emergency_kits_checked, completed_at) VALUES
(CURDATE(), 'morning', 2, TRUE, TRUE, TRUE, TRUE, NOW());

-- Sample incidents
INSERT INTO incidents (reported_by, incident_type, severity, title, description, status, bus_id) VALUES
(2, 'equipment_failure', 'medium', 'RFID Reader Malfunction', 'RFID reader on Bus #3 not responding. Requires technician check.', 'open', 3);

-- Sample absences
INSERT INTO student_absences (student_id, absence_date, reason, reason_details, marked_by, parent_notified) VALUES
(5, CURDATE(), 'sick', 'Student has fever, parent called in morning', 2, TRUE);

-- ============================================
-- DATABASE SETUP COMPLETE
-- ============================================

-- Database setup completed successfully!
-- You can now access the system at: http://localhost/SmartBus
