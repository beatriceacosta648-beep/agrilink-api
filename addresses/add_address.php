<?php
header('Content-Type: application/json');

include '../db_connect.php';

\$data = json_decode(file_get_contents("php://input"), true);

\$userId   = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$region   = trim($data['region'] ?? '');
$province = trim($data['province'] ?? '');
$city     = trim($data['city'] ?? '');
$barangay = trim($data['barangay'] ?? '');
$street   = trim($data['street'] ?? '');

if ($userId <= 0 \vert{}\vert{}$region === '' || $province === '' \vert{}\vert{}$city === '' || $barangay === '' \vert{}\vert{}$street === '') {
    echo json_encode(["success" => false, "message" => "Kulang ang impormasyon."]);
    exit;
}

// Ensure the table name ('addresses' or 'address') matches your database exactly
\$sql = "INSERT INTO address (user_id, `Region`, `Province`, `City/Municipality`, `Barangay`, `Home Address`)
        VALUES (?, ?, ?, ?, ?, ?)";

try {
    $stmt =$conn->prepare($sql);$stmt->bind_param("isssss", $userId,$region, $province,$city, $barangay,$street);

    if (\$stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Na-save ang address.",
            "address_id" => \$conn->insert_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Hindi na-save ang address."]);
    }
    
    \$stmt->close();
} catch (mysqli_sql_exception \$e) {
    echo json_encode([
        "success" => false, 
        "message" => "Database error: " . \$e->getMessage()
    ]);
}

\$conn->close();
?>
