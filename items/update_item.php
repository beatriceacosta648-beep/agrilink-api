<?php
header("Content-Type: application/json");
require_once "../db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);

$itemId       = isset($data['item_id']) ? (int)$data['item_id'] : 0;
$itemName     = trim($data['item_name'] ?? '');
$description  = trim($data['description'] ?? '');
$categoryId   = isset($data['category_id']) ? (int)$data['category_id'] : 0;
$unitPrice    = isset($data['unit_price']) ? (float)$data['unit_price'] : 0.0;
$stockQuantity= isset($data['stock_quantity']) ? (int)$data['stock_quantity'] : 0;

if ($itemId <= 0 || $itemName === '' || $categoryId <= 0 || $unitPrice <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid parameters for update."]);
    exit;
}

$sql = "UPDATE items 
        SET item_name = ?, description = ?, category_id = ?, unit_price = ?, stock_quantity = ? 
        WHERE item_id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssidii", $itemName, $description, $categoryId, $unitPrice, $stockQuantity, $itemId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Item updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update item."]);
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>
