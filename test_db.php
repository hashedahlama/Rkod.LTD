<?php
try {
    $db = new PDO('sqlite:rkod.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // الأعمدة التي تريد إضافتها مع نوعها
    $columnsToAdd = [
        "supplier_name TEXT",
        "egg_type TEXT",
        "egg_size TEXT",
        "quantity INTEGER",
        "unit_price REAL",
        "total_amount REAL",
        "vat_amount REAL",
        "total_with_vat REAL",
        "purchase_date TEXT"
    ];

    foreach ($columnsToAdd as $column) {
        // استخراج اسم العمود فقط (قبل أول فراغ)
        $colName = explode(' ', $column)[0];
        // تحقق إذا كان العمود موجود مسبقاً
        $result = $db->query("PRAGMA table_info(purchases)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $exists = false;
        foreach ($columns as $col) {
            if ($col['name'] === $colName) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            // إضافة العمود
            $db->exec("ALTER TABLE purchases ADD COLUMN $column");
            echo "تم إضافة العمود: $colName <br>";
        } else {
            echo "العمود $colName موجود مسبقًا <br>";
        }
    }
} catch (PDOException $e) {
    echo "خطأ: " . $e->getMessage();
}
?>
