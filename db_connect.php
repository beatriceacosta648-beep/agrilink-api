<?php
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "agrilink_db";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed"]));
}
?>
