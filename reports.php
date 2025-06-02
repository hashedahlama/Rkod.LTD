<?php
// إعداد الاتصال بقاعدة البيانات
$host = "localhost";
$db = "rokood_db";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("خطأ في الاتصال: " . $conn->connect_error);
}

// التأكد من وجود جدول التقارير
$conn->query("CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    total_sales DECIMAL(12,2) NOT NULL,
    total_purchases DECIMAL(12,2) NOT NULL,
    total_expenses DECIMAL(12,2) NOT NULL,
    profit DECIMAL(12,2) NOT NULL,
    profit_percent DECIMAL(8,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

// معالجة الفترة الزمنية
$today = date('Y-m-d');
$from = isset($_GET['from']) ? $_GET['from'] : $today;
$to   = isset($_GET['to']) ? $_GET['to'] : $today;
if ($from > $to) { $temp = $from; $from = $to; $to = $temp; }

// استخراج البيانات من الأعمدة الصحيحة حسب الجداول الفعلية
function getSum($conn, $table, $date_col, $sum_col, $from, $to) {
    $sql = "SELECT SUM($sum_col) as total FROM $table WHERE $date_col BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return floatval($row['total']);
}
$total_sales = getSum($conn, "sales", "sale_date", "total_amount", $from, $to);
$total_purchases = getSum($conn, "purchases", "purchase_date", "total_amount", $from, $to);
$total_expenses = getSum($conn, "expenses", "expense_date", "amount", $from, $to);
$profit = $total_sales - $total_purchases - $total_expenses;
$profit_percent = $total_sales > 0 ? round(($profit / $total_sales) * 100, 2) : 0;

// حفظ التقرير في قاعدة البيانات عند الضغط على زر "حفظ التقرير"
$save_message = "";
if (isset($_POST['save_report'])) {
    $stmt = $conn->prepare("SELECT id FROM reports WHERE from_date=? AND to_date=?");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO reports (from_date, to_date, total_sales, total_purchases, total_expenses, profit, profit_percent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdddddd", $from, $to, $total_sales, $total_purchases, $total_expenses, $profit, $profit_percent);
        if ($stmt->execute()) {
            $save_message = "<div style='color:green;text-align:center;font-weight:bold;margin-bottom:12px;'>تم حفظ التقرير بنجاح.</div>";
        } else {
            $save_message = "<div style='color:red;text-align:center;font-weight:bold;margin-bottom:12px;'>حدث خطأ أثناء الحفظ.</div>";
        }
    } else {
        $save_message = "<div style='color:orange;text-align:center;font-weight:bold;margin-bottom:12px;'>هذا التقرير محفوظ مسبقاً.</div>";
    }
}

// جلب كل التقارير المحفوظة
$all_reports = [];
$res = $conn->query("SELECT * FROM reports ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    $all_reports[] = $row;
}

// تصدير إلى إكسل
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=report_" . date('Ymd_His') . ".xls");
    echo "الفترة\tإجمالي المبيعات\tإجمالي المشتريات\tإجمالي الـتـوريـد\tصافي الربح\tنسبة الربح\n";
    echo "$from إلى $to\t$total_sales\t$total_purchases\t$total_expenses\t$profit\t$profit_percent%\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>صفحة التقارير | مؤسسة ركود التجارية</title>
    <style>
        body {font-family:'Cairo',Tahoma,Arial,sans-serif;margin:0;background:#f5f7fa;direction:rtl;}
        .sidebar {
            position:fixed;right:0;top:0;width:220px;height:100%;
            background:linear-gradient(135deg,#304ffe 60%,#1976d2 100%);
            color:#fff;box-shadow:-2px 0 8px rgba(60,60,60,0.09);
            padding-top:40px;z-index:100;
        }
        .sidebar h2 {text-align:center;font-size:1.2em;margin-bottom:40px;}
        .sidebar a {display:block;padding:15px 30px;color:#fff;text-decoration:none;font-size:1.12em;margin-bottom:8px;
            border-radius:30px 0 0 30px;transition:background 0.2s;}
        .sidebar a.active,.sidebar a:hover {background:rgba(255,255,255,0.13);}
        .main-content {
            margin-right:260px;padding:50px 30px 30px 30px;min-height:100vh;
            display:flex;flex-direction:column;align-items:center;
        }
        .page-title {text-align:center;font-size:2em;color:#374151;margin-bottom:35px;letter-spacing:1px;}
        .report-form-container {
            background:#fff;border-radius:18px;padding:32px 24px 24px 24px;box-shadow:0 4px 18px 0 rgba(44,62,80,0.08);
            border:1px solid #f0f0f0;max-width:440px;width:100%;margin-bottom:32px;
        }
        .form-row {display:flex;gap:14px;margin-bottom:18px;}
        .form-group {flex:1;display:flex;flex-direction:column;}
        label {font-size:15px;margin-bottom:4px;color:#304ffe;font-weight:500;}
        input[type="date"] {padding:8px 10px;font-size:15px;border-radius:6px;border:1px solid #ddd;background:#fcfcfc;}
        .btn-generate {
            background:#304ffe;color:#fff;padding:12px 30px;font-size:16px;border:none;
            border-radius:7px;cursor:pointer;transition:background 0.2s;font-weight:bold;
        }
        .btn-generate:hover {background:#1976d2;}
        .report-result-container {
            width:100%;max-width:700px;background:#fff;box-shadow:0 2px 16px 0 rgba(44,62,80,0.10);
            border-radius:18px;border:1px solid #ececec;padding:25px 18px 30px 18px;margin-bottom:30px;
        }
        .report-table-title {font-size:1.2em;color:#304ffe;margin-bottom:14px;text-align:right;font-weight:bold;}
        table {width:100%;border-collapse:collapse;background:#fff;font-size:15px;}
        th, td {padding:12px 8px;text-align:center;border-bottom:1px solid #f0f0f0;}
        th {background:#f3f6fd;color:#304ffe;font-weight:bold;}
        tr:last-child td {border-bottom:none;}
        .btn-pdf {
            background:#fff;color:#304ffe;border:2px solid #304ffe;
            padding:11px 22px;font-size:16px;border-radius:7px;font-weight:bold;cursor:pointer;
            transition:background 0.2s, color 0.2s;display:inline-flex;align-items:center;gap:5px;margin-left:10px;
        }
        .btn-pdf:hover {background:#304ffe;color:#fff;}
        .btn-excel {
            background:#43a047;color:#fff;border:none;padding:13px 35px;font-size:18px;
            border-radius:7px;font-weight:bold;cursor:pointer;box-shadow:0 1px 6px #bdbdbd44;transition:background 0.2s;
            display:inline-flex;align-items:center;gap:8px;margin-left:10px;
        }
        .btn-excel:hover {background:#388e3c;}
        .btn-save {
            background:#ff9800;color:#fff;padding:12px 26px;font-size:16px;border:none;
            border-radius:7px;cursor:pointer;transition:background 0.2s;font-weight:bold;
            margin-top:10px;margin-bottom:10px;
            display:inline-block;
        }
        .btn-save:hover {background:#ffb300;}
        .all-reports-container {
            width:100%;max-width:900px;margin:20px auto 0 auto;background:#fff;box-shadow:0 2px 10px 0 rgba(44,62,80,0.07);
            border-radius:18px;padding:20px 10px 25px 10px;border:1px solid #e0e0e0;
        }
        @media (max-width:900px) {
            .main-content {margin-right:0;padding:20px 2vw;}
            .sidebar {width:100vw;height:65px;position:fixed;top:0;right:0;display:flex;flex-direction:row;align-items:center;padding:0 12px;z-index:999;}
            .sidebar h2 {display:none;}
            .sidebar a {display:inline-block;padding:8px 18px;margin-bottom:0;border-radius:30px;font-size:1em;margin-right:4px;}
            .report-result-container {padding:8px 2px 15px 2px;}
            table, th, td {font-size:13px;}
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h2>لوحة التحكم</h2>
        <a href="index.php"><span class="material-icons" style="vertical-align:middle">home</span> الرئيسية</a>
        <a href="inventory_management.php"><span class="material-icons" style="vertical-align:middle">inventory_2</span> إدارة المخزون</a>
        <a href="suppliers.php"><span class="material-icons" style="vertical-align:middle">local_shipping</span> الموردين</a>
        <a href="customers.php"><span class="material-icons" style="vertical-align:middle">groups</span> العملاء</a>
        <a href="sales.php"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
        <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> الـتـوريـد</a>
        <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
        <a href="reports.php" class="active"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
    </div>
    <div class="main-content">
        <div class="page-title">التقارير المالية</div>
        <div class="report-form-container">
            <form method="GET" id="reportForm" autocomplete="off">
                <div class="form-row">
                    <div class="form-group">
                        <label for="from">من تاريخ</label>
                        <input type="date" name="from" id="from" value="<?= htmlspecialchars($from) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="to">إلى تاريخ</label>
                        <input type="date" name="to" id="to" value="<?= htmlspecialchars($to) ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn-generate">
                    <span class="material-icons" style="font-size:18px;vertical-align:middle;">search</span> عرض التقرير
                </button>
            </form>
        </div>
        <div class="report-result-container" id="report-result">
            <?= $save_message ?>
            <div class="report-table-title">تقرير الفترة: <?= htmlspecialchars($from) ?> إلى <?= htmlspecialchars($to) ?></div>
            <div style="margin-bottom:16px;">
                <button class="btn-pdf" onclick="printReportPDF()">
                    <span class="material-icons" style="font-size:18px;vertical-align:middle;">picture_as_pdf</span>
                    تصدير و طباعة PDF
                </button>
                <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=excel" class="btn-excel">
                    <span class="material-icons" style="vertical-align:middle;">download</span>
                    تصدير إلى ملف Excel
                </a>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                    <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                    <button type="submit" name="save_report" class="btn-save">
                        <span class="material-icons" style="font-size:18px;vertical-align:middle;">save</span>
                        حفظ التقرير في قاعدة البيانات
                    </button>
                </form>
            </div>
            <table id="report-table">
                <thead>
                    <tr>
                        <th>إجمالي المبيعات</th>
                        <th>إجمالي ماتـم تـوريـده</th>
                        <th>إجمالي المصروفات</th>
                        <th>صافي الربح</th>
                        <th>نسبة الربح</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= number_format($total_sales,2) ?></td>
                        <td><?= number_format($total_purchases,2) ?></td>
                        <td><?= number_format($total_expenses,2) ?></td>
                        <td><?= number_format($profit,2) ?></td>
                        <td><?= $profit_percent ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="all-reports-container">
            <div class="report-table-title" style="margin-bottom:10px;">جميع التقارير المحفوظة</div>
            <table>
                <thead>
                    <tr>
                        <th>م</th>
                        <th>الفترة</th>
                        <th>المبيعات</th>
                        <th>الـتـوريـد</th>
                        <th>المصروفات</th>
                        <th>الربح</th>
                        <th>نسبة الربح</th>
                        <th>تاريخ الحفظ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($all_reports): ?>
                        <?php foreach ($all_reports as $i => $r): ?>
                            <tr>
                                <td><?= ($i+1) ?></td>
                                <td><?= htmlspecialchars($r['from_date']) ?> إلى <?= htmlspecialchars($r['to_date']) ?></td>
                                <td><?= number_format($r['total_sales'],2) ?></td>
                                <td><?= number_format($r['total_purchases'],2) ?></td>
                                <td><?= number_format($r['total_expenses'],2) ?></td>
                                <td><?= number_format($r['profit'],2) ?></td>
                                <td><?= $r['profit_percent'] ?>%</td>
                                <td><?= htmlspecialchars($r['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8">لا توجد تقارير محفوظة بعد.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    // طباعة وتصدير التقرير PDF (ورق 80 ملم)
    function printReportPDF() {
        var from = document.getElementById('from').value;
        var to = document.getElementById('to').value;
        var table = document.getElementById('report-table');
        var rows = table.querySelectorAll('tr');
        var tableContent = '';
        for (var i = 0; i < rows.length; i++) {
            tableContent += '<tr>';
            var cells = rows[i].querySelectorAll('th,td');
            for (var j = 0; j < cells.length; j++) {
                tableContent += '<td style="padding:7px;">' + cells[j].textContent + '</td>';
            }
            tableContent += '</tr>';
        }
        var content = `
            <style>
                @media print {
                    html, body {
                        width: 80mm !important;
                        min-width: 80mm !important;
                        max-width: 80mm !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        direction: rtl;
                        background: #fff !important;
                    }
                    table {
                        width: 100% !important;
                        max-width: 80mm !important;
                    }
                    h2 {
                        color: #304ffe;
                        text-align: center;
                        font-size: 20px;
                        margin: 0 0 7px 0;
                    }
                    td, th {
                        font-size: 14px !important;
                    }
                }
            </style>
            <div style="font-family:'Cairo',Arial,Tahoma,sans-serif;direction:rtl;padding:10px;">
                <h2>التقرير المالي</h2>
                <div style="margin-bottom:7px;font-size:15px;color:#1976d2;">
                    <b>الفترة:</b> ${from} إلى ${to}
                </div>
                <table style="width:100%;border:1px solid #304ffe;background:#fff;border-radius:10px;box-shadow:0 2px 10px #e0e0e0;">
                    ${tableContent}
                </table>
            </div>
        `;
        var myWindow = window.open("", "PrintReport", "width=400,height=600");
        myWindow.document.write(content);
        myWindow.document.close();
        myWindow.focus();
        myWindow.print();
    }
    </script>
</body>
</html>