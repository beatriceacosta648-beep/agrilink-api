<?php
header("Content-Type: application/json");
require_once "db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);
$itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;

if ($itemId <= 0) {
    echo json_encode(["success" => false, "message" => "Valid item_id is required."]);
    exit;
}

$sql = "DELETE FROM items WHERE item_id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $itemId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Item deleted successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to delete item."]);
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>
