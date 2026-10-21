<?php
header('Content-Type: application/json');
include '../db_connect.php';

try {
    $sql = "SELECT user_id, full_name, email, role, phone_num, created_at FROM users";
    $result = $conn->query($sql);

    $users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['user_id'] = (int)$row['user_id'];
            $users[] = $row;
        }
        echo json_encode(["success" => true, "data" => $users]);
    } else {
        echo json_encode(["success" => false, "message" => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>
