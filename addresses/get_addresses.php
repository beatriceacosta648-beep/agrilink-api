<?php
header('Content-Type: application/json');
include '../db_connect.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId <= 0) {
    $data = json_decode(file_get_contents("php://input"), true);
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
}

if ($userId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Valid user_id is required."
    ]);
    exit;
}

$sql = "SELECT
            address_id,
            user_id,
            `Region` AS region,
            `Province` AS province,
            `City/Municipality` AS city,
            `Barangay` AS barangay,
            `Home Address` AS street
        FROM address
        WHERE user_id = ?
        ORDER BY address_id DESC";

try {

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    $addresses = [];

    while ($row = $result->fetch_assoc()) {
        $row['address_id'] = (int)$row['address_id'];
        $row['user_id'] = (int)$row['user_id'];
        $addresses[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($addresses),
        "data" => $addresses
    ]);

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
