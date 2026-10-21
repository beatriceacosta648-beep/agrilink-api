<?php
header('Content-Type: application/json');
include '../db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$fullName = isset($data['full_name']) ? $conn->real_escape_string(trim($data['full_name'])) : '';
$phoneNum = isset($data['phone_num']) ? $conn->real_escape_string(trim($data['phone_num'])) : '';

if ($userId <= 0 || $fullName === '') {
    echo json_encode(["success" => false, "message" => "user_id and full_name are required"]);
    exit;
}

$sql = "UPDATE users 
        SET full_name = '$fullName', phone_num = '$phoneNum' 
        WHERE user_id = $userId";

if ($conn->query($sql)) {
    echo json_encode(["success" => true, "message" => "User updated successfully"]);
} else {
    echo json_encode(["success" => false, "message" => $conn->error]);
}

$conn->close();
?>
