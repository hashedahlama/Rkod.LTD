<?php
// الاتصال بقاعدة البيانات
$conn = new mysqli('localhost', 'root', '', 'rokood_db');
$conn->set_charset("utf8");

// جلب بيانات الفاتورة المراد تعديلها
$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sale = null;

if ($sale_id > 0) {
    $q = $conn->query("SELECT * FROM sales WHERE id = $sale_id LIMIT 1");
    if ($q && $q->num_rows) {
        $sale = $q->fetch_assoc();
    }
}

// عند إرسال النموذج (فورم التعديل)
if (isset($_POST['save']) && $sale_id > 0) {
    // اجلب البيانات من النموذج
    $egg_type   = $conn->real_escape_string($_POST['egg_type']);
    $egg_size   = $conn->real_escape_string($_POST['egg_size']);
    $unit_price = floatval($_POST['unit_price']);
    $quantity   = intval($_POST['quantity']);
    $customer   = $conn->real_escape_string($_POST['customer']);
    $sale_date  = $conn->real_escape_string($_POST['sale_date']);

    $total_amount = $unit_price * $quantity;
    $vat_amount = $total_amount * 0.15;
    $total_with_vat = $total_amount + $vat_amount;

    // تحديث البيانات في الجدول
    $sql = "UPDATE sales SET 
        egg_type = '$egg_type',
        egg_size = '$egg_size',
        unit_price = $unit_price,
        quantity = $quantity,
        customer_name = '$customer',
        sale_date = '$sale_date',
        total_amount = $total_amount,
        vat_amount = $vat_amount,
        total_with_vat = $total_with_vat
        WHERE id = $sale_id
    ";
    if ($conn->query($sql)) {
        $msg = "تم التعديل بنجاح!";
        // جلب البيانات المعدلة من جديد
        $q = $conn->query("SELECT * FROM sales WHERE id = $sale_id LIMIT 1");
        if ($q && $q->num_rows) {
            $sale = $q->fetch_assoc();
        }
    } else {
        $msg = "خطأ في التعديل: " . $conn->error;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تعديل فاتورة مبيعات</title>
    <style>
        body { font-family: 'Cairo', Tahoma, Arial, sans-serif; direction: rtl; background: #f9f9f9; }
        .container { margin: 40px auto; width: 400px; background: #fff; padding: 30px; border-radius: 9px; box-shadow: 0 3px 18px #aaa2; }
        h2 { text-align: center; }
        label { display: block; margin-top: 16px; }
        input[type="text"], input[type="date"], input[type="number"] { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #aaa; }
        button { margin-top: 24px; width: 100%; padding: 10px; background: #347ab7; color: #fff; border: none; border-radius: 4px; font-size: 1.1em; }
        .msg { text-align: center; color: green; margin: 10px 0; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h2>تعديل فاتورة مبيعات</h2>
    <?php if (isset($msg)) { ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php } ?>
    <?php if ($sale): ?>
    <form method="post">
        <label>نوع البيض</label>
        <input type="text" name="egg_type" required value="<?= htmlspecialchars($sale['egg_type']) ?>">

        <label>حجم البيض</label>
        <input type="text" name="egg_size" required value="<?= htmlspecialchars($sale['egg_size']) ?>">

        <label>سعر الوحدة</label>
        <input type="number" name="unit_price" required step="0.01" value="<?= htmlspecialchars($sale['unit_price']) ?>">

        <label>الكمية</label>
        <input type="number" name="quantity" required value="<?= htmlspecialchars($sale['quantity']) ?>">

        <label>العميل</label>
        <input type="text" name="customer" required value="<?= htmlspecialchars($sale['customer_name']) ?>">

        <label>تاريخ البيع</label>
        <input type="date" name="sale_date" required value="<?= htmlspecialchars($sale['sale_date']) ?>">

        <button type="submit" name="save">حفظ التعديل</button>
    </form>
    <?php else: ?>
        <div class="error">لم يتم العثور على الفاتورة المطلوبة!</div>
    <?php endif; ?>
</div>
</body>
</html>