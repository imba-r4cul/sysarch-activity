<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Default XAMPP username
define('DB_PASS', '');          // Default XAMPP has no password
define('DB_NAME', 'student_management');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// ── Auto-create system_settings table ──
$conn->query("
    CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$conn->query("INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('reservations_enabled', '1')");

// ── Auto-create lab_software table ──
$conn->query("
    CREATE TABLE IF NOT EXISTS `lab_software` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `lab` VARCHAR(100) NOT NULL,
        `software_name` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_lab_software` (`lab`, `software_name`),
        INDEX `idx_lab` (`lab`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Global hook to auto-process reservations
function auto_process_reservations($conn) {
    // 1. Auto-Reject Pending reservations past their scheduled time
    $conn->query("
        UPDATE reservations 
        SET status = 'Rejected', admin_note = 'Reservation expired: Past scheduled time' 
        WHERE status = 'Pending' 
          AND STR_TO_DATE(CONCAT(reservation_date, ' ', reservation_time), '%Y-%m-%d %H:%i') <= NOW()
    ");
    
    // 2. Auto-Start Approved reservations past their scheduled time
    $res = $conn->query("
        SELECT r.id, r.user_id, r.purpose, r.lab, r.pc_number, u.id_number, u.first_name, u.last_name 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.status = 'Approved' 
          AND STR_TO_DATE(CONCAT(r.reservation_date, ' ', r.reservation_time), '%Y-%m-%d %H:%i') <= NOW()
    ");
    
    if ($res && $res->num_rows > 0) {
        $ins = $conn->prepare("INSERT INTO sit_in_records (user_id, id_number, first_name, last_name, purpose, lab, pc_number, status, time_in) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())");
        $upd = $conn->prepare("UPDATE reservations SET status = 'Completed', admin_note = 'Session auto-started' WHERE id = ?");
        
        while ($row = $res->fetch_assoc()) {
            if ($ins && $upd) {
                $ins->bind_param('issssis', $row['user_id'], $row['id_number'], $row['first_name'], $row['last_name'], $row['purpose'], $row['lab'], $row['pc_number']);
                if ($ins->execute()) {
                    $upd->bind_param('i', $row['id']);
                    $upd->execute();
                }
            }
        }
        if ($ins) $ins->close();
        if ($upd) $upd->close();
    }
}
auto_process_reservations($conn);
?>