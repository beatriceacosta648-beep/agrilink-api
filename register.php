<?php
header('Content-Type: application/json');
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

// Reject empty/invalid requests (e.g. opening this URL in a browser)
if (!$data || empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$full_name = $conn->real_escape_string($data['full_name']);
$email = $conn->real_escape_string($data['email']);
$password = password_hash($data['password'], PASSWORD_DEFAULT);
$role = 'buyer'; // this screen only creates normal user accounts

// Prevent duplicate emails
$check = $conn->query("SELECT * FROM users WHERE email = '$email'");
if ($check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email already registered"]);
    exit;
}

$sql = "INSERT INTO users (full_name, email, password, role, phone_num)
        VALUES ('$full_name', '$email', '$password', '$role', NULL)";

if ($conn->query($sql)) {
    echo json_encode(["success" => true, "message" => "Account created"]);
} else {
    echo json_encode(["success" => false, "message" => $conn->error]);
}

$conn->close();
?>
