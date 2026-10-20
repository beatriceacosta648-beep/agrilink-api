<?php
header("Content-Type: application/json");
require_once "db_connect.php";

try {
    $query = "SELECT 
                i.item_id, 
                i.item_name, 
                i.description, 
                i.unit_price, 
                i.stock_quantity,
                c.category_id,
                c.category_name
              FROM items i
              JOIN category c ON i.category_id = c.category_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $items
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>