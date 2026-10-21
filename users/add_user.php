<?php
header('Content-Type: application/json');
include '../db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$full_name = $conn->real_escape_string(trim($data['full_name']));
$email = $conn->real_escape_string(trim($data['email']));
$phone_num = isset($data['phone_num']) ? "'" . $conn->real_escape_string(trim($data['phone_num'])) . "'" : "NULL";
$password = password_hash($data['password'], PASSWORD_DEFAULT);
$role = isset($data['role']) ? $conn->real_escape_string($data['role']) : 'buyer';

// Check duplicate email
$check = $conn->query("SELECT user_id FROM users WHERE email = '$email'");
if ($check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email already registered"]);
    exit;
}

$sql = "INSERT INTO users (full_name, email, password, role, phone_num)
        VALUES ('$full_name', '$email', '$password', '$role', $phone_num)";

if ($conn->query($sql)) {
    echo json_encode([
        "success" => true,
        "message" => "User added successfully",
        "user_id" => $conn->insert_id
    ]);
} else {
    echo json_encode(["success" => false, "message" => $conn->error]);
}

$conn->close();
?>
