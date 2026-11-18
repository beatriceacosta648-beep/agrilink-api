<?php
include 'group_helpers.php';
include '../db_connect.php';

// All filters are optional (query string or JSON body):
//   barangay, city_municipality   pools for one place (the Barangay Pool banner)
//   status                        open (default), unlocked, completed, cancelled, expired, or all
//   item_id                       only pools that include this item
//   group_id                      just one pool (works as a "view" request)
//   user_id                       adds my_quantity: how many units this user has committed
$in = input();

$userId   = (int)($in['user_id'] ?? 0);
$groupId  = (int)($in['group_id'] ?? 0);
$itemId   = (int)($in['item_id'] ?? 0);
$barangay = trim($in['barangay'] ?? '');
$city     = trim($in['city_municipality'] ?? '');
$status   = trim($in['status'] ?? GROUP_OPEN);
$joinedOnly = ($userId > 0 && !empty($in['joined_only']));

if ($status !== 'all' && !in_array($status, group_statuses(), true)) {
    fail("Hindi tama ang status.");
}

try {
    expire_old_groups($conn);

    $types  = '';
    $params = [];

    // This part of the SELECT comes first, so its ? is bound first
    $mine = "0 AS my_quantity";
    if ($userId > 0) {
        $mine = "(SELECT COALESCE(SUM(gm.quantity_commited), 0)
                  FROM group_members gm
                  WHERE gm.group_id = g.group_id AND gm.user_id = ?) AS my_quantity";
        $types .= 'i';
        $params[] = $userId;
    }

    $sql = "SELECT g.group_id, g.group_name, g.created_by, g.agent_user_id,
                   g.target_quantity, g.current_quantity, g.status, g.expires_at,
                   g.barangay, g.city_municipality,
                   GREATEST(TIMESTAMPDIFF(SECOND, NOW(), g.expires_at), 0) AS seconds_left,
                   (SELECT COUNT(DISTINCT gm2.user_id)
                    FROM group_members gm2
                    WHERE gm2.group_id = g.group_id) AS member_count,
                   $mine
            FROM group_buys g
            WHERE 1 = 1";

    if ($status !== 'all') {
        $sql .= " AND g.status = ?";
        $types .= 's';
        $params[] = $status;
    }
    if ($groupId > 0) {
        $sql .= " AND g.group_id = ?";
        $types .= 'i';
        $params[] = $groupId;
    }
    if ($barangay !== '') {
        $sql .= " AND g.barangay = ?";
        $types .= 's';
        $params[] = $barangay;
    }
    if ($city !== '') {
        $sql .= " AND g.city_municipality = ?";
        $types .= 's';
        $params[] = $city;
    }
    if ($itemId > 0) {
        $sql .= " AND EXISTS (SELECT 1 FROM group_buy_items gi
                              WHERE gi.group_id = g.group_id AND gi.item_id = ?)";
        $types .= 'i';
        $params[] = $itemId;
    }
    $sql .= " ORDER BY g.expires_at ASC, g.group_id DESC";

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $row['group_id']         = (int)$row['group_id'];
        $row['created_by']       = (int)$row['created_by'];
        $row['agent_user_id']    = ($row['agent_user_id'] === null) ? null : (int)$row['agent_user_id'];
        $row['target_quantity']  = (int)$row['target_quantity'];
        $row['current_quantity'] = (int)$row['current_quantity'];
        $row['seconds_left']     = (int)$row['seconds_left'];
        $row['member_count']     = (int)$row['member_count'];
        $row['my_quantity']      = (int)$row['my_quantity'];

        $row['progress_percent'] = ($row['target_quantity'] > 0)
            ? min(100, (int)round($row['current_quantity'] / $row['target_quantity'] * 100))
            : 0;
        $row['remaining_quantity'] = max($row['target_quantity'] - $row['current_quantity'], 0);
        $row['items'] = [];

        $groups[$row['group_id']] = $row;
    }
    $stmt->close();

    // The items (with names and prices) of every pool found
    if (count($groups) > 0) {
        $idList = implode(',', array_keys($groups));   // whole numbers only, safe to put in the query
        $res = $conn->query(
            "SELECT gi.group_id, gi.group_buy_items_id, gi.item_id, gi.pool_price,
                    i.item_name, i.unit_price, i.image_url
             FROM group_buy_items gi
             JOIN items i ON i.item_id = gi.item_id
             WHERE gi.group_id IN ($idList)
             ORDER BY gi.group_buy_items_id"
        );
        while ($it = $res->fetch_assoc()) {
            $groups[(int)$it['group_id']]['items'][] = [
                "group_buy_items_id" => (int)$it['group_buy_items_id'],
                "item_id"            => (int)$it['item_id'],
                "item_name"          => $it['item_name'],
                "unit_price"         => (float)$it['unit_price'],
                "pool_price"         => (float)$it['pool_price'],
                "image_url"          => $it['image_url']
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "count"   => count($groups),
        "groups"  => array_values($groups)
    ]);

} catch (Throwable $e) {
    error_log("get_group_buys.php: " . $e->getMessage());
    fail("Hindi makuha ang mga group buy.");
}
