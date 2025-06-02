<?php
// الاتصال بقاعدة البيانات
$host = "localhost";
$db = "rokood_db";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("خطأ في الاتصال: " . $conn->connect_error);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM purchases WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: purchases.php");
    exit;
} else {
    echo "رقم الفاتورة غير صحيح!";
}
?>