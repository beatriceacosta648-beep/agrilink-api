<?php
$host = getenv("DB_HOST");
$port = getenv("DB_PORT");
$db_user = getenv("DB_USER");
$db_pass = getenv("DB_PASS");
$db_name = getenv("DB_NAME");

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, "/var/www/html/ca.pem", NULL, NULL);
$conn->real_connect($host, $db_user, $db_pass, $db_name, (int)$port, NULL, MYSQLI_CLIENT_SSL);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}
?>
