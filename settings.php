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

// إنشاء جدول الإعدادات إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

// جلب البريد الإلكتروني الحالي
$email = '';
$res = $conn->query("SELECT email FROM settings ORDER BY id DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $email = $row['email'];
}

// حفظ البريد الإلكتروني
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $new_email = trim($_POST['email']);
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "يرجى إدخال بريد إلكتروني صحيح.";
    } else {
        // إذا كان هناك بريد سابق قم بالتحديث، وإلا أضف جديد
        if ($email) {
            $stmt = $conn->prepare("UPDATE settings SET email=? WHERE id=(SELECT id FROM (SELECT id FROM settings ORDER BY id DESC LIMIT 1) as t)");
            $stmt->bind_param("s", $new_email);
        } else {
            $stmt = $conn->prepare("INSERT INTO settings (email) VALUES (?)");
            $stmt->bind_param("s", $new_email);
        }
        if ($stmt->execute()) {
            $success = true;
            $email = $new_email;
        } else {
            $error = "حدث خطأ أثناء الحفظ: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الإعدادات | مؤسسة ركود التجارية</title>
    <style>
        body {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            margin: 0;
            background: #f5f7fa;
            direction: rtl;
        }
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
        .main-content {
            margin-right: 260px;
            padding: 50px 30px 30px 30px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .page-title {
            text-align: center;
            font-size: 2em;
            color: #374151;
            margin-bottom: 35px;
            letter-spacing: 1px;
        }
        .form-container {
            background: #fff;
            border-radius: 16px;
            padding: 32px 30px 24px 30px;
            box-shadow: 0 4px 18px 0 rgba(44, 62, 80, 0.08);
            border: 1px solid #f0f0f0;
            max-width: 540px;
            width: 100%;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
            min-width: 160px;
        }
        label {
            font-size: 15px;
            margin-bottom: 6px;
            color: #304ffe;
            font-weight: 600;
            letter-spacing: .2px;
        }
        input {
            padding: 9px 12px;
            font-size: 16px;
            border-radius: 7px;
            border: 1px solid #ddd;
            background: #fcfcfc;
            outline: none;
            box-shadow: none;
            transition: border 0.2s, box-shadow 0.2s;
        }
        input:focus {
            border: 1.5px solid #304ffe;
            background: #f3f8ff;
        }
        .form-actions {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-top: 12px;
        }
        .btn-submit {
            background: linear-gradient(90deg,#304ffe 80%,#1976d2 100%);
            color: #fff;
            padding: 13px 35px;
            font-size: 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 2px 8px #304ffe22;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #1976d2;
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
        @media print {
            .sidebar, .main-content > .form-container, .page-title { display: none !important; }
            body { background: #fff; }
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
        <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> المشتريات</a>
        <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
        <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
        <a href="settings.php" class="active"><span class="material-icons" style="vertical-align:middle">settings</span> الإعدادات</a>
    </div>
    <div class="main-content">
        <div class="page-title">الإعدادات العامة</div>
        <div class="form-container">
            <?php if ($success): ?>
                <div class="alert-success">تم حفظ البريد الإلكتروني بنجاح!</div>
            <?php elseif ($error): ?>
                <div class="alert-error"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="email">البريد الإلكتروني لاستلام التقارير الأسبوعية</label>
                    <input type="email" name="email" id="email" required value="<?= htmlspecialchars($email) ?>" placeholder="example@email.com">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit" name="save">
                        <span class="material-icons" style="font-size:18px;vertical-align:middle;">save</span>
                        حفظ البريد الإلكتروني
                    </button>
                </div>
            </form>
        </div>
        <div style="margin-top:36px;max-width:600px;font-size:15px;color:#616161;line-height:2;">
            <b>ملاحظة:</b> سيتم إرسال تقارير PDF و Excel بشكل أسبوعي إلى البريد الإلكتروني المسجل أعلاه، وتشمل جميع بيانات الأقسام.
        </div>
    </div>
</body>
</html>