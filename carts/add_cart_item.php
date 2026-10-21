<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include 'db_connect.php';

function fail($message) {
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);
$in   = array_merge($_GET, is_array($body) ? $body : []);

$userId  = (int)($in['user_id'] ?? 0);
$itemId  = (int)($in['item_id'] ?? 0);
$qty     = isset($in['quantity']) ? (int)$in['quantity'] : 1;
$groupId = ((int)($in['group_id'] ?? 0) > 0) ? (int)$in['group_id'] : null;   // optional (group buys)

if ($userId <= 0 || $itemId <= 0 || $qty <= 0) {
    fail("Kulang o hindi tama ang impormasyon.");
}

try {
    $conn->begin_transaction();

    // The item must exist
    $stmt = $conn->prepare("SELECT item_name, unit_price, stock_quantity FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$item) {
        throw new RuntimeException("Hindi mahanap ang item.");
    }

    // Find the user's active cart, or start a new one
    $stmt = $conn->prepare(
        "SELECT cart_id FROM cart
         WHERE user_id = ? AND cart_status = 'active' AND group_id <=> ?
         LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param("ii", $userId, $groupId);
    $stmt->execute();
    $cart = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cart) {
        $cartId = (int)$cart['cart_id'];
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cart (user_id, group_id, cart_status, created_at, updated_at)
             VALUES (?, ?, 'active', NOW(), NOW())"
        );
        $stmt->bind_param("ii", $userId, $groupId);
        $stmt->execute();
        $cartId = $conn->insert_id;
        $stmt->close();
    }

    // Is the item already in the cart?
    $stmt = $conn->prepare(
        "SELECT cart_items_id, quantity FROM cart_items WHERE cart_id = ? AND item_id = ? FOR UPDATE"
    );
    $stmt->bind_param("ii", $cartId, $itemId);
    $stmt->execute();
    $line = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $newQty = $qty + ($line ? (int)$line['quantity'] : 0);
    if ($newQty > (int)$item['stock_quantity']) {
        throw new RuntimeException(
            "Kulang ang stock ng \"{$item['item_name']}\" (natitira: {$item['stock_quantity']})."
        );
    }
    $price = (float)$item['unit_price'];

    if ($line) {
        $lineId = (int)$line['cart_items_id'];
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ?, unit_price = ? WHERE cart_items_id = ?");
        $stmt->bind_param("idi", $newQty, $price, $lineId);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cart_items (cart_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("iiid", $cartId, $itemId, $newQty, $price);
    }
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE cart SET updated_at = NOW() WHERE cart_id = ?");
    $stmt->bind_param("i", $cartId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        "success"    => true,
        "message"    => "Naidagdag sa cart.",
        "cart_id"    => $cartId,
        "item_id"    => $itemId,
        "item_name"  => $item['item_name'],
        "quantity"   => $newQty,
        "unit_price" => $price
    ]);

} catch (RuntimeException $e) {
    $conn->rollback();
    fail($e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    error_log("add_cart_item.php: " . $e->getMessage());
    fail("Hindi maidagdag sa cart.");
}
