<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include '../db_connect.php';

function fail($message) {
    echo json_encode([
        "success" => false,
        "message" => $message
    ]);
    exit;
}

// Accepts query string (?order_id=1&user_id=1) or a JSON body
$body = json_decode(file_get_contents("php://input"), true);
$in = array_merge($_GET, is_array($body) ? $body : []);

$orderId = (int)($in['order_id'] ?? 0);
$userId  = (int)($in['user_id'] ?? 0);

if ($orderId <= 0 || $userId <= 0) {
    fail("Kulang ang impormasyon.");
}

try {

    // The order, only if it belongs to this user
    $stmt = $conn->prepare(
        "SELECT
            o.order_id,
            o.user_id,
            o.group_id,
            o.total_amount,
            o.order_status,
            o.payment_status,
            o.date_paid,
            o.estimated_delivery_date,
            o.created_at,
            o.updated_at,
            pm.method_name AS payment_method,
            a.`Region` AS region,
            a.`Province` AS province,
            a.`City/Municipality` AS city,
            a.`Barangay` AS barangay,
            a.`Home Address` AS street
         FROM orders o
         LEFT JOIN address a
            ON a.address_id = o.address_id
         LEFT JOIN payment_methods pm
            ON pm.payment_methods_id = o.payment_method_id
         WHERE o.order_id = ?
         AND o.user_id = ?"
    );

    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$order) {
        fail("Hindi nahanap ang order.");
    }

    // Cast numeric fields
    $order['order_id'] = (int)$order['order_id'];
    $order['user_id'] = (int)$order['user_id'];

    // Order items
    $stmt = $conn->prepare(
        "SELECT
            oi.item_id,
            i.item_name,
            oi.quantity,
            oi.unit_price,
            (oi.quantity * oi.unit_price) AS subtotal
         FROM order_items oi
         LEFT JOIN items i
            ON i.item_id = oi.item_id
         WHERE oi.order_id = ?"
    );

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $result = $stmt->get_result();

    $items = [];

    while ($row = $result->fetch_assoc()) {

        $row['item_id'] = (int)$row['item_id'];
        $row['quantity'] = (int)$row['quantity'];

        $items[] = $row;
    }

    $order['items'] = $items;

    $stmt->close();

    echo json_encode([
        "success" => true,
        "order" => $order
    ]);

} catch (Throwable $e) {

    error_log("view_order.php: " . $e->getMessage());

    fail("Hindi makuha ang order.");

} finally {

    if (isset($conn)) {
        $conn->close();
    }

}
?>
