<?php
$host = 'localhost';
$db = 'eggs_inventory';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) { die("فشل الاتصال: " . $conn->connect_error); }

$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

// تحديث الجدول ليحتوي على التالف والمفقود
$conn->query("CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE,
    egg_type VARCHAR(20),
    package_size VARCHAR(20),
    in_qty INT DEFAULT 0,
    out_qty INT DEFAULT 0,
    damaged_qty INT DEFAULT 0,
    lost_qty INT DEFAULT 0
)");

$columns = [];
$res = $conn->query("SHOW COLUMNS FROM inventory");
while ($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}
if (!in_array('damaged_qty', $columns)) {
    $conn->query("ALTER TABLE inventory ADD COLUMN damaged_qty INT DEFAULT 0");
}
if (!in_array('lost_qty', $columns)) {
    $conn->query("ALTER TABLE inventory ADD COLUMN lost_qty INT DEFAULT 0");
}

// عدل هنا نوع البيض
$egg_types = ['Small', 'Meduim', 'Large1', 'Large2', 'Large3', 'Jambo', 'صفارين'];
$package_sizes = ['كرتونة', 'نصف كرتونة'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_save'])) {
    $date = $_POST['date'];
    $egg_type = $_POST['egg_type'];
    $package_size = $_POST['package_size'];
    $in_qty = intval($_POST['in_qty']);
    $out_qty = intval($_POST['out_qty']);
    $damaged_qty = intval($_POST['damaged_qty']);
    $lost_qty = intval($_POST['lost_qty']);

    // حساب المخزون الحالي قبل العملية
    $sql = "SELECT IFNULL(SUM(in_qty),0) AS total_in, IFNULL(SUM(out_qty),0) AS total_out, IFNULL(SUM(damaged_qty),0) AS total_damaged, IFNULL(SUM(lost_qty),0) AS total_lost 
            FROM inventory WHERE egg_type=? AND package_size=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $egg_type, $package_size);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $current_stock = intval($result['total_in']) - intval($result['total_out']) - intval($result['total_damaged']) - intval($result['total_lost']);

    // التحقق من توفر الكمية أثناء الإخراج/التالف/المفقود
    $total_out_all = $out_qty + $damaged_qty + $lost_qty;
    if ($total_out_all > $current_stock) {
        $message = "<div class='alert-error'>خطأ: مجموع الكمية الخارجة + التالفة + المفقودة ($total_out_all) يتجاوز المخزون المتاح ($current_stock)!</div>";
    } else {
        $insert = $conn->prepare("INSERT INTO inventory (date, egg_type, package_size, in_qty, out_qty, damaged_qty, lost_qty) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param('sssiiii', $date, $egg_type, $package_size, $in_qty, $out_qty, $damaged_qty, $lost_qty);
        if ($insert->execute()) {
            $message = "<div class='alert-success'>تم حفظ العملية بنجاح.</div>";
        } else {
            $message = "<div class='alert-error'>حدث خطأ أثناء الحفظ.</div>";
        }
    }
}

// لجلب كل الكميات لكل نوع وحجم (لجدول الملخص وللتحديث الفوري في الفورم عبر JS)
$stock_data = [];
$sql = "SELECT egg_type, package_size, SUM(in_qty) AS total_in, SUM(out_qty) AS total_out, SUM(damaged_qty) AS total_damaged, SUM(lost_qty) AS total_lost
        FROM inventory
        GROUP BY egg_type, package_size";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $key = $row['egg_type'] . '|' . $row['package_size'];
    $stock_data[$key] = intval($row['total_in']) - intval($row['total_out']) - intval($row['total_damaged']) - intval($row['total_lost']);
}

// لعرض الجدول النهائي
$stock = [];
foreach ($stock_data as $k => $v) {
    $key = str_replace('|', ' - ', $k);
    $stock[$key] = $v;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة المخزون | مؤسسة ركود التجارية</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            margin: 40px;
            margin-right: 240px;
            background: #f5f7fa;
        }
        h2, h3 { color: #304ffe; }
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
            font-size: 1.05em;
            text-align: center;
        }
        th {
            background: #304ffe;
            color: #fff;
            font-size: 1.13em;
        }
        /* نافذة منبثقة */
        .modal {
            display: none; position: fixed; z-index: 1000; right: 0; top: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.18); overflow:auto;
        }
        .modal-content {
            background: #fff; margin:60px auto; padding:26px 32px 16px 32px; border:1.5px solid #bdbdbd;
            width: 400px; border-radius: 12px; box-shadow:0 6px 30px #343a4060; color: #222;
        }
        .close {color:#e53935;float:left;font-size:28px;font-weight:bold;cursor:pointer;}
        .close:hover {color:#b71c1c;}
        .modal label {
            display: inline-block;
            width: 120px;
            font-size: 1.09em;
            margin-bottom: 7px;
        }
        .modal input[type="text"], .modal input[type="date"], .modal input[type="number"], .modal select {
            width: 70%;
            font-size: 1.09em;
            padding: 9px 8px;
            border: 1.5px solid #bdbdbd;
            border-radius: 8px;
            margin-bottom: 13px;
            transition: border 0.2s;
            background: #fff;
            box-sizing: border-box;
        }
        .modal input[type="text"]:focus, .modal input[type="date"]:focus, .modal input[type="number"]:focus, .modal select:focus {
            border: 2px solid #304ffe;
            outline: none;
            background: #f1f7fe;
        }
        .modal input[readonly] {
            background: #f0f0f0;
            color: #666;
        }
        @media (max-width:900px) {
            body { margin-right: 0; }
            table { font-size: 0.98em;}
            .modal-content { width: 97vw;}
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
        var stockData = <?php echo json_encode($stock_data, JSON_UNESCAPED_UNICODE); ?>;
        function updateCurrentStock() {
            var eggType = document.getElementById('modal_egg_type').value;
            var packageSize = document.getElementById('modal_package_size').value;
            var key = eggType + "|" + packageSize;
            var current = stockData[key] !== undefined ? stockData[key] : 0;
            document.getElementById('modal_current_stock').value = current;
        }
        function showModal() {
            document.getElementById("addModal").style.display = "block";
            updateCurrentStock();
        }
        function closeModal() {
            document.getElementById("addModal").style.display = "none";
        }
        window.onclick = function(event) {
            let modal = document.getElementById("addModal");
            if (event.target == modal) closeModal();
        }
        window.onload = function() {
            if(document.getElementById('modal_egg_type')) {
                document.getElementById('modal_egg_type').addEventListener('change', updateCurrentStock);
            }
            if(document.getElementById('modal_package_size')) {
                document.getElementById('modal_package_size').addEventListener('change', updateCurrentStock);
            }
        }
    </script>
</head>
<body>
<!-- الشريط الجانبي -->
<div class="sidebar">
    <h2>لوحة التحكم</h2>
    <a href="index.php"><span class="material-icons" style="vertical-align:middle">home</span> الرئيسية</a>
    <a href="inventory_management.php" class="active"><span class="material-icons" style="vertical-align:middle">inventory_2</span> إدارة المخزون</a>
    <a href="suppliers.php"><span class="material-icons" style="vertical-align:middle">local_shipping</span> الموردين</a>
    <a href="customers.php"><span class="material-icons" style="vertical-align:middle">groups</span> العملاء</a>
    <a href="sales.php"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
    <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> المشتريات</a>
    <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
    <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
</div>

    <h2>إدارة المخزون</h2>
    <?php echo $message; ?>
    <button class="btn" onclick="showModal()">
        <span class="material-icons" style="font-size:18px;vertical-align:middle;">add_circle</span> إضافة للمخزون
    </button>

    <!-- نافذة منبثقة لإضافة المخزون -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 style="color:#304ffe;text-align:center;margin-bottom:18px;">إضافة عملية للمخزون</h3>
            <form method="post" autocomplete="off">
                <label>التاريخ:</label>
                <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>"><br>
                <label>نوع البيض:</label>
                <select name="egg_type" id="modal_egg_type" required>
                    <?php foreach ($egg_types as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select><br>
                <label>حجم العبوة:</label>
                <select name="package_size" id="modal_package_size" required>
                    <?php foreach ($package_sizes as $size): ?>
                        <option value="<?php echo $size; ?>"><?php echo $size; ?></option>
                    <?php endforeach; ?>
                </select><br>
                <label>المخزون الحالي:</label>
                <input type="number" id="modal_current_stock" readonly style="background:#f6f6f6;color:#304ffe;"><br>
                <label>الكمية الواردة:</label>
                <input type="number" name="in_qty" min="0" value="0"><br>
                <label>الكمية الخارجة:</label>
                <input type="number" name="out_qty" min="0" value="0"><br>
                <label>الكمية التالفة:</label>
                <input type="number" name="damaged_qty" min="0" value="0"><br>
                <label>الكمية المفقودة:</label>
                <input type="number" name="lost_qty" min="0" value="0"><br>
                <button type="submit" class="btn" name="modal_save">
                    <span class="material-icons" style="font-size:18px;vertical-align:middle;">save</span> حفظ
                </button>
            </form>
        </div>
    </div>

    <h3>الكميات المتبقية لكل نوع وحجم</h3>
    <table>
        <tr>
            <th>نوع البيض - الحجم</th>
            <th>الكمية المتبقية</th>
        </tr>
        <?php foreach ($stock as $key => $qty): ?>
            <tr>
                <td><?php echo $key; ?></td>
                <td><?php echo $qty; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <script>
        // تحديث المخزون الحالي تلقائياً عند فتح النافذة
        document.querySelector('.btn[onclick="showModal()"]').addEventListener('click', function() {
            setTimeout(updateCurrentStock, 100);
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>