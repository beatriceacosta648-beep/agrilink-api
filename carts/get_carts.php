<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include 'db_connect.php';

function fail($message) {
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

// Query string (?user_id=1&cart_status=checked_out) or JSON body.
// cart_status is optional: 'active' or 'checked_out'. Use checked_out for the History section.
$body = json_decode(file_get_contents("php://input"), true);
$in   = array_merge($_GET, is_array($body) ? $body : []);

$userId = (int)($in['user_id'] ?? 0);
$status = trim($in['cart_status'] ?? '');

if ($userId <= 0) {
    fail("Kulang ang impormasyon.");
}
if ($status !== '' && !in_array($status, ['active', 'checked_out'], true)) {
    fail("Hindi tama ang cart_status.");
}

try {
    $sql = "SELECT c.cart_id, c.cart_status, c.order_id, c.created_at, c.updated_at,
                   o.order_status, o.payment_status, o.total_amount,
                   COUNT(ci.cart_items_id) AS item_count
            FROM cart c
            LEFT JOIN orders o ON o.order_id = c.order_id
            LEFT JOIN cart_items ci ON ci.cart_id = c.cart_id
            WHERE c.user_id = ?";
    if ($status !== '') {
        $sql .= " AND c.cart_status = ?";
    }
    $sql .= " GROUP BY c.cart_id, c.cart_status, c.order_id, c.created_at, c.updated_at,
                       o.order_status, o.payment_status, o.total_amount
              ORDER BY c.updated_at DESC, c.cart_id DESC";

    $stmt = $conn->prepare($sql);
    if ($status !== '') {
        $stmt->bind_param("is", $userId, $status);
    } else {
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $carts = [];
    while ($row = $result->fetch_assoc()) {
        $carts[] = $row;
    }

    echo json_encode(["success" => true, "count" => count($carts), "carts" => $carts]);
} catch (Throwable $e) {
    error_log("get_carts.php: " . $e->getMessage());
    fail("Hindi makuha ang mga cart.");
}
