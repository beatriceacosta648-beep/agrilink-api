<?php
header('Content-Type: application/json');
include '../db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$addressId = isset($data['address_id']) ? (int)$data['address_id'] : 0;

$region   = trim($data['region'] ?? '');
$province = trim($data['province'] ?? '');
$city     = trim($data['city'] ?? '');
$barangay = trim($data['barangay'] ?? '');
$street   = trim($data['street'] ?? '');

if (
    $addressId <= 0 ||
    $region === '' ||
    $province === '' ||
    $city === '' ||
    $barangay === '' ||
    $street === ''
) {
    echo json_encode([
        "success" => false,
        "message" => "Kulang ang impormasyon."
    ]);
    exit;
}

$sql = "UPDATE address
        SET
            `Region` = ?,
            `Province` = ?,
            `City/Municipality` = ?,
            `Barangay` = ?,
            `Home Address` = ?
        WHERE address_id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssi",
        $region,
        $province,
        $city,
        $barangay,
        $street,
        $addressId
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Address updated successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to update address."
        ]);
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
