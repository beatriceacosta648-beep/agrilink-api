<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include '../db_connect.php';

function fail($message) {
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);
$in   = array_merge($_GET, is_array($body) ? $body : []);

$orderId = (int)($in['order_id'] ?? 0);
$userId  = (int)($in['user_id'] ?? 0);

if ($orderId <= 0 || $userId <= 0) {
    fail("Kulang ang impormasyon.");
}

// "Delete" cancels the order instead of erasing it, so order history is kept
try {
    $conn->begin_transaction();

    // Lock the order, and make sure it belongs to this user
    $stmt = $conn->prepare(
        "SELECT order_status FROM orders WHERE order_id = ? AND user_id = ? FOR UPDATE"
    );
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new RuntimeException("Hindi nahanap ang order.");
    }
    if (!in_array($order['order_status'], ['pending', 'confirmed'], true)) {
        throw new RuntimeException(
            "Hindi na maaaring ikansela ang order na ito (status: {$order['order_status']})."
        );
    }

    // Mark it cancelled (and refunded if it had been paid)
    $stmt = $conn->prepare(
        "UPDATE orders
         SET order_status = 'cancelled',
             payment_status = IF(payment_status = 'paid', 'refunded', payment_status)
         WHERE order_id = ?"
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->close();

    // Put the stock back
    $stmt = $conn->prepare(
        "UPDATE items i
         JOIN order_items oi ON oi.item_id = i.item_id
         SET i.stock_quantity = i.stock_quantity + oi.quantity
         WHERE oi.order_id = ?"
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        "success"  => true,
        "message"  => "Nakansela ang order.",
        "order_id" => $orderId
    ]);
} catch (RuntimeException $e) {
    $conn->rollback();
    fail($e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    error_log("delete_order.php: " . $e->getMessage());
    fail("Hindi makansela ang order.");
}
