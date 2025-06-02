<?php
// إعداد متغيرات الفاتورة
$invoice_no   = '';
$invoice_date = '';
$invoice_time = date('H:i');
$customer_name  = '';
$items        = [];
$total        = 0;
$vat          = 0;
$total_with_vat = 0;

// الاتصال بقاعدة البيانات
$conn = new mysqli('localhost', 'root', '', 'eggs_inventory'); // تأكد من اسم قاعدة البيانات الصحيح
$conn->set_charset("utf8");

// جلب بيانات فاتورة المبيعات
if (isset($_GET['sale_id'])) {
    $sale_id = intval($_GET['sale_id']);
    
    // إضافة debugging
    echo "<!-- Sale ID: " . $sale_id . " -->";
    
    // استخدام Prepared Statement للحماية
    $stmt = $conn->prepare("
        SELECT s.*, c.name AS customer_name 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE s.id = ?
    ");
    
    if (!$stmt) {
        die("خطأ في الاستعلام: " . $conn->error);
    }
    
    $stmt->bind_param('i', $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $invoice_no = $row['id'];
        $invoice_date = $row['sale_date'];
        $customer_name = $row['customer_name'];
        
        // حساب ضريبة القيمة المضافة (15%)
        $total = floatval($row['total_amount']);
        $vat = $total * 0.15;
        $total_with_vat = $total + $vat;
        
        $items = [[
            'name' => $row['egg_type'] . ' - ' . $row['egg_size'],
            'price' => floatval($row['unit_price']),
            'qty' => intval($row['quantity']),
            'total' => floatval($row['total_amount'])
        ]];
        
        // إضافة debugging
        echo "<!-- Data loaded successfully -->";
    } else {
        echo "<!-- No data found for sale_id: " . $sale_id . " -->";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>فاتورة مبيعات</title>
    <style>
        body { font-family: 'Cairo', Tahoma, Arial, sans-serif; direction: rtl; background: #fff; margin: 0; }
        .header { display: flex; justify-content: space-between; margin: 30px 40px 0 40px; }
        .logo { width: 120px; }
        .company-info { text-align: right; font-size: 1.15em; }
        .main-title { text-align: center; font-size: 2em; font-weight: bold; margin-top: 18px; }
        .row { margin: 15px 40px 0 40px; font-size: 1.13em; }
        .row label { font-weight: bold; }
        .invoice-table { width: 90%; margin: 30px auto 0 auto; border-collapse: collapse; }
        .invoice-table th, .invoice-table td {
            border: 1px solid #888;
            padding: 12px 8px;
            font-size: 1.1em;
            text-align: center;
        }
        .invoice-table th { background: #f7f7f7; }
        .total-section { margin: 36px 0 0 0; text-align: left; padding-left: 60px; font-size: 1.2em; }
        .sign-section { margin: 60px 40px 0 0; font-size: 1.15em; }
        .dotted { border-bottom: 1px dotted #222; display: inline-block; width: 260px; vertical-align: middle; }
        @media print { 
            button { display: none; }
            body { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <img src="icons\rkod_logo.png" class="logo" alt="Logo">
        </div>
        <div class="company-info">
            مؤسسة بيع البيض<br>
            العنوان: <!--اكتب العنوان هنا--><br>
            الرقم الضريبي: <!--اكتب الرقم الضريبي هنا--><br>
            جوال: <!--اكتب رقم الجوال هنا--><br>
            <br>
            التاريخ: <?= htmlspecialchars($invoice_date) ?><br>
            الوقت: <?= htmlspecialchars($invoice_time) ?><br>
        </div>
    </div>
    
    <div class="row" style="margin-top:30px;">
        <label>رقم الفاتورة:</label>
        <span class="dotted"><?= htmlspecialchars($invoice_no) ?></span>
    </div>
    <div class="row" style="margin-top:30px;">
    <strong>سند قبض</strong>
</div>

<div class="row">
    <label>طريقة الدفع:</label>
    <label style="margin-right: 20px;"><input type="checkbox"> نقدي</label>
    <label style="margin-right: 20px;"><input type="checkbox"> آجل</label>
    <label style="margin-right: 20px;"><input type="checkbox"> نقاط بيع</label>
    <label style="margin-right: 20px;"><input type="checkbox"> حوالة بنكية</label>
</div>
<div class="row">
    <label>رقم الحوالة:</label> <span class="dotted" style="width: 200px;"></span>
    <label style="margin-right: 40px;">البنك:</label> <span class="dotted" style="width: 200px;"></span>
    <label style="margin-right: 40px;">تاريخ الحوالة:</label> <span class="dotted" style="width: 200px;"></span>
</div>
    
    <div class="sign-section">
        توقيع المستلم: _________________
    </div>
    
    <div style="text-align:center;margin:35px;">
        <button onclick="window.print()">طباعة الفاتورة</button>
    </div>
</body>
</html>