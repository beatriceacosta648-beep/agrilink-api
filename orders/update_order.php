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

$orderId       = (int)($in['order_id'] ?? 0);
$orderStatus   = trim($in['order_status'] ?? '');
$paymentStatus = trim($in['payment_status'] ?? '');
$estDelivery   = trim($in['estimated_delivery_date'] ?? '');

if ($orderId <= 0) {
    fail("Kulang ang impormasyon.");
}

// Allowed values (cancelling goes through delete_order.php so stock gets restored)
$allowedOrder = ['pending', 'confirmed', 'out_for_delivery', 'delivered'];
$allowedPay   = ['unpaid', 'paid', 'refunded'];

if ($orderStatus === 'cancelled') {
    fail("Gamitin ang delete_order.php para i-cancel ang order.");
}
if ($orderStatus !== '' && !in_array($orderStatus, $allowedOrder, true)) {
    fail("Hindi tama ang order_status.");
}
if ($paymentStatus !== '' && !in_array($paymentStatus, $allowedPay, true)) {
    fail("Hindi tama ang payment_status.");
}
if ($estDelivery !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $estDelivery);
    if (!$d || $d->format('Y-m-d') !== $estDelivery) {
        fail("Hindi tama ang petsa. Gamitin ang format na YYYY-MM-DD.");
    }
}

// Build the UPDATE from only the fields that were sent
$sets  = [];
$types = '';
$vals  = [];

if ($orderStatus !== '') {
    $sets[] = "order_status = ?";
    $types .= 's';
    $vals[] = $orderStatus;
}
if ($paymentStatus !== '') {
    $sets[] = "payment_status = ?";
    $types .= 's';
    $vals[] = $paymentStatus;
    // keep date_paid in step with payment_status
    if ($paymentStatus === 'paid') {
        $sets[] = "date_paid = NOW()";
    } elseif ($paymentStatus === 'unpaid') {
        $sets[] = "date_paid = NULL";
    }
}
if ($estDelivery !== '') {
    $sets[] = "estimated_delivery_date = ?";
    $types .= 's';
    $vals[] = $estDelivery;
}

if (count($sets) === 0) {
    fail("Walang babaguhin.");
}

try {
    // The order must exist and must not already be cancelled
    $stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        fail("Hindi nahanap ang order.");
    }
    if ($current['order_status'] === 'cancelled') {
        fail("Kanselado na ang order, hindi na ito maaaring baguhin.");
    }

    $sql   = "UPDATE orders SET " . implode(', ', $sets) . " WHERE order_id = ?";
    $types .= 'i';
    $vals[] = $orderId;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();

    // Return the order's current state
    $stmt = $conn->prepare(
        "SELECT order_id, order_status, payment_status, date_paid,
                estimated_delivery_date, updated_at
         FROM orders WHERE order_id = ?"
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "success" => true,
        "message" => "Na-update ang order.",
        "order"   => $order
    ]);
} catch (Throwable $e) {
    error_log("update_order.php: " . $e->getMessage());
    fail("Hindi ma-update ang order.");
}
