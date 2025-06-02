<?php
// إعداد الاتصال بقاعدة البيانات
$host = "localhost";
$db = "rokood_db";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("خطأ في الاتصال: " . $conn->connect_error);
}
$conn->query("CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$conn->select_db($db);

// التأكد من وجود جدول المصروفات والأعمدة اللازمة
$tableCheck = $conn->query("SHOW TABLES LIKE 'expenses'");
if (!$tableCheck || $tableCheck->num_rows == 0) {
    $createTableSql = "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_date DATE NOT NULL,
        expense_type VARCHAR(100) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    $conn->query($createTableSql);
} else {
    // التأكد من وجود الأعمدة للضريبة والإجمالي
    $columns = [];
    $colsResult = $conn->query("SHOW COLUMNS FROM expenses");
    while ($row = $colsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    if (!in_array('vat_amount', $columns)) {
        $conn->query("ALTER TABLE expenses ADD COLUMN vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
    if (!in_array('total_with_vat', $columns)) {
        $conn->query("ALTER TABLE expenses ADD COLUMN total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
}

// حفظ بيانات المصروف الجديد
$success = false;
$error = "";
$new_expense = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $expense_date = $_POST['expense_date'];
    $expense_type = $_POST['expense_type'];
    $amount = floatval($_POST['amount']);
    $vat_amount = round($amount * 0.15, 2);
    $total_with_vat = round($amount + $vat_amount, 2);
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO expenses (expense_date, expense_type, amount, vat_amount, total_with_vat, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddds", $expense_date, $expense_type, $amount, $vat_amount, $total_with_vat, $notes);

    if ($stmt->execute()) {
        $success = true;
        $last_id = $conn->insert_id;
        $result = $conn->query("SELECT * FROM expenses WHERE id=$last_id LIMIT 1");
        $new_expense = $result ? $result->fetch_assoc() : null;
    } else {
        $error = "حدث خطأ أثناء حفظ البيانات: " . $stmt->error;
    }
    $stmt->close();
}

// تصدير البيانات إلى إكسل عند الطلب
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=expenses_" . date('Ymd_His') . ".xls");
    echo "التاريخ\tنوع المصروف\tالمبلغ\tضريبة القيمة المضافة (15%)\tالإجمالي شامل الضريبة\tالملاحظات\n";
    $result = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");
    while ($row = $result->fetch_assoc()) {
        echo "{$row['expense_date']}\t{$row['expense_type']}\t{$row['amount']}\t{$row['vat_amount']}\t{$row['total_with_vat']}\t";
        echo str_replace(array("\t","\n","\r"), " ", $row['notes']) . "\n";
    }
    exit;
}

// جلب المصروفات للعرض
$expenses = [];
$result = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
}

// تجهيز متغيرات الفاتورة عند إضافة مصروف جديد فقط
if ($success && $new_expense) {
    $invTitle = "إيصال مصروف";
    $invoiceDate = $new_expense['expense_date'];
    $invoiceNumber = $new_expense['id'];
    $dataHeaders = ["الوصف", "المبلغ", "الضريبة", "الإجمالي"];
    $dataRows = [
        [
            $new_expense['notes'],
            number_format($new_expense['amount'],2),
            number_format($new_expense['vat_amount'],2),
            number_format($new_expense['total_with_vat'],2)
        ]
    ];
    $invoiceInfo = [
        "نوع المصروف" => $new_expense['expense_type'],
        "الإجمالي الكلي" => number_format($new_expense['total_with_vat'],2) . " ريال"
    ];
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>صفحة المصروفات | مؤسسة ركود التجارية</title>
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
            padding: 10px 26px;
            border-radius: 7px;
            border: none;
            background: #304ffe;
            color: #fff;
            font-size: 1.12em;
            font-weight: bold;
            margin: 7px 0;
            cursor:pointer;
            transition: background 0.18s, color 0.18s;
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
        .alert-success {
            background: #d4edda;
            color: #26734d;
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 15px;
        }
        .alert-error {
            background: #f8d7da;
            color: #b71c1c;
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 15px;
        }
        .btn-print {
            background: #1976d2;
            color: #fff;
            padding: 11px 24px;
            font-size: 16px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 0 0 10px;
            display: inline-block;
            transition: background 0.18s;
        }
        .btn-print:hover {
            background: #304ffe;
        }
        .form-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
        }
        @media (max-width:900px) {
            body { margin-right: 0; }
            .form-box { width: 97vw; max-width: 500px;}
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
    <script>
    function calcVAT() {
        var amount = parseFloat(document.getElementById('amount').value) || 0;
        var vat = +(amount * 0.15).toFixed(2);
        var total = +(amount + vat).toFixed(2);
        document.getElementById('vat_amount').value = vat;
        document.getElementById('total_with_vat').value = total;
    }
    window.onload = function() {
        if (document.getElementById('amount')) {
            calcVAT();
        }
    }
    function printReceipt() {
        window.print();
    }
    </script>
</head>
<body>
<!-- الشريط الجانبي -->
<div class="sidebar">
    <h2>لوحة التحكم</h2>
    <a href="index.php"><span class="material-icons" style="vertical-align:middle">home</span> الرئيسية</a>
    <a href="inventory_management.php"><span class="material-icons" style="vertical-align:middle">inventory_2</span> إدارة المخزون</a>
    <a href="suppliers.php"><span class="material-icons" style="vertical-align:middle">local_shipping</span> الموردين</a>
    <a href="customers.php"><span class="material-icons" style="vertical-align:middle">groups</span> العملاء</a>
    <a href="sales.php"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
    <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> الـتـوريـد</a>
    <a href="expenses.php" class="active"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
    <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
</div>

    <h2>إدارة المصروفات</h2>
    <?php if ($success && $new_expense): ?>
        <div class="alert-success">تم حفظ المصروف بنجاح!</div>
        <?php
        // تضمين قالب الفاتورة الموحد وتمرير المتغيرات
        include 'invoice_template.php';
        ?>
        <button class="btn-print" onclick="printReceipt()">طباعة</button>
    <?php endif; ?>

    <?php if (!$success): ?>
        <div class="form-box">
            <h3>إضافة مصروف جديد</h3>
            <?php if ($error): ?>
                <div class="alert-error"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST" id="expenseForm" autocomplete="off">
                <label for="expense_date">التاريخ:</label>
                <input type="date" name="expense_date" id="expense_date" required><br>
                <label for="expense_type">نوع المصروف:</label>
                <select name="expense_type" id="expense_type" required>
                    <option value="">اختر النوع</option>
                    <option value="نقل">نقل</option>
                    <option value="عمالة">عمالة</option>
                    <option value="كهرباء">كهرباء</option>
                    <option value="صيانة">صيانة</option>
                    <option value="إيجار">إيجار</option>
                    <option value="مواد مكتبية">مواد مكتبية</option>
                    <option value="تسويق">تسويق</option>
                    <option value="استهلاكات">استهلاكات</option>
                    <option value="أخرى">أخرى</option>
                </select><br>
                <label for="amount">المبلغ (بدون الضريبة):</label>
                <input type="number" name="amount" id="amount" min="0" step="0.01" required oninput="calcVAT()"><br>
                <label for="vat_amount">ضريبة القيمة المضافة (15%):</label>
                <input type="number" id="vat_amount" name="vat_amount" readonly><br>
                <label for="total_with_vat">الإجمالي شامل الضريبة:</label>
                <input type="number" id="total_with_vat" name="total_with_vat" readonly><br>
                <label for="notes">الملاحظات:</label>
                <textarea name="notes" id="notes" placeholder="تفاصيل أو ملاحظات إضافية"></textarea><br>
                <div class="form-actions">
                    <button type="submit" class="btn" name="save">
                        <span class="material-icons" style="font-size:18px;vertical-align:middle;">save</span> حفظ المصروف
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <h3>جدول المصروفات</h3>
    <table>
        <tr>
            <th>#</th>
            <th>التاريخ</th>
            <th>نوع المصروف</th>
            <th>المبلغ</th>
            <th>ضريبة القيمة المضافة (15%)</th>
            <th>الإجمالي شامل الضريبة</th>
            <th>الملاحظات</th>
        </tr>
        <?php foreach($expenses as $i => $row): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($row['expense_date']) ?></td>
                <td><?= htmlspecialchars($row['expense_type']) ?></td>
                <td><?= number_format($row['amount'], 2) ?></td>
                <td><?= number_format($row['vat_amount'], 2) ?></td>
                <td><?= number_format($row['total_with_vat'], 2) ?></td>
                <td><?= htmlspecialchars($row['notes']) ?></td>
            </tr>
        <?php endforeach ?>
    </table>
    <form method="get" style="margin-top:20px;text-align:center;">
        <button type="submit" name="export" value="excel" class="btn add-btn">
            <span class="material-icons" style="vertical-align:middle;">download</span> تصدير إلى ملف Excel
        </button>
    </form>
</body>
</html>
<?php
$conn->close();
?>