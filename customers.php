<?php
// إعداد الاتصال بقاعدة البيانات
$host = 'localhost';
$db = 'eggs_inventory';
$user = 'root';
$pass = '';

// الاتصال بدون تحديد قاعدة بيانات لإنشائها إذا لم تكن موجودة
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// إنشاء قاعدة البيانات إذا لم تكن موجودة
$conn->query("CREATE DATABASE IF NOT EXISTS $db");

// اختيار قاعدة البيانات
$conn->select_db($db);

// إنشاء جدول العملاء إذا لم يكن موجوداً (بإضافة العنوان الوطني)
$conn->query("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    type VARCHAR(20),
    mobile VARCHAR(20),
    address VARCHAR(255),
    notes TEXT
)");

// إنشاء جدول المشتريات إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    purchase_date DATE,
    quantity INT,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
)");

// إنشاء جدول المبيعات إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE,
    customer_id INT,
    egg_type VARCHAR(30),
    egg_size VARCHAR(30),
    quantity INT,
    unit_price DECIMAL(10,2),
    total_amount DECIMAL(12,2),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
)");

// حذف عميل
$message = '';
if (isset($_POST['delete_customer']) && isset($_POST['customer_id'])) {
    $delete_id = intval($_POST['customer_id']);
    $stmt = $conn->prepare("DELETE FROM customers WHERE id=?");
    $stmt->bind_param('i', $delete_id);
    if ($stmt->execute()) {
        $message = "<div style='color:red;'>تم حذف العميل بنجاح.</div>";
    } else {
        $message = "<div style='color:red;'>حدث خطأ أثناء حذف العميل!</div>";
    }
}

// إضافة عميل جديد
if (isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $mobile = $_POST['mobile'];
    $address = $_POST['address'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO customers (name, mobile, address, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $mobile, $address, $notes);

    if ($stmt->execute()) {
        echo "تمت إضافة العميل بنجاح!";
    } else {
        echo "حدث خطأ أثناء إضافة العميل.";
    }
}

// جلب العملاء
$customers = [];
$res = $conn->query("SELECT * FROM customers ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    $customers[] = $row;
}

// عرض سجل مشتريات عميل
$customer_purchases = [];
$show_purchases_for = null;
if (isset($_GET['purchases'])) {
    $customer_id = intval($_GET['purchases']);
    // جلب بيانات العميل
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $show_purchases_for = $stmt->get_result()->fetch_assoc();

    // جلب المشتريات الخاصة بهذا العميل
    $stmt2 = $conn->prepare("SELECT * FROM purchases WHERE customer_id=? ORDER BY purchase_date DESC, id DESC");
    $stmt2->bind_param('i', $customer_id);
    $stmt2->execute();
    $customer_purchases = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

    // جلب عمليات البيع الخاصة بهذا العميل
    $customer_sales = [];
    $stmt3 = $conn->prepare("SELECT * FROM sales WHERE customer_id=? ORDER BY sale_date DESC, id DESC");
    $stmt3->bind_param('i', $customer_id);
    $stmt3->execute();
    $customer_sales = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>صفحة العملاء</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            margin: 40px;
            margin-right: 240px;
            background: #f5f7fa;
        }
        h2, h3, h4 {
            color: #304ffe;
        }
        label {
            display: inline-block;
            width: 140px;
            font-size: 1.15em;
            margin-bottom: 7px;
        }
        input[type="text"], input[type="date"], input[type="number"], select, textarea {
            width: 85%;
            font-size: 1.15em;
            padding: 12px 10px;
            border: 1.5px solid #bdbdbd;
            border-radius: 8px;
            margin-bottom: 14px;
            transition: border 0.2s;
            background: #fff;
            box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="date"]:focus, input[type="number"]:focus, select:focus, textarea:focus {
            border: 2px solid #304ffe;
            outline: none;
            background: #f1f7fe;
        }
        textarea {
            resize: vertical;
            min-height: 50px;
            max-height: 160px;
        }
        table {
            border-collapse: collapse;
            margin-top: 30px;
            background: #fff;
            width: 100%;
            box-shadow: 0 2px 12px 0 rgba(80,80,80,0.08);
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid #e0e0e0;
            padding: 14px 18px;
            font-size: 1.07em;
        }
        th {
            background: #304ffe;
            color: #fff;
            font-size: 1.13em;
        }
        .form-box {
            border: 1.5px solid #ddd;
            padding: 28px 28px 10px 28px;
            width: 460px;
            margin-bottom: 35px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 14px 0 rgba(44, 62, 80, 0.09);
        }
        .btn {
            padding: 7px 12px;
            border-radius: 6px;
            border: none;
            background: #304ffe;
            color: #fff;
            font-size: 0.96em;
            font-weight: bold;
            margin: 2px 0;
            cursor:pointer;
            transition: background 0.18s, color 0.18s;
            vertical-align: middle;
            display: inline-block;
        }
        .btn:hover {
            background: #1976d2;
            color: #fff;
        }
        .add-btn {
            margin-bottom: 18px;
            background: #43a047;
            color: #fff;
            border: none;
        }
        .add-btn:hover {
            background: #388e3c;
        }
        .details-box {
            border: 1.5px solid #bbb;
            padding: 22px 28px 8px 28px;
            background: #fcfcfc;
            margin-bottom: 20px;
            border-radius: 13px;
            width: 520px;
            box-shadow: 0 2px 8px 0 rgba(80,80,80,0.07);
        }
        .close-btn {
            background:#eee;
            color:#666;
            border: none;
            font-weight: normal;
        }
        .close-btn:hover {
            background:#ccc;
            color: #222;
        }
        .action-btns {
            display: flex;
            gap: 6px;
            justify-content: flex-start;
            align-items: center;
        }
        .btn-edit {
            background: #ff9800;
            color: #222;
        }
        .btn-edit:hover {
            background: #ffa726;
        }
        .btn-delete {
            background: #e53935;
            color: #fff;
        }
        .btn-delete:hover {
            background: #b71c1c;
        }
        @media (max-width:900px) {
            body { margin-right: 0; }
            .form-box, .details-box { width: 97vw; max-width: 500px;}
            table { font-size: 0.98em;}
        }
        /* الشريط الجانبي */
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 220px;
            height: 100%;
            background: linear-gradient(135deg, #304ffe 60%, #1976d2 100%);
            color: #fff;
            box-shadow: -2px 0 8px rgba(60,60,60,0.09);
            padding-top: 40px;
            z-index: 100;
        }
        .sidebar h2 {
            text-align: center;
            font-size: 1.2em;
            margin-bottom: 40px;
        }
        .sidebar a {
            display: block;
            padding: 15px 30px;
            color: #fff;
            text-decoration: none;
            font-size: 1.12em;
            margin-bottom: 8px;
            border-radius: 30px 0 0 30px;
            transition: background 0.2s;
        }
        .sidebar a.active,
        .sidebar a:hover {
            background: rgba(255,255,255,0.13);
        }
        @media (max-width: 900px) {
            .sidebar {
                width: 100vw;
                height: 65px;
                position: fixed;
                top: 0;
                right: 0;
                display: flex;
                flex-direction: row;
                align-items: center;
                padding: 0 12px;
                z-index: 999;
            }
            .sidebar h2 {
                display: none;
            }
            .sidebar a {
                display: inline-block;
                padding: 8px 18px;
                margin-bottom: 0;
                border-radius: 30px;
                font-size: 1em;
                margin-right: 4px;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<!-- الشريط الجانبي -->
<div class="sidebar">
    <h2>لوحة التحكم</h2>
    <a href="index.php"><span class="material-icons" style="vertical-align:middle">home</span> الرئيسية</a>
    <a href="inventory_management.php"><span class="material-icons" style="vertical-align:middle">inventory_2</span> إدارة المخزون</a>
    <a href="suppliers.php"><span class="material-icons" style="vertical-align:middle">local_shipping</span> الموردين</a>
    <a href="customers.php" class="active"><span class="material-icons" style="vertical-align:middle">groups</span> العملاء</a>
    <a href="sales.php"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
    <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> الـتـوريـد</a>
    <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
    <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
</div>

    <h2>إدارة العملاء</h2>
    <?php echo $message; ?>

    <?php if ($show_purchases_for): ?>
        <div class="details-box">
            <h3>سجل مشتريات العميل: <?php echo htmlspecialchars($show_purchases_for['name']); ?></h3>
            <div style="margin-bottom:12px;">
                <b>النوع:</b> <?php echo htmlspecialchars($show_purchases_for['type']); ?><br>
                <b>رقم الجوال:</b> <?php echo htmlspecialchars($show_purchases_for['mobile']); ?><br>
                <b>العنوان الوطني:</b> <?php echo htmlspecialchars($show_purchases_for['address']); ?><br>
                <b>الملاحظات:</b> <?php echo htmlspecialchars($show_purchases_for['notes']); ?><br>
            </div>
            <hr>
            <h4>سجل المشتريات</h4>
            <?php if (count($customer_purchases)): ?>
                <table>
                    <tr>
                        <th>التاريخ</th>
                        <th>الكمية</th>
                        <th>ملاحظات</th>
                    </tr>
                    <?php foreach ($customer_purchases as $purchase): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($purchase['purchase_date']); ?></td>
                            <td><?php echo htmlspecialchars($purchase['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($purchase['notes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div>لا توجد مشتريات لهذا العميل بعد.</div>
            <?php endif; ?>

            <!-- سجل المبيعات الخاص بالعميل -->
            <h4 style="margin-top:30px;">سجل المبيعات</h4>
            <?php if (!empty($customer_sales)): ?>
                <table>
                    <tr>
                        <th>التاريخ</th>
                        <th>نوع البيض</th>
                        <th>الحجم</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>الإجمالي</th>
                    </tr>
                    <?php foreach ($customer_sales as $sale): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                            <td><?php echo htmlspecialchars($sale['egg_type']); ?></td>
                            <td><?php echo htmlspecialchars($sale['egg_size']); ?></td>
                            <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($sale['unit_price'],2)); ?></td>
                            <td><?php echo htmlspecialchars(number_format($sale['total_amount'],2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div>لا توجد مبيعات لهذا العميل بعد.</div>
            <?php endif; ?>
            <br>
            <a class="btn close-btn" href="customers.php">عودة لقائمة العملاء</a>
        </div>
    <?php else: ?>
        <button class="btn add-btn" onclick="document.getElementById('addCustomerBox').style.display='block';">اضافة عميل جديد</button>
        <div class="form-box" id="addCustomerBox" style="display:none;">
            <h3>إضافة عميل جديد</h3>
            <form method="post">
                <label>اسم العميل:</label>
                <input type="text" name="name" required><br>
                <label>نوع العميل:</label>
                <select name="type" required>
                    <option value="جملة">جملة</option>
                    <option value="تجزئة">تجزئة</option>
                </select><br>
                <label>رقم الجوال:</label>
                <input type="text" name="mobile" required><br>
                <label>العنوان الوطني:</label>
                <input type="text" name="address" required><br>
                <label>ملاحظات:</label>
                <textarea name="notes" rows="3"></textarea><br>
                <button class="btn" type="submit" name="add_customer">حفظ العميل</button>
                <button class="btn close-btn" type="button" onclick="document.getElementById('addCustomerBox').style.display='none';">إلغاء</button>
            </form>
        </div>
        <h3>قائمة العملاء</h3>
        <table>
            <tr>
                <th>الاسم</th>
                <th>النوع</th>
                <th>رقم الجوال</th>
                <th>العنوان الوطني</th>
                <th>ملاحظات</th>
                <th>الإجراءات</th>
            </tr>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                    <td><?php echo htmlspecialchars($customer['type']); ?></td>
                    <td><?php echo htmlspecialchars($customer['mobile']); ?></td>
                    <td><?php echo htmlspecialchars($customer['address']); ?></td>
                    <td><?php echo htmlspecialchars($customer['notes']); ?></td>
                    <td>
                        <div class="action-btns">
                            <a class="btn" style="background:#2196f3;font-size:0.94em;" href="?purchases=<?php echo $customer['id']; ?>">
                                <span class="material-icons" style="font-size:16px;vertical-align:middle;">receipt_long</span>
                                سجل المشتريات
                            </a>
                            <a class="btn btn-edit" href="edit_customer.php?id=<?php echo $customer['id']; ?>">
                                <span class="material-icons" style="font-size:16px;vertical-align:middle;">edit</span>
                            </a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا العميل؟');">
                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                <button class="btn btn-delete" type="submit" name="delete_customer" title="حذف">
                                    <span class="material-icons" style="font-size:16px;vertical-align:middle;">delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <script>
            // إظهار نافذة إضافة عميل تلقائياً إذا حدث خطأ في الإضافة
            <?php if (isset($_POST['add_customer']) && strpos($message, 'خطأ') !== false): ?>
            document.getElementById('addCustomerBox').style.display = 'block';
            <?php endif; ?>
        </script>
    <?php endif; ?>
</body>
</html>
<?php
$conn->close();
?>