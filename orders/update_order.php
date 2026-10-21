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
