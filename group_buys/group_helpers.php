<?php
// Shared by every file in this folder. Each file starts with:
//     include 'group_helpers.php';
//     include '../db_connect.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---- The words stored in group_buys.status ----
// If your column uses different words, change them HERE and nowhere else.
const GROUP_OPEN      = 'open';        // still collecting units
const GROUP_UNLOCKED  = 'unlocked';    // target reached, the pool price applies
const GROUP_COMPLETED = 'completed';   // orders placed / delivered
const GROUP_CANCELLED = 'cancelled';
const GROUP_EXPIRED   = 'expired';     // the deadline passed before the target was reached

function group_statuses() {
    return [GROUP_OPEN, GROUP_UNLOCKED, GROUP_COMPLETED, GROUP_CANCELLED, GROUP_EXPIRED];
}

function fail($message, $extra = []) {
    echo json_encode(array_merge(["success" => false, "message" => $message], $extra));
    exit;
}

// Query string (?a=1) and JSON body merged together, like the carts files
function input() {
    $body = json_decode(file_get_contents("php://input"), true);
    return array_merge($_GET, is_array($body) ? $body : []);
}

function user_exists($conn, $userId) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found ? true : false;
}

// Render's free server has no scheduled jobs, so expiry is checked whenever
// pools are read: any 'open' pool past its expires_at becomes 'expired'.
// (Unlocked pools never expire: their target was already reached.)
function expire_old_groups($conn) {
    $open = GROUP_OPEN;
    $expired = GROUP_EXPIRED;
    $stmt = $conn->prepare("UPDATE group_buys SET status = ? WHERE status = ? AND expires_at < NOW()");
    $stmt->bind_param("ss", $expired, $open);
    $stmt->execute();
    $stmt->close();
}
