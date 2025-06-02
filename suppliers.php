<?php
$host = 'localhost';
$db = 'eggs_inventory';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8");

// إضافة مورد جديد
$message = '';
if (isset($_POST['add_supplier'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['mobile']);
    $address = trim($_POST['address']);

    if ($name != "") {
        $stmt = $conn->prepare("INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $phone, $address);
        if ($stmt->execute()) {
            $message = "<div style='color:green;text-align:center;margin-top:10px;'>تمت إضافة المورد بنجاح.</div>";
        } else {
            $message = "<div style='color:red;text-align:center;margin-top:10px;'>حدث خطأ أثناء إضافة المورد.</div>";
        }
    } else {
        $message = "<div style='color:red;text-align:center;margin-top:10px;'>يرجى إدخال اسم المورد.</div>";
    }
}

// حذف مورد
if (isset($_POST['delete_supplier']) && isset($_POST['supplier_id'])) {
    $delete_id = intval($_POST['supplier_id']);
    $conn->query("DELETE FROM suppliers WHERE id = $delete_id");
    $message = "<div style='color:red;text-align:center;margin-top:10px;'>تم حذف المورد بنجاح.</div>";
}

// تعديل مورد
$edit_supplier_data = null;
if (isset($_GET['edit_supplier_id'])) {
    $edit_id = intval($_GET['edit_supplier_id']);
    $res_edit = $conn->query("SELECT * FROM suppliers WHERE id=$edit_id");
    if ($res_edit && $res_edit->num_rows) {
        $edit_supplier_data = $res_edit->fetch_assoc();
    }
}

// حفظ التعديل
if (isset($_POST['edit_supplier'])) {
    $edit_id = intval($_POST['supplier_id']);
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    $stmt = $conn->prepare("UPDATE suppliers SET name=?, phone=?, address=? WHERE id=?");
    $stmt->bind_param('sssi', $name, $phone, $address, $edit_id);
    if ($stmt->execute()) {
        $message = "<div style='color:green;text-align:center;margin-top:10px;'>تم تعديل بيانات المورد بنجاح.</div>";
    } else {
        $message = "<div style='color:red;text-align:center;margin-top:10px;'>حدث خطأ أثناء التعديل.</div>";
    }
}

// جلب الموردين
$suppliers = [];
$res = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    $suppliers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الموردين</title>
    <!-- ... باقي التنسيقات كما في كودك ... -->
    <style>
        body { font-family: 'Cairo', Tahoma, Arial, sans-serif; background: #f5f7fa; direction: rtl; margin: 0;}
        h2 { color: #304ffe; text-align: center; margin-top: 35px; }
        label { display: inline-block; width: 110px; font-size: 1.08em; }
        input[type="text"], input[type="tel"] {
            width: 80%; font-size: 1.08em; padding: 8px 8px; border: 1px solid #bdbdbd; border-radius: 7px;
            margin-bottom: 10px; transition: border 0.2s; background: #fff; box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="tel"]:focus {
            border: 2px solid #304ffe; outline: none; background: #f1f7fe;
        }
        textarea {
            width: 80%; font-size: 1.08em; padding: 8px 8px; border: 1px solid #bdbdbd; border-radius: 7px;
            margin-bottom: 13px; transition: border 0.2s; background: #fff; box-sizing: border-box;
        }
        textarea:focus {
            border: 2px solid #304ffe; outline: none; background: #f1f7fe;
        }
        .form-box { border: 1.5px solid #ddd; padding: 28px 28px 10px 28px; width: 420px; margin: 32px auto 18px auto;
            background: #fff; border-radius: 16px; box-shadow: 0 4px 14px 0 rgba(44, 62, 80, 0.09); }
        .btn { padding: 9px 23px; border-radius: 7px; border: none; background: #304ffe; color: #fff; font-size: 1.05em; font-weight: bold; margin: 7px 0; cursor: pointer; transition: background 0.18s, color 0.18s;}
        .btn:hover { background: #1976d2; color: #fff; }
        .btn-action { padding: 6px 12px; font-size: 0.95em; margin: 2px 1px;}
        .btn-edit   { background: #ffb300; color:#222;}
        .btn-edit:hover {background:#ff9800;}
        .btn-delete { background:#e53935; color:#fff;}
        .btn-delete:hover {background:#b71c1c;}
        .content { margin-right: 230px; padding: 15px; }
        .sidebar { position: fixed; right: 0; top: 0; width: 210px; height: 100%; background: linear-gradient(135deg, #304ffe 60%, #1976d2 100%); color: #fff; box-shadow: -2px 0 8px rgba(60,60,60,0.09); padding-top: 40px; z-index: 100;}
        .sidebar h2 { text-align: center; font-size: 1.1em; margin-bottom: 35px; }
        .sidebar a { display: block; padding: 13px 22px; color: #fff; text-decoration: none; font-size: 1em; margin-bottom: 8px; border-radius: 30px 0 0 30px; transition: background 0.2s; }
        .sidebar a.active, .sidebar a:hover { background: rgba(255,255,255,0.13);}
        .suppliers-table { margin: 20px auto 0 auto; width: 98%; background: #fff; border-radius: 10px; box-shadow: 0 2px 14px 0 rgba(44,62,80,0.08); border-collapse: collapse; overflow: hidden; font-size: 0.99em; }
        .suppliers-table th, .suppliers-table td { border: 1px solid #e0e0e0; padding: 10px 8px; text-align: center; }
        .suppliers-table th { background: #304ffe; color: #fff; font-size: 1.05em; }
        .suppliers-table tr:nth-child(even) {background: #f8fafd;}
        .suppliers-table tr:hover {background: #e3eafd;}
        @media (max-width:900px) {
            .sidebar { width: 100vw; height: 65px; position: fixed; top: 0; right: 0; display: flex; flex-direction: row; align-items: center; padding: 0 12px; z-index: 999;}
            .sidebar h2 { display: none;}
            .sidebar a { display: inline-block; padding: 8px 15px; margin-bottom: 0; border-radius: 30px; font-size: 1em; margin-right: 4px;}
            .content { margin-right: 0; padding: 20px 2vw; }
            .form-box { width: 99vw; max-width: 420px;}
            .suppliers-table { font-size: 0.92em;}
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<div class="sidebar">
    <h2>لوحة التحكم</h2>
    <a href="index.php"><span class="material-icons" style="vertical-align:middle">home</span> الرئيسية</a>
    <a href="inventory_management.php"><span class="material-icons" style="vertical-align:middle">inventory_2</span> إدارة المخزون</a>
    <a href="suppliers.php" class="active"><span class="material-icons" style="vertical-align:middle">local_shipping</span> الموردين</a>
    <a href="customers.php"><span class="material-icons" style="vertical-align:middle">groups</span> العملاء</a>
    <a href="sales.php"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
    <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> الـتـوريـد</a>
    <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
    <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
</div>
<div class="content">
    <h2>إدارة الموردين</h2>
    <div class="form-box">
        <?php if ($edit_supplier_data): ?>
        <form method="post">
            <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier_data['id']; ?>">
            <label>اسم الـمـورد:</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_supplier_data['name']); ?>"><br>
            <label>رقم الجوال:</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_supplier_data['phone']); ?>"><br>
            <label>العنوان:</label>
            <textarea name="address"><?php echo htmlspecialchars($edit_supplier_data['address']); ?></textarea><br>
            <button class="btn btn-edit" type="submit" name="edit_supplier">تعديل</button>
            <a href="suppliers.php" class="btn" style="background:#ccc;color:#222;">الغاء</a>
        </form>
        <?php else: ?>
        <form method="post">
            <label>اسم المورد:</label>
            <input type="text" name="name" required><br>
            <label>رقم الجوال:</label>
            <input type="tel" name="phone"><br>
            <label>العنوان:</label>
            <textarea name="address"></textarea><br>
            <button class="btn" type="submit" name="add_supplier">إضافة</button>
        </form>
        <?php endif; ?>
        <?php if ($message) echo $message;?>
    </div>
    <h2 style="margin-top: 40px; margin-bottom: 18px;">جدول الموردين</h2>
    <table class="suppliers-table">
        <tr>
            <th>م</th>
            <th>اسم المورد</th>
            <th>رقم الجوال</th>
            <th>العنوان</th>
            <th>الإجراءات</th>
        </tr>
        <?php if (count($suppliers)): ?>
            <?php foreach ($suppliers as $k => $supplier): ?>
                <tr>
                    <td><?php echo $k+1; ?></td>
                    <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                    <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                    <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                    <td>
                        <form action="suppliers.php" method="get" style="display:inline;">
                            <input type="hidden" name="edit_supplier_id" value="<?php echo $supplier['id']; ?>">
                            <button class="btn btn-edit btn-action" type="submit" title="تعديل">
                                <span class="material-icons" style="font-size:18px;vertical-align:middle;">edit</span>
                            </button>
                        </form>
                        <form action="suppliers.php" method="post" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف المورد؟');">
                            <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                            <button class="btn btn-delete btn-action" type="submit" name="delete_supplier" title="حذف">
                                <span class="material-icons" style="font-size:18px;vertical-align:middle;">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">لا توجد بيانات موردين.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
<?php
$conn->close();
?>