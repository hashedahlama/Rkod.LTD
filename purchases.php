<?php
$host = 'localhost';
$db = 'eggs_inventory';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8");

// جلب قائمة الموردين
$suppliers = [];
$res_sup = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");
while ($row = $res_sup->fetch_assoc()) {
    $suppliers[] = $row;
}

$egg_types = ['Small' , 'Medium', 'Large 1', 'Large 2' , 'Large 3' , 'Jambo' , 'صفارين'];
$egg_sizes = ['كرتونة', 'نصف كرتونة'];

$message = '';
$last_purchase_id = null;

// حذف عملية شراء
if (isset($_POST['delete_purchase']) && isset($_POST['purchase_id'])) {
    $delete_id = intval($_POST['purchase_id']);
    $conn->query("DELETE FROM purchases WHERE id = $delete_id");
    $message = "<div style='color:red;margin-top:20px;text-align:center;'>تم حذف عملية الشراء بنجاح.</div>";
}

// تعديل عملية شراء
$edit_purchase_data = null;
if (isset($_GET['edit_purchase_id'])) {
    $edit_id = intval($_GET['edit_purchase_id']);
    $res_edit = $conn->query("SELECT * FROM purchases WHERE id=$edit_id");
    if ($res_edit && $res_edit->num_rows) {
        $edit_purchase_data = $res_edit->fetch_assoc();
    }
}

// حفظ التعديل
if (isset($_POST['edit_purchase'])) {
    $edit_id    = intval($_POST['purchase_id']);
    $purchase_date  = $_POST['purchase_date'];
    $supplier_id = intval($_POST['supplier_id']);
    $egg_type   = $_POST['egg_type'];
    $egg_size   = $_POST['egg_size'];
    $quantity   = intval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $total_amount = $quantity * $unit_price;
    $vat_amount = $total_amount * 0.15;
    $total_with_vat = $total_amount + $vat_amount;

    $stmt = $conn->prepare("UPDATE purchases SET purchase_date=?, supplier_id=?, egg_type=?, egg_size=?, quantity=?, unit_price=?, total_amount=?, vat_amount=?, total_with_vat=? WHERE id=?");
    $stmt->bind_param('sissiddddi', $purchase_date, $supplier_id, $egg_type, $egg_size, $quantity, $unit_price, $total_amount, $vat_amount, $total_with_vat, $edit_id);
    if ($stmt->execute()) {
        $message = "<div style='color:green;margin-top:20px;text-align:center;'>تم تعديل عملية الشراء بنجاح.</div>";
    } else {
        $message = "<div style='color:red;'>حدث خطأ أثناء تعديل الشراء.</div>";
    }
}

// دالة توليد رقم فاتورة فريد للمشتريات
function generate_purchase_invoice_number($conn) {
    $year = date('Y');
    $res = $conn->query("SELECT invoice_number FROM purchases WHERE invoice_number LIKE 'PINV-$year-%' ORDER BY id DESC LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        $last_num = intval(substr($row['invoice_number'], -4));
        $new_num = $last_num + 1;
    } else {
        $new_num = 1;
    }
    return sprintf("PINV-%s-%04d", $year, $new_num);
}

// إضافة شراء جديد
if (isset($_POST['add_purchase'])) {
    $purchase_date  = $_POST['purchase_date'];
    $supplier_id = intval($_POST['supplier_id']);
    $egg_type   = $_POST['egg_type'];
    $egg_size   = $_POST['egg_size'];
    $quantity   = intval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $total_amount = $quantity * $unit_price;
    $vat_amount = $total_amount * 0.15;
    $total_with_vat = $total_amount + $vat_amount;

    // توليد رقم فاتورة تلقائي
    $invoice_number = generate_purchase_invoice_number($conn);

    $stmt = $conn->prepare("INSERT INTO purchases (purchase_date, supplier_id, invoice_number, egg_type, egg_size, quantity, unit_price, total_amount, vat_amount, total_with_vat)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sisssidddd', $purchase_date, $supplier_id, $invoice_number, $egg_type, $egg_size, $quantity, $unit_price, $total_amount, $vat_amount, $total_with_vat);
    if ($stmt->execute()) {
        $last_purchase_id = $conn->insert_id;
        $message = "<div style='color:green;margin-top:20px;text-align:center;'>تم حفظ عملية الشراء بنجاح.</div>";
    } else {
        $message = "<div style='color:red;'>حدث خطأ أثناء حفظ الشراء.</div>";
    }
}

// --- جلب المشتريات السابقة مع اسم المورد ---
$purchases = [];
$res2 = $conn->query("
    SELECT purchases.*, suppliers.name AS supplier_name
    FROM purchases
    LEFT JOIN suppliers ON purchases.supplier_id = suppliers.id
    ORDER BY purchases.purchase_date DESC, purchases.id DESC
");
while ($row = $res2->fetch_assoc()) {
    $purchases[] = $row;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>توريد</title>
    <style>
        body { font-family: 'Cairo', Tahoma, Arial, sans-serif; margin: 0; background: #f5f7fa; direction: rtl; }
        h2 { color: #304ffe; text-align: center; margin-bottom: 20px; }
        label { display: inline-block; width: 140px; font-size: 1.15em; margin-bottom: 7px; }
        input[type="text"], input[type="date"], input[type="number"], select {
            width: 85%; font-size: 1.15em; padding: 12px 10px; border: 1.5px solid #bdbdbd; border-radius: 8px;
            margin-bottom: 14px; transition: border 0.2s; background: #fff; box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="date"]:focus, input[type="number"]:focus, select:focus {
            border: 2px solid #304ffe; outline: none; background: #f1f7fe;
        }
        input[readonly] { background: #e6e6e6; color: #333; }
        .form-box { border: 1.5px solid #ddd; padding: 28px 28px 10px 28px; width: 460px; margin: 50px auto;
            background: #fff; border-radius: 16px; box-shadow: 0 4px 14px 0 rgba(44, 62, 80, 0.09); }
        .btn { padding: 10px 26px; border-radius: 7px; border: none; background: #304ffe; color: #fff; font-size: 1.12em; font-weight: bold; margin: 7px 0; cursor: pointer; transition: background 0.18s, color 0.18s;}
        .btn:hover { background: #1976d2; color: #fff; }
        .print-btn { background: #43a047; margin-top: 20px; }
        .print-btn:hover { background: #2e7d32; }
        .btn-action {
            padding: 4px 8px !important;
            font-size: 17px !important;
            margin: 1px 2px !important;
            min-width: 34px;
            min-height: 34px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-edit   { background: #ffb300; color:#222;}
        .btn-edit:hover {background:#ff9800;}
        .btn-delete { background:#e53935; color:#fff;}
        .btn-delete:hover {background:#b71c1c;}
        .btn-sandqabd {
            background: #0288d1; /* أزرق سماوي */
            color: #fff;
        }
        .btn-sandqabd:hover {
            background: #01579b; /* أزرق داكن */
        }
        .total-box { font-size: 1.18em; background: #e3eafd; border-radius: 7px; display: inline-block; padding: 7px 15px; margin-bottom: 14px; color: #304ffe; }
        .content { margin-right: 240px; padding: 20px; }
        .sidebar { position: fixed; right: 0; top: 0; width: 220px; height: 100%; background: linear-gradient(135deg, #304ffe 60%, #1976d2 100%); color: #fff; box-shadow: -2px 0 8px rgba(60,60,60,0.09); padding-top: 40px; z-index: 100;}
        .sidebar h2 { text-align: center; font-size: 1.2em; margin-bottom: 40px; }
        .sidebar a { display: block; padding: 15px 30px; color: #fff; text-decoration: none; font-size: 1.12em; margin-bottom: 8px; border-radius: 30px 0 0 30px; transition: background 0.2s; }
        .sidebar a.active, .sidebar a:hover { background: rgba(255,255,255,0.13);}
        .purchases-table { margin: 35px auto 0 auto; width: 98%; background: #fff; border-radius: 12px; box-shadow: 0 2px 14px 0 rgba(44,62,80,0.09); border-collapse: collapse; overflow: hidden; font-size: 1em; }
        .purchases-table th, .purchases-table td { border: 1px solid #e0e0e0; padding: 10px 12px; text-align: center; }
        .purchases-table th { background: #304ffe; color: #fff; font-size: 1.08em; }
        .purchases-table tr:nth-child(even) {background: #f8fafd;}
        .purchases-table tr:hover {background: #e3eafd;}
        @media (max-width:900px) {
            .sidebar { width: 100vw; height: 65px; position: fixed; top: 0; right: 0; display: flex; flex-direction: row; align-items: center; padding: 0 12px; z-index: 999;}
            .sidebar h2 { display: none;}
            .sidebar a { display: inline-block; padding: 8px 18px; margin-bottom: 0; border-radius: 30px; font-size: 1em; margin-right: 4px;}
            .content { margin-right: 0; padding: 20px 2vw; }
            .form-box { width: 99vw; max-width: 500px;}
            .purchases-table { font-size: 0.94em;}
            .btn-action { min-width: 28px; min-height: 28px; font-size: 15px !important;}
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script>
        function calcTotal() {
            var qty = parseFloat(document.getElementById('quantity').value) || 0;
            var price = parseFloat(document.getElementById('unit_price').value) || 0;
            var total = qty * price;
            var vat = total * 0.15;
            var totalWithVat = total + vat;

            document.getElementById('total_amount').innerText = total.toFixed(2) + " ريال";
            document.getElementById('vat_amount').innerText = vat.toFixed(2) + " ريال";
            document.getElementById('total_with_vat').innerText = totalWithVat.toFixed(2) + " ريال";
        }
    </script>
</head>
<body>
<div class="sidebar">
    <h2>لوحة التحكم</h2>
    <a href="index.php"><span class="material-icons" style="vertical-align:middle">home</span> الرئيسية</a>
    <a href="inventory_management.php"><span class="material-icons" style="vertical-align:middle">inventory_2</span> إدارة المخزون</a>
    <a href="suppliers.php"><span class="material-icons" style="vertical-align:middle">local_shipping</span> الموردين</a>
    <a href="customers.php"><span class="material-icons" style="vertical-align:middle">groups</span> العملاء</a>
    <a href="sales.php"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
    <a href="purchases.php" class="active"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> الـتـوريـد</a>
    <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
    <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
</div>
<div class="content">
    <h2>إدارة الـتـوريـد</h2>
    <div class="form-box">
        <!-- نموذج إضافة أو تعديل الشراء -->
        <?php if ($edit_purchase_data): ?>
        <form method="post">
            <input type="hidden" name="purchase_id" value="<?php echo $edit_purchase_data['id']; ?>">
            <label>التاريخ:</label>
            <input type="date" name="purchase_date" required value="<?php echo htmlspecialchars($edit_purchase_data['purchase_date']); ?>"><br>
            <label>اسم الـمـورد:</label>
            <select name="supplier_id" required>
                <option value="">اختر الـمـورد</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php if ($edit_purchase_data['supplier_id'] == $s['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($s['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select><br>
            <label>رقم الفاتورة:</label>
            <input type="text" name="invoice_number" readonly value="<?php echo htmlspecialchars($edit_purchase_data['invoice_number']); ?>"><br>
            <label>نوع البيض:</label>
            <select name="egg_type" required>
                <?php foreach ($egg_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>"
                        <?php if ($edit_purchase_data['egg_type'] == $type) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($type); ?>
                    </option>
                <?php endforeach; ?>
            </select><br>
            <label>الحجم:</label>
            <select name="egg_size" required>
                <?php foreach ($egg_sizes as $size): ?>
                    <option value="<?php echo htmlspecialchars($size); ?>"
                        <?php if ($edit_purchase_data['egg_size'] == $size) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($size); ?>
                    </option>
                <?php endforeach; ?>
            </select><br>
            <label>الكمية:</label>
            <input type="number" name="quantity" id="quantity" min="1" required value="<?php echo htmlspecialchars($edit_purchase_data['quantity']); ?>" oninput="calcTotal()"><br>
            <label>سعر الوحدة:</label>
            <input type="number" name="unit_price" id="unit_price" min="0.01" step="0.01" required value="<?php echo htmlspecialchars($edit_purchase_data['unit_price']); ?>" oninput="calcTotal()"><br>
            <div class="total-box">
                الإجمالي: <span id="total_amount"><?php echo number_format($edit_purchase_data['quantity'] * $edit_purchase_data['unit_price'],2); ?> ريال</span>
            </div>
            <div class="total-box">
                ضريبة القيمة المضافة (15%): <span id="vat_amount"><?php echo number_format(($edit_purchase_data['quantity'] * $edit_purchase_data['unit_price']) * 0.15,2); ?> ريال</span>
            </div>
            <div class="total-box">
                الإجمالي شامل الضريبة: <span id="total_with_vat"><?php echo number_format(($edit_purchase_data['quantity'] * $edit_purchase_data['unit_price']) * 1.15,2); ?> ريال</span>
            </div>
            <button class="btn btn-edit" type="submit" name="edit_purchase">تعديل</button>
            <a href="purchases.php" class="btn" style="background:#ccc;color:#222;">الغاء</a>
        </form>
        <?php else: ?>
        <form method="post">
            <label>التاريخ:</label>
            <input type="date" name="purchase_date" required value="<?php echo date('Y-m-d'); ?>"><br>
            <label>اسم الـمـورد:</label>
            <select name="supplier_id" required>
                <option value="">اختر الـمـورد</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select><br>
            <label>رقم الفاتورة:</label>
            <input type="text" name="invoice_number" readonly value="<?php echo generate_purchase_invoice_number($conn); ?>"><br>
            <label>نوع البيض:</label>
            <select name="egg_type" required>
                <?php foreach ($egg_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                <?php endforeach; ?>
            </select><br>
            <label>الحجم:</label>
            <select name="egg_size" required>
                <?php foreach ($egg_sizes as $size): ?>
                    <option value="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></option>
                <?php endforeach; ?>
            </select><br>
            <label>الكمية:</label>
            <input type="number" name="quantity" id="quantity" min="1" required oninput="calcTotal()"><br>
            <label>سعر الوحدة:</label>
            <input type="number" name="unit_price" id="unit_price" min="0.01" step="0.01" required oninput="calcTotal()"><br>
            <div class="total-box">
                الإجمالي: <span id="total_amount">0.00 ريال</span>
            </div>
            <div class="total-box">
                ضريبة القيمة المضافة (15%): <span id="vat_amount">0.00 ريال</span>
            </div>
            <div class="total-box">
                الإجمالي شامل الضريبة: <span id="total_with_vat">0.00 ريال</span>
            </div>
            <button class="btn" type="submit" name="add_purchase">حفظ</button>
        </form>
        <?php endif; ?>
        <?php if (!empty($message)): ?>
            <div><?php echo $message; ?></div>
            <?php if ($last_purchase_id): ?>
                <form action="invoice_template.php" method="get" style="text-align:center;">
                    <input type="hidden" name="purchase_id" value="<?php echo $last_purchase_id; ?>">
                    <button class="btn print-btn" type="submit">طباعة الفاتورة</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- جدول الـتـوريـدات السابقة -->
    <h2 style="margin-top: 55px; margin-bottom: 18px;">جدول الـتـوريـدات السابقة</h2>
    <table class="purchases-table">
        <tr>
            <th>م</th>
            <th>التاريخ</th>
            <th>اسم الـمـورد</th>
            <th>رقم الفاتورة</th>
            <th>نوع البيض</th>
            <th>الحجم</th>
            <th>الكمية</th>
            <th>سعر الوحدة</th>
            <th>الإجمالي</th>
            <th>الإجراءات</th>
        </tr>
        <?php if (count($purchases)): ?>
            <?php foreach ($purchases as $k => $purchase): ?>
                <tr>
                    <td><?php echo $k+1; ?></td>
                    <td><?php echo htmlspecialchars($purchase['purchase_date']); ?></td>
                    <td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td>
                    <td><?php echo isset($purchase['invoice_number']) ? htmlspecialchars($purchase['invoice_number']) : ''; ?></td>
                    <td><?php echo htmlspecialchars($purchase['egg_type']); ?></td>
                    <td><?php echo htmlspecialchars($purchase['egg_size']); ?></td>
                    <td><?php echo htmlspecialchars($purchase['quantity']); ?></td>
                    <td><?php echo htmlspecialchars(number_format($purchase['unit_price'],2)); ?></td>
                    <td><?php echo htmlspecialchars(number_format($purchase['total_amount'],2)); ?></td>
                    <td>
                        <!-- زر طباعة الفاتورة -->
                        <form action="invoice_template.php" method="get" target="_blank" style="display:inline;">
                            <input type="hidden" name="purchase_id" value="<?php echo $purchase['id']; ?>">
                            <button class="btn print-btn btn-action" type="submit" title="طباعة فاتورة">
                                <span class="material-icons" style="font-size:20px;vertical-align:middle;">print</span>
                            </button>
                        </form>
                        <!-- زر طباعة سند قبض -->
                        <a href="sandqabd.php?type=purchase&id=<?php echo $purchase['id']; ?>" class="btn-sandqabd btn-action" title="طباعة سند قبض" target="_blank">
                            <span class="material-icons" style="font-size:20px;vertical-align:middle;">receipt_long</span>
                        </a>
                        <!-- زر تعديل -->
                        <form action="purchases.php" method="get" style="display:inline;">
                            <input type="hidden" name="edit_purchase_id" value="<?php echo $purchase['id']; ?>">
                            <button class="btn btn-edit btn-action" type="submit" title="تعديل">
                                <span class="material-icons" style="font-size:20px;vertical-align:middle;">edit</span>
                            </button>
                        </form>
                        <!-- زر حذف -->
                        <form action="purchases.php" method="post" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف عملية الشراء؟');">
                            <input type="hidden" name="purchase_id" value="<?php echo $purchase['id']; ?>">
                            <button class="btn btn-delete btn-action" type="submit" name="delete_purchase" title="حذف">
                                <span class="material-icons" style="font-size:20px;vertical-align:middle;">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="10">لا توجد مشتريات سابقة.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
<?php
$conn->close();
?>