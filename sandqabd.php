<?php
$host = "localhost";
$db = "eggs_inventory";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8");

// استقبال نوع القسم والمعرف من الرابط أو POST
$type = $_GET['type'] ?? $_POST['type'] ?? 'purchase'; // purchase أو sale أو غيرها
$id   = intval($_GET['id'] ?? $_POST['id'] ?? 0);

$receipt_no = '';
$receipt_date = date('Y-m-d');
$receipt_time = date('H:i');
$client_name = '';
$table_title = '';
$table_data = [];
$receipt_type_text = '';
$invoice_number = '';

// جلب قائمة الموردين
$suppliers = [];
$res_sup = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");
while ($row = $res_sup->fetch_assoc()) {
    $suppliers[$row['id']] = $row['name'];
}

if ($type == 'purchase') {
    $receipt_type_text = "نوع السند: مشتريات";
} elseif ($type == 'sale') {
    $receipt_type_text = "نوع السند: مبيعات";
} else {
    $receipt_type_text = "نوع السند: غير محدد";
}

if ($id > 0) {
    if ($type == 'purchase') {
        // جلب بيانات عملية شراء مع اسم المورد من جدول الموردين
        $result = $conn->query("SELECT * FROM purchases WHERE id = $id");
        if ($row = $result->fetch_assoc()) {
            $receipt_no   = $row['id'];
            $receipt_date = $row['purchase_date'];
            // جلب اسم المورد من جدول الموردين
            $supplier_id = $row['supplier_id'];
            $client_name = isset($suppliers[$supplier_id]) ? $suppliers[$supplier_id] : 'مورد غير معروف';
            $invoice_number = $row['invoice_number'] ?? '';
            $table_title  = "تفاصيل عملية الشراء";
            $table_data[] = [
                "رقم الفاتورة" => $invoice_number,
                "التاريخ" => $row['purchase_date'],
                "اسم المورد" => $client_name,
                "نوع البيض" => $row['egg_type'],
                "الحجم" => $row['egg_size'],
                "الكمية" => $row['quantity'],
                "السعر للوحدة" => number_format($row['unit_price'], 2),
                "المبلغ الإجمالي" => number_format($row['total_amount'], 2),
                "ضريبة القيمة المضافة" => number_format($row['vat_amount'], 2),
                "الإجمالي شامل الضريبة" => number_format($row['total_with_vat'], 2),
                "إجراءات" => ""
            ];
        }
    } elseif ($type == 'sale') {
        // جلب بيانات عملية بيع مع اسم العميل
        $result = $conn->query("SELECT sales.*, customers.name AS customer_name FROM sales LEFT JOIN customers ON sales.customer_id = customers.id WHERE sales.id = $id");
        if ($row = $result->fetch_assoc()) {
            $receipt_no   = $row['id'];
            $receipt_date = $row['sale_date'];
            $client_name  = $row['customer_name'];
            $invoice_number = $row['invoice_number'] ?? '';
            // حساب الضريبة والإجمالي مع الضريبة إذا لم تكن موجودة في الجدول
            $vat_amount = $row['total_amount'] * 0.15;
            $total_with_vat = $row['total_amount'] + $vat_amount;
            $table_title  = "تفاصيل عملية البيع";
            $table_data[] = [
                "رقم الفاتورة" => $invoice_number,
                "التاريخ" => $row['sale_date'],
                "اسم المورد" => $row['customer_name'],
                "نوع البيض" => $row['egg_type'] ?? '',
                "الحجم" => $row['egg_size'] ?? '',
                "الكمية" => $row['quantity'],
                "السعر للوحدة" => number_format($row['unit_price'], 2),
                "المبلغ الإجمالي" => number_format($row['total_amount'], 2),
                "ضريبة القيمة المضافة" => number_format($vat_amount, 2),
                "الإجمالي شامل الضريبة" => number_format($total_with_vat, 2),
                "إجراءات" => ""
            ];
        }
    }
    // يمكنك إضافة أقسام أخرى بنفس الطريقة ...
}

// إذا لم يتم جلب من قاعدة البيانات، استخدم POST كما كان سابقا (احتياطي)
if (!$receipt_no) {
    $receipt_no = $_POST['receipt_no'] ?? 'غير محدد';
    $receipt_date = $_POST['receipt_date'] ?? date('Y-m-d');
    $receipt_time = $_POST['receipt_time'] ?? date('H:i');
    $client_name = $_POST['client_name'] ?? 'عميل غير معروف';
    $invoice_number = $_POST['invoice_number'] ?? '';
    $table_title = "تفاصيل العملية";
    $table_data[] = [
        "رقم الفاتورة" => $invoice_number,
        "التاريخ" => $receipt_date,
        "اسم المورد" => $client_name,
        "نوع البيض" => '',
        "الحجم" => '',
        "الكمية" => '',
        "السعر للوحدة" => '',
        "المبلغ الإجمالي" => '',
        "ضريبة القيمة المضافة" => '',
        "الإجمالي شامل الضريبة" => number_format(floatval($_POST['amount'] ?? 0), 2),
        "إجراءات" => ""
    ];
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8" />
    <title>سند قبض</title>
    <style>
        body { font-family: 'Cairo', Tahoma, Arial, sans-serif; direction: rtl; background: #fff; margin: 0; }
        .header { display: flex; justify-content: space-between; margin: 30px 40px 0 40px; }
        .logo { width: 120px; }
        .company-info { text-align: right; font-size: 1.15em; }
        .main-title { text-align: center; font-size: 2em; font-weight: bold; margin-top: 18px; }
        .row { margin: 15px 40px 0 40px; font-size: 1.13em; }
        .row label { font-weight: bold; }
        .sign-section { margin: 60px 40px 0 0; font-size: 1.15em; }
        .dotted { border-bottom: 1px dotted #222; display: inline-block; width: 260px; vertical-align: middle; }
        .details-table-container { margin: 30px 40px 0 40px;}
        table.details-table { width:100%; border-collapse:collapse; background: #fcfcfc;}
        table.details-table th, table.details-table td { border:1px solid #ccc; padding:8px 6px; text-align:center;}
        table.details-table th { background:#e3eaff; color:#304ffe;}
        input.table-edit { width: 100%; background: #fffbe9; border: 1px solid #ddd; font-size: 1em; padding: 4px 4px; text-align:center;}
        @media print { 
            button, .table-edit { display: none !important; }
            body { padding: 20px; }
            table.details-table td { border:1px solid #888; }
        }
        .receipt-type-row { margin: 10px 40px 0 40px; font-size: 1.14em; color: #304ffe; font-weight: bold;}
    </style>
    <script>
    function updateCell(input) {
        input.setAttribute('value', input.value);
    }
    function beforePrint(){
        let cells = document.querySelectorAll('.table-edit');
        cells.forEach(function(input){
            let td = input.parentElement;
            td.innerText = input.value;
        });
    }
    window.onbeforeprint = beforePrint;
    </script>
</head>
<body>
    <div class="header">
        <div>
            <img src="icons/rkod_logo.png" class="logo" alt="Logo" />
        </div>
        <div class="company-info">
            مؤسسة ركود التجارية<br />
            العنوان: الرياض<br />
            الرقم الضريبي: :301056559500003<br />
            جوال: 0542287038<br /><br />
            التاريخ: <?= htmlspecialchars($receipt_date) ?><br />
            الوقت: <?= htmlspecialchars($receipt_time) ?><br />
        </div>
    </div>
    
    <div class="row" style="margin-top:30px;">
        <label>رقم سند القبض:</label>
        <span class="dotted"><?= htmlspecialchars($receipt_no) ?></span>
    </div>
    <div class="row">
        <label>رقم الفاتورة:</label>
        <span class="dotted"><?= htmlspecialchars($invoice_number) ?></span>
    </div>

    <!-- سطر نوع السند -->
    <div class="receipt-type-row">
        <?= htmlspecialchars($receipt_type_text) ?>
    </div>
    
    <div class="main-title">سند قبض</div>
    
    <div class="row">
        اسم العميل / : <span class="dotted"><?= htmlspecialchars($client_name) ?></span>
    </div>
    
    <?php if (!empty($table_data)): ?>
    <div class="details-table-container">
        <div style="font-weight:bold; color:#304ffe; margin-bottom:7px;"><?= $table_title ?></div>
        <table class="details-table">
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>التاريخ</th>
                    <th>اسم المورد</th>
                    <th>نوع البيض</th>
                    <th>الحجم</th>
                    <th>الكمية</th>
                    <th>السعر للوحدة</th>
                    <th>المبلغ الإجمالي</th>
                    <th>ضريبة القيمة المضافة</th>
                    <th>الإجمالي شامل الضريبة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($table_data as $row): ?>
                <tr>
                    <?php foreach($row as $col => $val): ?>
                    <td>
                        <input class="table-edit" type="text" value="<?= htmlspecialchars($val) ?>" oninput="updateCell(this)" />
                    </td>
                    <?php endforeach ?>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="sign-section">
        توقيع المستلم: _________________
    </div>
    
    <div style="text-align:center;margin:35px;">
        <button onclick="window.print()">طباعة سند القبض</button>
    </div>
</body>
</html>