<?php
header('Content-Type: application/json');
include '../db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$addressId = isset($data['address_id']) ? (int)$data['address_id'] : 0;

if ($addressId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Valid address_id is required."
    ]);
    exit;
}

$sql = "DELETE FROM address WHERE address_id = ?";

try {

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $addressId);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Address deleted successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to delete address."
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
