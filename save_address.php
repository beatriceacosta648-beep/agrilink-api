<?php
header('Content-Type: application/json');

include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$userId   = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$region   = trim($data['region'] ?? '');
$province = trim($data['province'] ?? '');
$city     = trim($data['city'] ?? '');
$barangay = trim($data['barangay'] ?? '');
$street   = trim($data['street'] ?? '');

if ($userId <= 0 || $region === '' || $province === '' || $city === '' || $barangay === '' || $street === '') {
    echo json_encode(["success" => false, "message" => "Kulang ang impormasyon."]);
    exit;
}

$sql = "INSERT INTO address (user_id, `Region`, `Province`, `City/Municipality`, `Barangay`, `Home Address`)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssss", $userId, $region, $province, $city, $barangay, $street);

try {
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Na-save ang address.",
            "address_id" => $conn->insert_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Hindi na-save ang address."]);
    }
} catch (mysqli_sql_exception $e) {
    echo json_encode(["success" => false, "message" => "Hindi na-save ang address."]);
}