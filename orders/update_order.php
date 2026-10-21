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

try {

    // Accept GET params or JSON body
    $body = json_decode(file_get_contents("php://input"), true);

    $input = array_merge(
        $_GET,
        is_array($body) ? $body : []
    );

    $orderId = (int)($input['order_id'] ?? 0);

    $orderStatus = strtolower(trim($input['order_status'] ?? ''));
    $paymentStatus = strtolower(trim($input['payment_status'] ?? ''));
    $estimatedDelivery = trim($input['estimated_delivery_date'] ?? '');

    if ($orderId <= 0) {
        fail("Kulang ang impormasyon.");
    }

    $allowedOrderStatus = [
        'pending',
        'confirmed',
        'out_for_delivery',
        'delivered'
    ];

    $allowedPaymentStatus = [
        'unpaid',
        'paid',
        'refunded'
    ];

    if ($orderStatus === 'cancelled') {
        fail("Gamitin ang delete_order.php para i-cancel ang order.");
    }

    if (
        $orderStatus !== '' &&
        !in_array($orderStatus, $allowedOrderStatus, true)
    ) {
        fail("Hindi tama ang order_status.");
    }

    if (
        $paymentStatus !== '' &&
        !in_array($paymentStatus, $allowedPaymentStatus, true)
    ) {
        fail("Hindi tama ang payment_status.");
    }

    if ($estimatedDelivery !== '') {

        $date = DateTime::createFromFormat(
            'Y-m-d',
            $estimatedDelivery
        );

        if (
            !$date ||
            $date->format('Y-m-d') !== $estimatedDelivery
        ) {
            fail(
                "Hindi tama ang petsa. Gamitin ang format na YYYY-MM-DD."
            );
        }
    }

    // Verify order exists
    $stmt = $conn->prepare(
        "SELECT order_status
         FROM orders
         WHERE order_id = ?"
    );

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $currentOrder = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$currentOrder) {
        fail("Hindi nahanap ang order.");
    }

    if ($currentOrder['order_status'] === 'cancelled') {
        fail(
            "Kanselado na ang order, hindi na ito maaaring baguhin."
        );
    }

    $setParts = [];
    $types = '';
    $values = [];

    if ($orderStatus !== '') {
        $setParts[] = "order_status = ?";
        $types .= "s";
        $values[] = $orderStatus;
    }

    if ($paymentStatus !== '') {

        $setParts[] = "payment_status = ?";
        $types .= "s";
        $values[] = $paymentStatus;

        if ($paymentStatus === 'paid') {
            $setParts[] = "date_paid = NOW()";
        } elseif ($paymentStatus === 'unpaid') {
            $setParts[] = "date_paid = NULL";
        }
    }

    if ($estimatedDelivery !== '') {
        $setParts[] = "estimated_delivery_date = ?";
        $types .= "s";
        $values[] = $estimatedDelivery;
    }

    // Optional auto-update timestamp
    $setParts[] = "updated_at = NOW()";

    if (empty($setParts)) {
        fail("Walang babaguhin.");
    }

    $sql = "
        UPDATE orders
        SET " . implode(', ', $setParts) . "
        WHERE order_id = ?
    ";

    $types .= "i";
    $values[] = $orderId;

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        $types,
        ...$values
    );

    $stmt->execute();
    $stmt->close();

    // Fetch updated order
    $stmt = $conn->prepare("
        SELECT
            order_id,
            order_status,
            payment_status,
            date_paid,
            estimated_delivery_date,
            updated_at
        FROM orders
        WHERE order_id = ?
    ");

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($order) {
        $order['order_id'] = (int)$order['order_id'];
    }

    echo json_encode([
        "success" => true,
        "message" => "Na-update ang order.",
        "order" => $order
    ]);

} catch (Throwable $e) {

    error_log(
        "update_order.php: " . $e->getMessage()
    );

    echo json_encode([
        "success" => false,
        "message" => "Hindi ma-update ang order."
    ]);

} finally {

    if (isset($conn)) {
        $conn->close();
    }

}
?>
