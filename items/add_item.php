<?php
header("Content-Type: application/json");
require_once "db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);

$itemName     = trim($data['item_name'] ?? '');
$description  = trim($data['description'] ?? '');
$categoryId   = isset($data['category_id']) ? (int)$data['category_id'] : 0;
$unitPrice    = isset($data['unit_price']) ? (float)$data['unit_price'] : 0.0;
$stockQuantity= isset($data['stock_quantity']) ? (int)$data['stock_quantity'] : 0;

if ($itemName === '' || $categoryId <= 0 || $unitPrice <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid item details provided."]);
    exit;
}

$sql = "INSERT INTO items (item_name, description, category_id, unit_price, stock_quantity) 
        VALUES (?, ?, ?, ?, ?)";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssidi", $itemName, $description, $categoryId, $unitPrice, $stockQuantity);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Item added successfully.",
            "item_id" => $conn->insert_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to add item."]);
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>
