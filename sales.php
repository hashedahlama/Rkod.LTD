<?php
include 'db.php';
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;

// إعداد الاتصال بقاعدة البيانات
$host = 'localhost';
$db = 'eggs_inventory';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) { die("فشل الاتصال: " . $conn->connect_error); }

// إنشاء قاعدة البيانات إذا لم تكن موجودة
$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

// إنشاء جدول المبيعات مع حقل رقم الفاتورة إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE,
    customer_id INT,
    invoice_number VARCHAR(40),
    egg_type VARCHAR(30),
    egg_size VARCHAR(30),
    quantity INT,
    unit_price DECIMAL(10,2),
    total_amount DECIMAL(12,2),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
)");

// التأكد من وجود مجلد الفواتير
$invoices_dir = __DIR__.'/invoices';
if (!is_dir($invoices_dir)) {
    mkdir($invoices_dir, 0777, true);
}

// جلب العملاء من قاعدة البيانات
$customers = [];
$res = $conn->query("SELECT id, name FROM customers ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $customers[] = $row;
}

$egg_types = ['Small' , 'Medium', 'Large 1', 'Large 2' , 'Large 3' , 'Jambo' , 'صفارين'];
$egg_sizes = ['كرتونة', 'نصف كرتونة'];

$message = '';
$last_sale_id = null;
$last_invoice_number = null;

// ====== دالة توليد رقم فاتورة تلقائي ====== //
function generate_invoice_number($conn) {
    $year = date('Y');
    $res = $conn->query("SELECT invoice_number FROM sales WHERE invoice_number LIKE 'SINV-$year-%' ORDER BY id DESC LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        $last_num = intval(substr($row['invoice_number'], -4));
        $new_num = $last_num + 1;
    } else {
        $new_num = 1;
    }
    return sprintf("SINV-%s-%04d", $year, $new_num);
}

// ====== سلة المبيعات (نافذة منبثقة) ====== //
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['sale_cart'])) $_SESSION['sale_cart'] = [];

if (isset($_POST['add_to_cart'])) {
    if (count($_SESSION['sale_cart']) < 10) {
        $_SESSION['sale_cart'][] = [
            'egg_type' => $_POST['egg_type'],
            'egg_size' => $_POST['egg_size'],
            'quantity' => intval($_POST['quantity']),
            'unit_price' => floatval($_POST['unit_price']),
        ];
    }
}
if (isset($_POST['remove_item']) && isset($_POST['item_index'])) {
    $idx = intval($_POST['item_index']);
    if (isset($_SESSION['sale_cart'][$idx])) {
        array_splice($_SESSION['sale_cart'], $idx, 1);
    }
}

// عند حفظ السلة يتم إنشاء رقم فاتورة موحد لكل المنتجات وإضافته في قاعدة البيانات
if (isset($_POST['save_sale_cart'])) {
    $customer_id = intval($_POST['customer_id']);
    $sale_date = $_POST['sale_date'];
    $items = $_SESSION['sale_cart'];
    if ($customer_id && $sale_date && count($items)) {
        $invoice_number = generate_invoice_number($conn);
        foreach ($items as $item) {
            $egg_type = $item['egg_type'];
            $egg_size = $item['egg_size'];
            $quantity = $item['quantity'];
            $unit_price = $item['unit_price'];
            $total_amount = $quantity * $unit_price;
            $stmt = $conn->prepare("INSERT INTO sales (sale_date, customer_id, invoice_number, egg_type, egg_size, quantity, unit_price, total_amount)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sisssidd', $sale_date, $customer_id, $invoice_number, $egg_type, $egg_size, $quantity, $unit_price, $total_amount);
            $stmt->execute();
            $last_sale_id = $conn->insert_id;
        }
        $message = "<div style='color:green;margin-top:20px;text-align:center;'>تم حفظ جميع المنتجات في جدول المبيعات بنجاح.</div>";
        $last_invoice_number = $invoice_number;
        $_SESSION['sale_cart'] = [];
    } else {
        $message = "<div style='color:red;text-align:center;'>يرجى تحديد العميل وإضافة منتج واحد على الأقل!</div>";
    }
}

// حذف عملية بيع
if (isset($_POST['delete_sale']) && isset($_POST['sale_id'])) {
    $delete_id = intval($_POST['sale_id']);
    $conn->query("DELETE FROM sales WHERE id = $delete_id");
    $message = "<div style='color:red;margin-top:20px;text-align:center;'>تم حذف عملية البيع بنجاح.</div>";
}

// تعديل عملية بيع (عرض البيانات في النموذج)
$edit_sale_data = null;
if (isset($_GET['edit_sale_id'])) {
    $edit_id = intval($_GET['edit_sale_id']);
    $res_edit = $conn->query("SELECT * FROM sales WHERE id=$edit_id");
    if ($res_edit && $res_edit->num_rows) {
        $edit_sale_data = $res_edit->fetch_assoc();
    }
}

// حفظ التعديل
if (isset($_POST['edit_sale'])) {
    $edit_id    = intval($_POST['sale_id']);
    $sale_date  = $_POST['sale_date'];
    $customer_id = intval($_POST['customer_id']);
    $egg_type   = $_POST['egg_type'];
    $egg_size   = $_POST['egg_size'];
    $quantity   = intval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $total_amount = $quantity * $unit_price;
    $invoice_number = $_POST['invoice_number'];

    $stmt = $conn->prepare("UPDATE sales SET sale_date=?, customer_id=?, invoice_number=?, egg_type=?, egg_size=?, quantity=?, unit_price=?, total_amount=? WHERE id=?");
    $stmt->bind_param('sisssiddi', $sale_date, $customer_id, $invoice_number, $egg_type, $egg_size, $quantity, $unit_price, $total_amount, $edit_id);
    if ($stmt->execute()) {
        $message = "<div style='color:green;margin-top:20px;text-align:center;'>تم تعديل عملية البيع بنجاح.</div>";
    } else {
        $message = "<div style='color:red;'>حدث خطأ أثناء تعديل البيع.</div>";
    }
}

// --- جلب المبيعات السابقة ---
$sales = [];
$res2 = $conn->query("
    SELECT sales.*, customers.name AS customer_name
    FROM sales
    LEFT JOIN customers ON sales.customer_id = customers.id
    ORDER BY sales.sale_date DESC, sales.id DESC
");
while ($row = $res2->fetch_assoc()) {
    $sales[] = $row;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>المبيعات</title>
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
        .form-box { border: 1.5px solid #ddd; padding: 28px 28px 10px 28px; width: 460px; margin: 50px auto;
            background: #fff; border-radius: 16px; box-shadow: 0 4px 14px 0 rgba(44, 62, 80, 0.09); }
        .btn { padding: 10px 26px; border-radius: 7px; border: none; background: #304ffe; color: #fff; font-size: 1.12em; font-weight: bold; margin: 7px 0; cursor: pointer; transition: background 0.18s, color 0.18s;}
        .btn:hover { background: #1976d2; color: #fff; }
        .print-btn { background: #43a047; margin-top: 20px; }
        .print-btn:hover { background: #2e7d32; }
        .btn-action { padding: 7px 10px; font-size: 0.97em; margin: 2px 1px;}
        .btn-edit   { background: #ffb300; color:#222;}
        .btn-edit:hover {background:#ff9800;}
        .btn-delete { background:#e53935; color:#fff;}
        .btn-delete:hover {background:#b71c1c;}
        .btn-sandqabd { background:#009688; color:#fff; border:none; border-radius:6px; padding:7px 10px;margin:0 2px;}
        .btn-sandqabd:hover { background:#00796b; }
        .total-box { font-size: 1.18em; background: #e3eafd; border-radius: 7px; display: inline-block; padding: 7px 15px; margin-bottom: 14px; color: #304ffe; }
        .content { margin-right: 240px; padding: 20px; }
        .sidebar { position: fixed; right: 0; top: 0; width: 220px; height: 100%; background: linear-gradient(135deg, #304ffe 60%, #1976d2 100%); color: #fff; box-shadow: -2px 0 8px rgba(60,60,60,0.09); padding-top: 40px; z-index: 100;}
        .sidebar h2 { text-align: center; font-size: 1.2em; margin-bottom: 40px; }
        .sidebar a { display: block; padding: 15px 30px; color: #fff; text-decoration: none; font-size: 1.12em; margin-bottom: 8px; border-radius: 30px 0 0 30px; transition: background 0.2s; }
        .sidebar a.active, .sidebar a:hover { background: rgba(255,255,255,0.13);}
        .sales-table { margin: 35px auto 0 auto; width: 98%; background: #fff; border-radius: 12px; box-shadow: 0 2px 14px 0 rgba(44,62,80,0.09); border-collapse: collapse; overflow: hidden; font-size: 1em; }
        .sales-table th, .sales-table td { border: 1px solid #e0e0e0; padding: 10px 12px; text-align: center; }
        .sales-table th { background: #304ffe; color: #fff; font-size: 1.08em; }
        .sales-table tr:nth-child(even) {background: #f8fafd;}
        .sales-table tr:hover {background: #e3eafd;}
        .cart-table {width:100%; border-collapse:collapse; margin-top:12px;}
        .cart-table th, .cart-table td {border:1px solid #bdbdbd; padding:7px 4px; text-align:center;}
        .cart-table th {background:#304ffe; color:#fff;}
        .cart-table tr:nth-child(even) {background:#f8fafd;}
        .cart-table tr:hover {background:#e3eafd;}
        .modal {display:none;position:fixed;z-index:200;right:0;top:0;width:100vw;height:100vh;overflow:auto;background:rgba(0,0,0,0.22);}
        .modal-content {background:#fff; margin:45px auto; padding:18px 28px 22px 28px; border:1px solid #bdbdbd; width:440px; border-radius:10px; box-shadow:0 4px 24px #222c3630; color:#222;}
        .close {color:#e53935;float:left;font-size:28px;font-weight:bold;cursor:pointer;}
        .close:hover {color:#b71c1c;}
        .remove-btn {background:#e53935; color:#fff; border:none; border-radius:5px; padding:3px 11px; cursor:pointer; font-size:1em;}
        .remove-btn:hover {background:#b71c1c;}
        @media (max-width:900px) {
            .sidebar { width: 100vw; height: 65px; position: fixed; top: 0; right: 0; display: flex; flex-direction: row; align-items: center; padding: 0 12px; z-index: 999;}
            .sidebar h2 { display: none;}
            .sidebar a { display: inline-block; padding: 8px 18px; margin-bottom: 0; border-radius: 30px; font-size: 1em; margin-right: 4px;}
            .content { margin-right: 0; padding: 20px 2vw; }
            .form-box { width: 99vw; max-width: 500px;}
            .sales-table { font-size: 0.94em;}
            .modal-content{width:96vw;}
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script>
        function showCartModal() {
            document.getElementById("cartModal").style.display = "block";
        }
        function closeCartModal() {
            document.getElementById("cartModal").style.display = "none";
        }
        window.onclick = function(event) {
            let modal = document.getElementById("cartModal");
            if (event.target == modal) closeCartModal();
        }
        function setCartCustomer() {
            var cust = document.getElementById('customer_select_main');
            var date = document.getElementById('sale_date_main');
            if(document.getElementById('modal_customer_id'))
                document.getElementById('modal_customer_id').value = cust ? cust.value : '';
            if(document.getElementById('modal_sale_date'))
                document.getElementById('modal_sale_date').value = date ? date.value : '';
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
    <a href="sales.php" class="active"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
    <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> الـتـوريـد</a>
    <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
    <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
</div>
<div class="content">
    <h2>إدارة المبيعات</h2>
    <div class="form-box">
        <form method="post">
            <label>التاريخ:</label>
            <input type="date" name="sale_date" id="sale_date_main" required value="<?php echo date('Y-m-d'); ?>"><br>
            <label>اسم العميل:</label>
            <select name="customer_id" id="customer_select_main" required>
                <option value="">اختر العميل</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select><br>
            <button class="btn" type="button" onclick="setCartCustomer();showCartModal();">فتح سلة المنتجات</button>
        </form>
        <?php if (!empty($message)): ?>
            <div><?php echo $message; ?></div>
            <?php if ($last_sale_id && $last_invoice_number): ?>
                <form action="print_sales_invoice.php" method="get" style="text-align:center;display:inline;">
                    <input type="hidden" name="sale_id" value="<?php echo $last_sale_id; ?>">
                    <input type="hidden" name="invoice_number" value="<?php echo htmlspecialchars($last_invoice_number); ?>">
                    <button class="btn print-btn" type="submit">طباعة الفاتورة</button>
                </form>
                <!-- زر سند قبض -->
                <a href="sandqabd.php?type=sale&id=<?php echo $last_sale_id; ?>&invoice_number=<?php echo htmlspecialchars($last_invoice_number); ?>" class="btn-sandqabd" target="_blank">
                    <span class="material-icons" style="vertical-align:middle;">receipt_long</span> سند قبض
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- نافذة السلة المنبثقة -->
    <div id="cartModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCartModal()">&times;</span>
            <h2 style="color:#304ffe;">سلة المنتجات</h2>
            <!-- رقم الفاتورة في السلة -->
            <?php
            // عرض رقم الفاتورة المرشح القادم
            $future_invoice_number = generate_invoice_number($conn);
            ?>
            <div class="total-box" style="margin-bottom: 14px;">
                رقم الفاتورة القادم: <b><?php echo htmlspecialchars($future_invoice_number); ?></b>
            </div>
            <form method="post" action="">
                <label>نوع البيض:</label>
                <select name="egg_type" required>
                    <?php foreach($egg_types as $type): ?>
                    <option value="<?= htmlspecialchars($type); ?>"><?= htmlspecialchars($type); ?></option>
                    <?php endforeach; ?>
                </select><br>
                <label>الحجم:</label>
                <select name="egg_size" required>
                    <?php foreach($egg_sizes as $size): ?>
                    <option value="<?= htmlspecialchars($size); ?>"><?= htmlspecialchars($size); ?></option>
                    <?php endforeach; ?>
                </select><br>
                <label>الكمية:</label>
                <input type="number" name="quantity" min="1" required><br>
                <label>سعر الوحدة:</label>
                <input type="number" name="unit_price" min="0.01" step="0.01" required><br>
                <button class="btn" type="submit" name="add_to_cart" <?= count($_SESSION['sale_cart'])>=10?'disabled':'' ?>>إضافة للسلة</button>
                <?php if(count($_SESSION['sale_cart'])>=10): ?>
                    <div style="color:#e53935;font-size:1.1em;margin-top:7px;">لا يمكن إضافة أكثر من 10 منتجات.</div>
                <?php endif; ?>
            </form>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>نوع البيض</th>
                        <th>الحجم</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>الإجمالي</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($_SESSION['sale_cart'])): ?>
                    <?php foreach($_SESSION['sale_cart'] as $idx => $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['egg_type']); ?></td>
                        <td><?= htmlspecialchars($item['egg_size']); ?></td>
                        <td><?= htmlspecialchars($item['quantity']); ?></td>
                        <td><?= number_format($item['unit_price'],2); ?></td>
                        <td><?= number_format($item['quantity']*$item['unit_price'],2); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="item_index" value="<?= $idx; ?>">
                                <button class="remove-btn" name="remove_item" type="submit" title="حذف">×</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="color:#888;">لم يتم إضافة أي منتج بعد.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <form method="post" action="">
                <input type="hidden" name="customer_id" value="" id="modal_customer_id">
                <input type="hidden" name="sale_date" value="" id="modal_sale_date">
                <button class="btn" type="submit" name="save_sale_cart" style="background:#43a047;">حفظ السلة</button>
            </form>
        </div>
    </div>
    <script>
    // عند فتح النافذة، عيّن معرف العميل والتاريخ من النموذج الرئيسي
    document.addEventListener('DOMContentLoaded', function() {
        var openModalBtn = document.querySelector('button[onclick*="showCartModal"]');
        if(openModalBtn) {
            openModalBtn.addEventListener('click',function(){
                setCartCustomer();
            });
        }
    });
    </script>

    <!-- جدول المبيعات السابقة -->
    <h2 style="margin-top: 55px; margin-bottom: 18px;">جدول المبيعات السابقة</h2>
    <table class="sales-table">
        <tr>
            <th>م</th>
            <th>رقم الفاتورة</th>
            <th>التاريخ</th>
            <th>اسم العميل</th>
            <th>نوع البيض</th>
            <th>الحجم</th>
            <th>الكمية</th>
            <th>سعر الوحدة</th>
            <th>الإجمالي</th>
            <th>الإجراءات</th>
        </tr>
        <?php if (count($sales)): ?>
            <?php foreach ($sales as $k => $sale): ?>
                <tr>
                    <td><?php echo $k+1; ?></td>
                    <td><?php echo htmlspecialchars($sale['invoice_number']); ?></td>
                    <td><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                    <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($sale['egg_type']); ?></td>
                    <td><?php echo htmlspecialchars($sale['egg_size']); ?></td>
                    <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                    <td><?php echo htmlspecialchars(number_format($sale['unit_price'],2)); ?></td>
                    <td><?php echo htmlspecialchars(number_format($sale['total_amount'],2)); ?></td>
                    <td>
                        <!-- زر طباعة الفاتورة -->
                        <form action="invoice_template.php" method="get" target="_blank" style="display:inline;">
                            <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                            <input type="hidden" name="invoice_number" value="<?php echo htmlspecialchars($sale['invoice_number']); ?>">
                            <button class="btn print-btn btn-action" type="submit" title="طباعة فاتورة">
                                <span class="material-icons" style="font-size:20px;vertical-align:middle;">print</span>
                            </button>
                        </form>
                        <!-- زر سند قبض -->
                        <a href="sandqabd.php?type=sale&id=<?php echo $sale['id']; ?>&invoice_number=<?php echo htmlspecialchars($sale['invoice_number']); ?>" class="btn-sandqabd" target="_blank" title="سند قبض">
                            <span class="material-icons" style="font-size:20px;vertical-align:middle;">receipt_long</span>
                        </a>
                        <form action="sales.php" method="get" style="display:inline;">
                            <input type="hidden" name="edit_sale_id" value="<?php echo $sale['id']; ?>">
                            <button class="btn btn-edit btn-action" type="submit" title="تعديل">
                                <span class="material-icons" style="font-size:20px;vertical-align:middle;">edit</span>
                            </button>
                        </form>
                        <form action="sales.php" method="post" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف عملية البيع؟');">
                            <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                            <button class="btn btn-delete btn-action" type="submit" name="delete_sale" title="حذف">
                                <span class="material-icons" style="font-size:20px;vertical-align:middle;">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="10">لا توجد مبيعات سابقة.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
<?php
$conn->close();
?>