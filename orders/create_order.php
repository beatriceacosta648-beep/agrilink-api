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
 
