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

// Accepts query string (?user_id=1) or JSON body
$body = json_decode(file_get_contents("php://input"), true);
$in = array_merge($_GET, is_array($body) ? $body : []);

$userId = (int)($in['user_id'] ?? 0);
$status = trim($in['order_status'] ?? '');

if ($userId <= 0) {
    fail("Kulang ang impormasyon.");
}

try {

    $sql = "
        SELECT
            o.order_id,
            o.total_amount,
            o.order_status,
            o.payment_status,
            o.estimated_delivery_date,
            o.created_at,
            COUNT(oi.order_items_id) AS item_count
        FROM orders o
        LEFT JOIN order_items oi
            ON oi.order_id = o.order_id
        WHERE o.user_id = ?
    ";

    if ($status !== '') {
        $sql .= " AND o.
