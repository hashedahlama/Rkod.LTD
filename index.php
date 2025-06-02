<?php
// لا حاجة لأي عمليات قاعدة بيانات هنا، فقط واجهة رئيسية
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>مؤوسسة ركود التجارية - الصفحة الرئيسية</title>
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
            justify-content: center;
        }
        .dashboard-title {
            text-align: center;
            font-size: 2.1em;
            color: #374151;
            margin-bottom: 40px;
            letter-spacing: 1px;
        }
        .cards-container {
            display: flex;
            flex-wrap: wrap;
            gap: 35px;
            justify-content: center;
        }
        .dashboard-card {
            width: 210px;
            height: 170px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 18px 0 rgba(44, 62, 80, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            color: #222;
            position: relative;
            transition: transform 0.18s, box-shadow 0.18s;
            border: 1px solid #f0f0f0;
        }
        .dashboard-card:hover {
            transform: translateY(-7px) scale(1.04);
            box-shadow: 0 8px 28px 0 rgba(44, 62, 80, 0.18);
            border-color: #1976d2;
        }
        .dashboard-card .icon {
            font-size: 2.8em;
            margin-bottom: 14px;
            color: #304ffe;
        }
        .dashboard-card .label {
            font-size: 1.13em;
            font-weight: 600;
            text-align: center;
            letter-spacing: .4px;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-right: 0;
                padding: 30px 2vw;
            }
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
    <!-- أيقونات مجانية من https://fonts.googleapis.com/icon?family=Material+Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h2>لوحة التحكم</h2>
        <a href="index.php" class="active"><span class="material-icons" style="vertical-align:middle">home</span> الرئيسية</a>
        <a href="inventory_management.php"><span class="material-icons" style="vertical-align:middle">inventory_2</span> إدارة المخزون</a>
        <a href="suppliers.php"><span class="material-icons" style="vertical-align:middle">local_shipping</span> الموردين</a>
        <a href="customers.php"><span class="material-icons" style="vertical-align:middle">groups</span> العملاء</a>
        <a href="sales.php"><span class="material-icons" style="vertical-align:middle">point_of_sale</span> المبيعات</a>
        <a href="purchases.php"><span class="material-icons" style="vertical-align:middle">shopping_cart</span> المشتريات</a>
        <a href="expenses.php"><span class="material-icons" style="vertical-align:middle">money_off</span> المصروفات</a>
        <a href="reports.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> التقارير</a>
        <a href="settings.php"><span class="material-icons" style="vertical-align:middle">bar_chart</span> الاعدادات</a>
    </div>
    <div class="main-content">
        <div class="dashboard-title">مؤوسسة ركود التجارية</div>
        <div class="cards-container">
            <a class="dashboard-card" href="inventory_management.php">
                <span class="icon material-icons">inventory_2</span>
                <span class="label">صفحة المخزون</span>
            </a>
            <a class="dashboard-card" href="suppliers.php">
                <span class="icon material-icons">local_shipping</span>
                <span class="label">صفحة الموردين</span>
            </a>
            <a class="dashboard-card" href="customers.php">
                <span class="icon material-icons">groups</span>
                <span class="label">صفحة العملاء</span>
            </a>
            <a class="dashboard-card" href="sales.php">
                <span class="icon material-icons">point_of_sale</span>
                <span class="label">صفحة المبيعات</span>
            </a>
            <a class="dashboard-card" href="purchases.php">
                <span class="icon material-icons">shopping_cart</span>
                <span class="label">صفحة المشتريات</span>
            </a>
            <a class="dashboard-card" href="expenses.php">
                <span class="icon material-icons">money_off</span>
                <span class="label">صفحة المصروفات</span>
            </a>
            <a class="dashboard-card" href="reports.php">
                <span class="icon material-icons">bar_chart</span>
                <span class="label">صفحة التقارير</span>
            </a>
            <a class="dashboard-card" href="reports.php">
                <span class="icon material-icons">bar_chart</span>
                <span class="label">صفحة الاعدادات</span>
            </a>
        </div>
    </div>
</body>
</html>