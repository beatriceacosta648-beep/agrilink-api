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

$userId = (int)($in['user_id'] ?? 0);
$cartId = (int)($in['cart_id'] ?? 0);
$itemId = (int)($in['item_id'] ?? 0);
$qty    = (int)($in['quantity'] ?? 0);   // the NEW quantity (not an amount to add)

if ($userId <= 0 || $cartId <= 0 || $itemId <= 0) {
    fail("Kulang ang impormasyon.");
}
if ($qty <= 0) {
    fail("Dapat hindi bababa sa 1 ang quantity. Gamitin ang delete_cart_item.php para alisin ang item.");
}

try {
    // Only the owner's active cart can be changed
    $stmt = $conn->prepare(
        "SELECT cart_id FROM cart WHERE cart_id = ? AND user_id = ? AND cart_status = 'active'"
    );
    $stmt->bind_param("ii", $cartId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        fail("Hindi nahanap ang aktibong cart.");
    }
    $stmt->close();

    // The item must be in this cart
    $stmt = $conn->prepare(
        "SELECT ci.cart_items_id, i.item_name, i.unit_price, i.stock_quantity
         FROM cart_items ci
         JOIN items i ON i.item_id = ci.item_id
         WHERE ci.cart_id = ? AND ci.item_id = ?"
    );
    $stmt->bind_param("ii", $cartId, $itemId);
    $stmt->execute();
    $line = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$line) {
        fail("Wala ang item sa cart.");
    }
    if ($qty > (int)$line['stock_quantity']) {
        fail("Kulang ang stock ng \"{$line['item_name']}\" (natitira: {$line['stock_quantity']}).");
    }

    $price  = (float)$line['unit_price'];
    $lineId = (int)$line['cart_items_id'];

    $stmt = $conn->prepare("UPDATE cart_items SET quantity = ?, unit_price = ? WHERE cart_items_id = ?");
    $stmt->bind_param("idi", $qty, $price, $lineId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE cart SET updated_at = NOW() WHERE cart_id = ?");
    $stmt->bind_param("i", $cartId);
    $stmt->execute();

    echo json_encode([
        "success"    => true,
        "message"    => "Na-update ang quantity.",
        "cart_id"    => $cartId,
        "item_id"    => $itemId,
        "quantity"   => $qty,
        "unit_price" => $price,
        "subtotal"   => round($price * $qty, 2)
    ]);
} catch (Throwable $e) {
    error_log("update_cart_item.php: " . $e->getMessage());
    fail("Hindi ma-update ang cart.");
}
