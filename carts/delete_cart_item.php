<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include '../db_connect.php';

function fail($message) {
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

// Send item_id to remove one item. Leave item_id out to clear the whole cart.
$body = json_decode(file_get_contents("php://input"), true);
$in   = array_merge($_GET, is_array($body) ? $body : []);

$userId = (int)($in['user_id'] ?? 0);
$cartId = (int)($in['cart_id'] ?? 0);
$itemId = (int)($in['item_id'] ?? 0);

if ($userId <= 0 || $cartId <= 0) {
    fail("Kulang ang impormasyon.");
}

try {
    // Only the owner's active cart can be changed (finished carts are history)
    $stmt = $conn->prepare(
        "SELECT cart_id FROM cart WHERE cart_id = ? AND user_id = ? AND cart_status = 'active'"
    );
    $stmt->bind_param("ii", $cartId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        fail("Hindi nahanap ang aktibong cart.");
    }
    $stmt->close();

    if ($itemId > 0) {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND item_id = ?");
        $stmt->bind_param("ii", $cartId, $itemId);
    } else {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
        $stmt->bind_param("i", $cartId);
    }
    $stmt->execute();
    $removed = $stmt->affected_rows;
    $stmt->close();

    if ($itemId > 0 && $removed === 0) {
        fail("Wala ang item sa cart.");
    }

    $stmt = $conn->prepare("UPDATE cart SET updated_at = NOW() WHERE cart_id = ?");
    $stmt->bind_param("i", $cartId);
    $stmt->execute();

    echo json_encode([
        "success"       => true,
        "message"       => $itemId > 0 ? "Naalis ang item sa cart." : "Nalinis ang cart.",
        "cart_id"       => $cartId,
        "removed_count" => $removed
    ]);
} catch (Throwable $e) {
    error_log("delete_cart_item.php: " . $e->getMessage());
    fail("Hindi maalis sa cart.");
}
