<?php
/**
 * Run this script once to create the default admin account.
 * Default credentials:  admin / admin123
 *
 * Usage: php db/seed_admin.php
 *        or visit http://localhost/sysarch_activity/db/seed_admin.php
 */
require_once __DIR__ . '/../config/database.php';

$username = 'admin';
$password = 'admin123';
$displayName = 'CCS Admin';

$hashed = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Admin account '{$username}' already exists. Updating password...\n";
    $stmt->close();
    $upd = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
    $upd->bind_param('ss', $hashed, $username);
    $upd->execute();
    $upd->close();
    echo "Password updated successfully.\n";
} else {
    $stmt->close();
    $ins = $conn->prepare("INSERT INTO admin_users (username, password, display_name) VALUES (?, ?, ?)");
    $ins->bind_param('sss', $username, $hashed, $displayName);
    if ($ins->execute()) {
        echo "Admin account created successfully!\n";
        echo "Username: {$username}\n";
        echo "Password: {$password}\n";
    } else {
        echo "Error creating admin: " . $ins->error . "\n";
    }
    $ins->close();
}

echo "\nDone.\n";
