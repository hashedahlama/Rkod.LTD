<?php
// إعداد متغيرات الفاتورة الموحدة (مبيعات أو مشتريات)
$invoice_no   = '';
$invoice_date = '';
$invoice_time = date('H:i');
$client_label = '';
$client_name  = '';
$invoice_number = '';
$items        = [];
$total        = 0;
$vat          = 0;
$total_with_vat = 0;
$invoice_title = '';
$source_type = ''; // sale أو purchase

$conn = new mysqli('localhost', 'root', '', 'eggs_inventory');
$conn->set_charset("utf8");

// تحديد نوع الفاتورة بناءً على الرابط
if (isset($_GET['sale_id'])) {
    $source_type = 'sale';
    $sale_id = intval($_GET['sale_id']);
    $stmt = $conn->prepare("
        SELECT s.*, c.name AS customer_name 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE s.id = ?
    ");
    $stmt->bind_param('i', $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $invoice_no = $row['id'];
        $invoice_date = $row['sale_date'];
        $client_name = $row['customer_name'];
        $invoice_number = $row['invoice_number'] ?? '';
        $total = floatval($row['total_amount']);
        $vat = $total * 0.15;
        $total_with_vat = $total + $vat;
        $items = [[
            'name'  => $row['egg_type'] . ' - ' . $row['egg_size'],
            'price' => floatval($row['unit_price']),
            'qty'   => intval($row['quantity']),
            'total' => floatval($row['total_amount'])
        ]];
        $invoice_title = "فاتورة مبيعات";
        $client_label = "اسم العميل";
    }
    $stmt->close();
} elseif (isset($_GET['purchase_id'])) {
    $source_type = 'purchase';
    $purchase_id = intval($_GET['purchase_id']);
    $stmt = $conn->prepare("
        SELECT p.*, s.name AS supplier_name 
        FROM purchases p 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.id = ?
    ");
    $stmt->bind_param('i', $purchase_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $invoice_no = $row['id'];
        $invoice_date = $row['purchase_date'];
        $client_name = $row['supplier_name'];
        $invoice_number = $row['invoice_number'] ?? '';
        $total = floatval($row['total_amount']);
        $vat = $row['vat_amount'] ?? ($total * 0.15);
        $total_with_vat = $row['total_with_vat'] ?? ($total + $vat);
        $items = [[
            'name'  => $row['egg_type'] . ' - ' . $row['egg_size'],
            'price' => floatval($row['unit_price']),
            'qty'   => intval($row['quantity']),
            'total' => floatval($row['total_amount'])
        ]];
        $invoice_title = "فاتورة تـوريـد";
        $client_label = "اسم الـمـورد";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($invoice_title) ?></title>
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
            <img src="icons/rkod_logo.png" class="logo" alt="Logo">
        </div>
        <div class="company-info">
            مؤسسة ركود التجارية<br>
            العنوان: الرياض<br>
            الرقم الضريبي: 301056559500003<br>
            جوال: 0542287038<br>
            <br>
            التاريخ: <?= htmlspecialchars($invoice_date) ?><br>
            الوقت: <?= htmlspecialchars($invoice_time) ?><br>
        </div>
    </div>
    
    <div class="row">
        <label>رقم الفاتورة:</label>
        <span class="dotted"><?= htmlspecialchars($invoice_number) ?></span>
    </div>
    
    <div class="main-title"><?= htmlspecialchars($invoice_title) ?></div>
    
    <div class="row">
        <?= htmlspecialchars($client_label) ?>: <span class="dotted"><?= htmlspecialchars($client_name) ?></span>
    </div>
    
    <table class="invoice-table">
        <tr>
            <th>المنتج/الوصف</th>
            <th>السعر</th>
            <th>الكمية</th>
            <th>الإجمالي</th>
        </tr>
        <?php if(!empty($items)): ?>
            <?php foreach($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= number_format($item['price'], 2) ?> ريال</td>
                    <td><?= htmlspecialchars($item['qty']) ?></td>
                    <td><?= number_format($item['qty'] * $item['price'], 2) ?> ريال</td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">لا توجد بيانات للفاتورة</td>
            </tr>
        <?php endif; ?>
    </table>
    
    <div class="total-section">
        <div>المجموع: <?= number_format($total, 2) ?> ريال</div>
        <?php if ($vat > 0): ?>
            <div>ضريبة القيمة المضافة (15%): <?= number_format($vat, 2) ?> ريال</div>
        <?php endif; ?>
        <div style="font-weight:bold;margin-top:10px;">
            الإجمالي النهائي: <?= number_format($total_with_vat, 2) ?> ريال
        </div>
    </div>
    
    <div class="sign-section">
        توقيع المستلم: _________________
    </div>
    
    <div style="text-align:center;margin:35px;">
        <button onclick="window.print()">طباعة الفاتورة</button>
    </div>
</body>
</html>