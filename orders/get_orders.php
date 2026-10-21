<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include 'db_connect.php';

function fail($message) {
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

// Accepts query string (?user_id=1) or a JSON body
$body = json_decode(file_get_contents("php://input"), true);
$in   = array_merge($_GET, is_array($body) ? $body : []);

$userId = (int)($in['user_id'] ?? 0);
$status = trim($in['order_status'] ?? '');   // optional filter

if ($userId <= 0) {
    fail("Kulang ang impormasyon.");
}

try {
    $sql = "SELECT o.order_id, o.total_amount, o.order_status, o.payment_status,
                   o.estimated_delivery_date, o.created_at,
                   COUNT(oi.order_items_id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.order_id
            WHERE o.user_id = ?";
    if ($status !== '') {
        $sql .= " AND o.order_status = ?";
    }
    $sql .= " GROUP BY o.order_id
              ORDER BY o.created_at DESC, o.order_id DESC";

    $stmt = $conn->prepare($sql);
    if ($status !== '') {
        $stmt->bind_param("is", $userId, $status);
    } else {
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count"   => count($orders),
        "orders"  => $orders
    ]);
} catch (Throwable $e) {
    error_log("get_orders.php: " . $e->getMessage());
    fail("Hindi makuha ang mga order.");
}
