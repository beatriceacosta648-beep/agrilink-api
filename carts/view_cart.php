<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include '../db_connect.php';

function fail($message) {
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

// Query string (?user_id=1) or JSON body.
// Send cart_id to view a specific cart (e.g. one from History); otherwise the active cart is shown.
$body = json_decode(file_get_contents("php://input"), true);
$in   = array_merge($_GET, is_array($body) ? $body : []);

$userId  = (int)($in['user_id'] ?? 0);
$cartId  = (int)($in['cart_id'] ?? 0);
$groupId = ((int)($in['group_id'] ?? 0) > 0) ? (int)$in['group_id'] : null;

if ($userId <= 0) {
    fail("Kulang ang impormasyon.");
}

try {
    if ($cartId > 0) {
        $stmt = $conn->prepare(
            "SELECT cart_id, group_id, cart_status, order_id, created_at, updated_at
             FROM cart WHERE cart_id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $cartId, $userId);
    } else {
        $stmt = $conn->prepare(
            "SELECT cart_id, group_id, cart_status, order_id, created_at, updated_at
             FROM cart
             WHERE user_id = ? AND cart_status = 'active' AND group_id <=> ?
             LIMIT 1"
        );
        $stmt->bind_param("ii", $userId, $groupId);
    }
    $stmt->execute();
    $cart = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cart) {
        if ($cartId > 0) {
            fail("Hindi nahanap ang cart.");
        }
        // No active cart yet is not an error: it's just an empty cart
        echo json_encode(["success" => true, "cart" => null, "items" => [], "total_amount" => 0]);
        exit;
    }

    // Active carts show today's price (what checkout will charge);
    // finished carts show the price saved in the cart.
    $priceCol = ($cart['cart_status'] === 'active') ? 'i.unit_price' : 'ci.unit_price';

    $stmt = $conn->prepare(
        "SELECT ci.cart_items_id, ci.item_id, i.item_name, ci.quantity,
                $priceCol AS unit_price, i.stock_quantity,
                (ci.quantity * $priceCol) AS subtotal
         FROM cart_items ci
         JOIN items i ON i.item_id = ci.item_id
         WHERE ci.cart_id = ?
         ORDER BY ci.cart_items_id"
    );
    $theCartId = (int)$cart['cart_id'];
    $stmt->bind_param("i", $theCartId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    $total = 0.0;
    while ($row = $result->fetch_assoc()) {
        $total += (float)$row['subtotal'];
        $items[] = $row;
    }

    echo json_encode([
        "success"      => true,
        "cart"         => $cart,
        "items"        => $items,
        "item_count"   => count($items),
        "total_amount" => round($total, 2)
    ]);
} catch (Throwable $e) {
    error_log("view_cart.php: " . $e->getMessage());
    fail("Hindi makuha ang cart.");
}
