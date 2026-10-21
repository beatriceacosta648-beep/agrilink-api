<?php
header("Content-Type: application/json");
require_once "db_connect.php";

try {
    // 1. Correct query with INNER JOIN to get category_name
    $sql = "SELECT 
                i.item_id, 
                i.item_name, 
                i.description, 
                i.unit_price, 
                i.stock_quantity,
                c.category_id,
                c.category_name
            FROM items i
            LEFT JOIN category c ON i.category_id = c.category_id";

    // 2. Use $conn (MySQLi) instead of $pdo
    $result = $conn->query($sql);

    if ($result) {
        $items = [];
        while ($row = $result->fetch_assoc()) {
            // Convert numeric string types to proper numbers for JSON
            $row['item_id'] = (int)$row['item_id'];
            $row['unit_price'] = (float)$row['unit_price'];
            $row['stock_quantity'] = (int)$row['stock_quantity'];
            $items[] = $row;
        }

        echo json_encode([
            "status" => "success",
            "data" => $items
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Query failed: " . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
