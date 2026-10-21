<?php
header('Content-Type: application/json');
include '../db_connect.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId <= 0) {
    // Check if passed via JSON body instead
    $data = json_decode(file_get_contents("php://input"), true);
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
}

if ($userId <= 0) {
    echo json_encode(["success" => false, "message" => "Valid user_id is required"]);
    exit;
}

$sql = "SELECT user_id, full_name, email, role, phone_num, created_at FROM users WHERE user_id = $userId";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user['user_id'] = (int)$user['user_id'];
    echo json_encode(["success" => true, "data" => $user]);
} else {
    echo json_encode(["success" => false, "message" => "User not found"]);
}

$conn->close();
?>
