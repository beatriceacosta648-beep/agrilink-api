<?php
header('Content-Type: application/json');
 
// Make mysqli throw exceptions so the transaction can roll back on any failure
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
include 'db_connect.php';
 
function fail($message) {
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail("POST lang ang tinatanggap.");
}
 
// ---------- 1. Read and validate the request ----------
// Send EITHER cart_id (checkout from the cart) OR an items list (direct order).
$data = json_decode(file_get_contents("php://input"), true);
 
$userId          = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$addressId       = isset($data['address_id']) ? (int)$data['address_id'] : 0;
$paymentMethodId = isset($data['payment_method_id']) ? (int)$data['payment_method_id'] : 0;
$cartId          = isset($data['cart_id']) ? (int)$data['cart_id'] : 0;
$itemsInput      = $data['items'] ?? [];
 
if ($userId <= 0 || $addressId <= 0 || $paymentMethodId <= 0) {
    fail("Kulang ang impormasyon.");
}
if ($cartId <= 0 && (!is_array($itemsInput) || count($itemsInput) === 0)) {
    fail("Kulang ang impormasyon.");
}
 
// Direct order: merge duplicate item_ids (e.g. item 5 listed twice) into one line
$wanted = [];
if ($cartId <= 0) {
    foreach ($itemsInput as $row) {
        $itemId = isset($row['item_id']) ? (int)$row['item_id'] : 0;
        $qty    = isset($row['quantity']) ? (int)$row['quantity'] : 0;
        if ($itemId <= 0 || $qty <= 0) {
            fail("Hindi tama ang item o quantity.");
        }
        $wanted[$itemId] = ($wanted[$itemId] ?? 0) + $qty;
    }
}
 
// ---------- 2. Do everything in one transaction ----------
try {
    $conn->begin_transaction();
 
    // Checkout from a cart: it must be this user's ACTIVE cart; its items become the order
    $groupId = null;
    if ($cartId > 0) {
        $stmt = $conn->prepare(
            "SELECT group_id FROM cart
             WHERE cart_id = ? AND user_id = ? AND cart_status = 'active' FOR UPDATE"
        );
        $stmt->bind_param("ii", $cartId, $userId);
        $stmt->execute();
        $cart = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$cart) {
            throw new RuntimeException("Hindi nahanap ang aktibong cart.");
        }
        $groupId = $cart['group_id'] !== null ? (int)$cart['group_id'] : null;
 
        $stmt = $conn->prepare("SELECT item_id, quantity FROM cart_items WHERE cart_id = ?");
        $stmt->bind_param("i", $cartId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rowItemId = (int)$row['item_id'];
            $wanted[$rowItemId] = ($wanted[$rowItemId] ?? 0) + (int)$row['quantity'];
        }
        $stmt->close();
 
        if (count($wanted) === 0) {
            throw new RuntimeException("Walang laman ang cart.");
        }
    }
    // Always lock items in the same order to avoid deadlocks between simultaneous orders
    ksort($wanted);
 
    // The address must belong to this user
    $stmt = $conn->prepare("SELECT address_id FROM address WHERE address_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addressId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new RuntimeException("Hindi nahanap ang address para sa user na ito.");
    }
    $stmt->close();
 
    // The payment method must exist
    $stmt = $conn->prepare("SELECT payment_methods_id FROM payment_methods WHERE payment_methods_id = ?");
    $stmt->bind_param("i", $paymentMethodId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new RuntimeException("Hindi tama ang payment method.");
    }
    $stmt->close();
 
    // Look up every item (price and stock come from the database, never from the app)
    $itemStmt = $conn->prepare(
        "SELECT item_name, unit_price, stock_quantity FROM items WHERE item_id = ? FOR UPDATE"
    );
    $lines = [];
    $total = 0.0;
 
    foreach ($wanted as $itemId => $qty) {
        $itemStmt->bind_param("i", $itemId);
        $itemStmt->execute();
        $item = $itemStmt->get_result()->fetch_assoc();
 
        if (!$item) {
            throw new RuntimeException("Hindi mahanap ang item #$itemId.");
        }
        if ((int)$item['stock_quantity'] < $qty) {
            throw new RuntimeException(
                "Kulang ang stock ng \"{$item['item_name']}\" (natitira: {$item['stock_quantity']})."
            );
        }
 
        $price = (float)$item['unit_price'];
        $total += $price * $qty;
        $lines[] = [
            "item_id"    => $itemId,
            "item_name"  => $item['item_name'],
            "quantity"   => $qty,
            "unit_price" => $price
        ];
    }
    $itemStmt->close();
    $total = round($total, 2);
 
    // Insert the order (group_id comes from the cart, or stays empty for direct orders)
    $orderStatus   = 'pending';
    $paymentStatus = 'unpaid';
    $estDelivery   = date('Y-m-d', strtotime('+3 days'));
 
    $stmt = $conn->prepare(
        "INSERT INTO orders
            (user_id, address_id, group_id, total_amount, order_status, payment_method_id,
             estimated_delivery_date, payment_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iiidsiss",
        $userId, $addressId, $groupId, $total, $orderStatus, $paymentMethodId, $estDelivery, $paymentStatus
    );
    $stmt->execute();
    $orderId = $conn->insert_id;
    $stmt->close();
 
    // Insert each order item (saving the price at the time of purchase) and subtract stock
    $oiStmt    = $conn->prepare(
        "INSERT INTO order_items (order_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)"
    );
    $stockStmt = $conn->prepare(
        "UPDATE items SET stock_quantity = stock_quantity - ? WHERE item_id = ?"
    );
 
    foreach ($lines as $line) {
        $lineItemId = $line['item_id'];
        $lineQty    = $line['quantity'];
        $linePrice  = $line['unit_price'];
 
        $oiStmt->bind_param("iiid", $orderId, $lineItemId, $lineQty, $linePrice);
        $oiStmt->execute();
 
        $stockStmt->bind_param("ii", $lineQty, $lineItemId);
        $stockStmt->execute();
    }
    $oiStmt->close();
    $stockStmt->close();
 
    // The cart has become an order: move it to History and link it to the order
    if ($cartId > 0) {
        $stmt = $conn->prepare(
            "UPDATE cart SET cart_status = 'checked_out', order_id = ?, updated_at = NOW()
             WHERE cart_id = ?"
        );
        $stmt->bind_param("ii", $orderId, $cartId);
        $stmt->execute();
        $stmt->close();
    }
 
    $conn->commit();
 
    echo json_encode([
        "success"                 => true,
        "message"                 => "Nailagay ang order.",
        "order_id"                => $orderId,
        "cart_id"                 => $cartId > 0 ? $cartId : null,
        "total_amount"            => $total,
        "order_status"            => $orderStatus,
        "payment_status"          => $paymentStatus,
        "estimated_delivery_date" => $estDelivery,
        "items"                   => $lines
    ]);
 
} catch (RuntimeException $e) {
    // Expected problems (bad address, not enough stock, ...): safe to show the message
    $conn->rollback();
    fail($e->getMessage());
} catch (Throwable $e) {
    // Unexpected database/PHP errors: log the details, show a generic message
    $conn->rollback();
    error_log("create_order.php: " . $e->getMessage());
    fail("Hindi nagawa ang order.");
}
 
