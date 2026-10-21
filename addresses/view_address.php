<?php
header('Content-Type: application/json');
include '../db_connect.php';

$addressId = isset($_GET['address_id']) ? (int)$_GET['address_id'] : 0;

if ($addressId <= 0) {
    $data = json_decode(file_get_contents("php://input"), true);
    $addressId = isset($data['address_id']) ? (int)$data['address_id'] : 0;
}

if ($addressId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Valid address_id is required."
    ]);
    exit;
}

$sql = "SELECT *
        FROM address
        WHERE address_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $addressId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    echo json_encode([
        "success" => true,
        "data" => $result->fetch_assoc()
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Address not found."
    ]);

}

$stmt->close();
$conn->close();
?>
