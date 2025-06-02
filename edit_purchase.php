<?php
// الاتصال بقاعدة البيانات
$host = "localhost";
$db = "rokood_db";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("خطأ في الاتصال: " . $conn->connect_error);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = false;
$error = "";

if ($id > 0) {
    // تحديث الفاتورة
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
        $purchase_date = $_POST['purchase_date'];
        $supplier_name = $_POST['supplier_name'];
        $egg_type = $_POST['egg_type'];
        $egg_size = $_POST['egg_size'];
        $quantity = intval($_POST['quantity']);
        $unit_price = floatval($_POST['unit_price']);
        $total_amount = $quantity * $unit_price;
        $vat_amount = $total_amount * 0.15;
        $total_with_vat = $total_amount + $vat_amount;
        $stmt = $conn->prepare("UPDATE purchases SET purchase_date=?, supplier_name=?, egg_type=?, egg_size=?, quantity=?, unit_price=?, total_amount=?, vat_amount=?, total_with_vat=? WHERE id=?");
        $stmt->bind_param("ssssiddddi", $purchase_date, $supplier_name, $egg_type, $egg_size, $quantity, $unit_price, $total_amount, $vat_amount, $total_with_vat, $id);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "حدث خطأ أثناء التعديل: " . $stmt->error;
        }
        $stmt->close();
    }

    // جلب بيانات الفاتورة
    $stmt = $conn->prepare("SELECT * FROM purchases WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $purchase = $result->fetch_assoc();
    $stmt->close();

    if (!$purchase) die("الفاتورة غير موجودة!");
} else {
    die("معرّف الفاتورة غير صحيح!");
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تعديل فاتورة شراء</title>
    <style>
        body { font-family: 'Cairo', Tahoma, Arial, sans-serif; direction: rtl; background: #f5f7fa; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow:0 2px 12px #e0e0e0;}
        label { font-weight: bold; display: block; margin-bottom: 6px; color: #304ffe;}
        input, select { width: 100%; padding: 7px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd;}
        button { background: #304ffe; color: #fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold; margin-left: 7px;}
        .msg { margin-bottom:12px; padding:7px 12px; border-radius:7px;}
        .success { background:#d4edda; color:#26734d;}
        .error { background:#f8d7da; color:#b71c1c;}
        a, .back-btn { color:#304ffe; text-decoration:underline; padding:10px 20px; border-radius:6px; border:none; background:#eee; font-weight:bold; cursor:pointer; display:inline-block;}
        .back-btn:hover { background: #d7e2ff; }
        .action-btns { margin-top: 10px; }
    </style>
    <script>
        function goBack() {
            window.history.back();
        }
    </script>
</head>
<body>
<div class="container">
    <h2>تعديل فاتورة الشراء</h2>
    <?php if($success): ?>
        <div class="msg success">تم حفظ التعديلات بنجاح. <a href="purchases.php">عودة</a></div>
    <?php elseif($error): ?>
        <div class="msg error"><?= $error ?></div>
    <?php endif; ?>
    <form method="post">
        <label>التاريخ</label>
        <input type="date" name="purchase_date" value="<?= htmlspecialchars($purchase['purchase_date']) ?>" required>
        <label>اسم المورد</label>
        <input type="text" name="supplier_name" value="<?= htmlspecialchars($purchase['supplier_name']) ?>" required>
        <label>نوع البيض</label>
        <select name="egg_type" required>
            <option value="بلدي" <?= $purchase['egg_type']=='بلدي'?'selected':'' ?>>بلدي</option>
            <option value="أحمر" <?= $purchase['egg_type']=='أحمر'?'selected':'' ?>>أحمر</option>
            <option value="أبيض" <?= $purchase['egg_type']=='أبيض'?'selected':'' ?>>أبيض</option>
        </select>
        <label>الحجم</label>
        <select name="egg_size" required>
            <option value="صغير" <?= $purchase['egg_size']=='صغير'?'selected':'' ?>>صغير</option>
            <option value="متوسط" <?= $purchase['egg_size']=='متوسط'?'selected':'' ?>>متوسط</option>
            <option value="كبير" <?= $purchase['egg_size']=='كبير'?'selected':'' ?>>كبير</option>
            <option value="جامبو" <?= $purchase['egg_size']=='جامبو'?'selected':'' ?>>جامبو</option>
        </select>
        <label>الكمية</label>
        <input type="number" name="quantity" value="<?= htmlspecialchars($purchase['quantity']) ?>" required>
        <label>السعر للوحدة</label>
        <input type="number" name="unit_price" step="0.01" value="<?= htmlspecialchars($purchase['unit_price']) ?>" required>
        <div class="action-btns">
            <button type="submit" name="update">حفظ التعديلات</button>
            <button type="button" class="back-btn" onclick="goBack()">عودة للصفحة السابقة</button>
            <a href="purchases.php" class="back-btn">قائمة المشتريات</a>
        </div>
    </form>
</div>
</body>
</html>