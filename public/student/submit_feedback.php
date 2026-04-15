<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$sitInId = isset($_POST['sit_in_id']) ? (int) $_POST['sit_in_id'] : 0;
$feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

if ($sitInId <= 0 || empty($feedback)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Ensure record belongs to user and is NOT active
$checkSql = "SELECT id FROM sit_in_records WHERE id = ? AND user_id = ? AND status <> 'Active'";
$checkStmt = $conn->prepare($checkSql);
if ($checkStmt) {
    $checkStmt->bind_param('ii', $sitInId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Record not found or is still active']);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// Update feedback
$updateSql = "UPDATE sit_in_records SET feedback = ? WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
if ($updateStmt) {
    $updateStmt->bind_param('si', $feedback, $sitInId);
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save feedback']);
    }
    $updateStmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
