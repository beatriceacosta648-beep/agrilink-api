<?php
header("Content-Type: application/json");
require_once "../db_connect.php";

// Accept item_id from GET query parameter or JSON body
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($itemId <= 0) {
    echo json_encode(["status" => "error", "message" => "Valid item_id is required."]);
    exit;
}

try {
    $sql = "SELECT 
                i.item_id, 
                i.item_name, 
                i.description, 
                i.unit_price, 
                i.stock_quantity,
                c.category_id,
                c.category_name
            FROM items i
            LEFT JOIN category c ON i.category_id = c.category_id
            WHERE i.item_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $row['item_id'] = (int)$row['item_id'];
        $row['unit_price'] = (float)$row['unit_price'];
        $row['stock_quantity'] = (int)$row['stock_quantity'];

        echo json_encode([
            "status" => "success",
            "data" => $row
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Item not found."]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>
