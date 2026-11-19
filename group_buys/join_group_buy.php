<?php
include 'group_helpers.php';
include '../db_connect.php';

// JSON body: { "user_id": 1, "group_id": 5, "quantity": 2 }
$in = input();

$userId  = (int)($in['user_id'] ?? 0);
$groupId = (int)($in['group_id'] ?? 0);
$qty     = (int)($in['quantity'] ?? 0);

if ($userId <= 0 || $groupId <= 0) {
    fail("Kulang ang impormasyon.");
}
if ($qty < 1) {
    fail("Ang dami ay dapat hindi bababa sa 1.");
}

try {
    expire_old_groups($conn);          // mark late pools as expired first
    $conn->begin_transaction();

    if (!user_exists($conn, $userId)) {
        throw new RuntimeException("Hindi mahanap ang user.");
    }

    // Lock the pool row so two people joining at once can't overshoot the target
    $stmt = $conn->prepare(
        "SELECT target_quantity, current_quantity, status
         FROM group_buys WHERE group_id = ? FOR UPDATE"
    );
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$g) {
        throw new RuntimeException("Hindi mahanap ang group buy.");
    }
    if ($g['status'] !== GROUP_OPEN) {
        throw new RuntimeException("Hindi na tumatanggap ng sasali ang pool na ito.");
    }
    $remaining = (int)$g['target_quantity'] - (int)$g['current_quantity'];
    if ($qty > $remaining) {
        throw new RuntimeException("Ang natitira na lang ay $remaining.");
    }

    // Already a member? add to their quantity. Otherwise create the row.
    $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $groupId, $userId);
    $stmt->execute();
    $already = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($already) {
        $stmt = $conn->prepare(
            "UPDATE group_members SET quantity_commited = quantity_commited + ?
             WHERE group_id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->bind_param("iii", $qty, $groupId, $userId);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO group_members (group_id, user_id, quantity_commited) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iii", $groupId, $userId, $qty);
    }
    $stmt->execute();
    $stmt->close();

    // Update the pool; unlock it if the target is reached
    $newQty    = (int)$g['current_quantity'] + $qty;
    $newStatus = ($newQty >= (int)$g['target_quantity']) ? GROUP_UNLOCKED : GROUP_OPEN;
    $stmt = $conn->prepare("UPDATE group_buys SET current_quantity = ?, status = ? WHERE group_id = ?");
    $stmt->bind_param("isi", $newQty, $newStatus, $groupId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(quantity_commited), 0) AS mine
         FROM group_members WHERE group_id = ? AND user_id = ?"
    );
    $stmt->bind_param("ii", $groupId, $userId);
    $stmt->execute();
    $mine = (int)$stmt->get_result()->fetch_assoc()['mine'];
    $stmt->close();

    $conn->commit();

    echo json_encode([
        "success"          => true,
        "message"          => ($newStatus === GROUP_UNLOCKED)
                                ? "Nasali ka na! Na-unlock na ang pool price."
                                : "Nasali ka na sa pool.",
        "group_id"         => $groupId,
        "my_quantity"      => $mine,
        "current_quantity" => $newQty,
        "status"           => $newStatus
    ]);

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("join_group_buy.php: " . $e->getMessage());
    fail("Hindi makasali sa group buy.", ["debug" => $e->getMessage()]);
} catch (RuntimeException $e) {
    $conn->rollback();
    fail($e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    error_log("join_group_buy.php: " . $e->getMessage());
    fail("Hindi makasali sa group buy.", ["debug" => $e->getMessage()]);
}
