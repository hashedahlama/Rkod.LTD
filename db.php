<?php
$host = "localhost";
$db   = "rokood_db";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("خطأ في الاتصال: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>