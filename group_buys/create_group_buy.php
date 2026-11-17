<?php
include 'group_helpers.php';
include '../db_connect.php';

// JSON body example:
// {
//   "user_id": 1,
//   "group_name": "Brgy San Jose Growth Promoter Pool",
//   "barangay": "San Jose",
//   "city_municipality": "Iloilo City",
//   "target_quantity": 100,
//   "expires_in_days": 7,
//   "agent_user_id": 3,                       (optional)
//   "items": [ { "item_id": 1, "pool_price": 400 } ]
// }
$in = input();

$userId      = (int)($in['user_id'] ?? 0);
$groupName   = trim($in['group_name'] ?? '');
$barangay    = trim($in['barangay'] ?? '');
$city        = trim($in['city_municipality'] ?? '');
$target      = (int)($in['target_quantity'] ?? 0);
$days        = isset($in['expires_in_days']) ? (int)$in['expires_in_days'] : 7;
$agentUserId = ((int)($in['agent_user_id'] ?? 0) > 0) ? (int)$in['agent_user_id'] : null;
$itemsIn     = (isset($in['items']) && is_array($in['items'])) ? $in['items'] : [];

if ($userId <= 0 || $groupName === '' || $barangay === '' || $city === '') {
    fail("Kulang ang impormasyon.");
}
if (strlen($groupName) > 100) {
    fail("Masyadong mahaba ang pangalan ng pool.");
}
if ($target < 2) {
    fail("Ang target ay dapat hindi bababa sa 2.");
}
if ($days < 1 || $days > 30) {
    fail("Ang pool ay dapat tumagal ng 1 hanggang 30 araw.");
}
if (count($itemsIn) === 0) {
    fail("Pumili ng kahit isang item para sa pool.");
}

// item_id => pool_price
$items = [];
foreach ($itemsIn as $row) {
    if (!is_array($row)) {
        fail("Hindi tama ang listahan ng items.");
    }
    $itemId = (int)($row['item_id'] ?? 0);
    $price  = (float)($row['pool_price'] ?? 0);
    if ($itemId <= 0 || $price <= 0) {
        fail("Hindi tama ang item o presyo ng pool.");
    }
    if (isset($items[$itemId])) {
        fail("May item na dalawang beses na nailagay.");
    }
    $items[$itemId] = $price;
}

try {
    $conn->begin_transaction();

    if (!user_exists($conn, $userId)) {
        throw new RuntimeException("Hindi mahanap ang user.");
    }
    if ($agentUserId !== null && !user_exists($conn, $agentUserId)) {
        throw new RuntimeException("Hindi mahanap ang agent.");
    }

    // Every item must exist, and the pool price must be cheaper than the normal price
    $stmt = $conn->prepare("SELECT item_name, unit_price FROM items WHERE item_id = ?");
    foreach ($items as $itemId => $price) {
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        if (!$item) {
            throw new RuntimeException("Hindi mahanap ang item #$itemId.");
        }
        if ($price >= (float)$item['unit_price']) {
            throw new RuntimeException(
                "Dapat mas mura ang presyo ng pool kaysa sa karaniwang presyo ng \"{$item['item_name']}\"."
            );
        }
    }
    $stmt->close();

    // The pool itself
    $status = GROUP_OPEN;
    $stmt = $conn->prepare(
        "INSERT INTO group_buys
            (group_name, created_by, agent_user_id, target_quantity, current_quantity,
             status, expires_at, barangay, city_municipality)
         VALUES (?, ?, ?, ?, 0, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?)"
    );
    $stmt->bind_param("siiisiss", $groupName, $userId, $agentUserId, $target,
                      $status, $days, $barangay, $city);
    $stmt->execute();
    $groupId = $conn->insert_id;
    $stmt->close();

    // Its items and pool prices
    $stmt = $conn->prepare("INSERT INTO group_buy_items (group_id, item_id, pool_price) VALUES (?, ?, ?)");
    foreach ($items as $itemId => $price) {
        $stmt->bind_param("iid", $groupId, $itemId, $price);
        $stmt->execute();
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT expires_at FROM group_buys WHERE group_id = ?");
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $expiresAt = $stmt->get_result()->fetch_assoc()['expires_at'];
    $stmt->close();

    $conn->commit();

    $itemsOut = [];
    foreach ($items as $itemId => $price) {
        $itemsOut[] = ["item_id" => $itemId, "pool_price" => $price];
    }

    echo json_encode([
        "success"           => true,
        "message"           => "Nagawa ang group buy.",
        "group_id"          => $groupId,
        "group_name"        => $groupName,
        "status"            => $status,
        "target_quantity"   => $target,
        "current_quantity"  => 0,
        "expires_at"        => $expiresAt,
        "barangay"          => $barangay,
        "city_municipality" => $city,
        "items"             => $itemsOut
    ]);

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("create_group_buy.php: " . $e->getMessage());
    fail("Hindi magawa ang group buy.");
} catch (RuntimeException $e) {
    $conn->rollback();
    fail($e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    error_log("create_group_buy.php: " . $e->getMessage());
    fail("Hindi magawa ang group buy.");
}
