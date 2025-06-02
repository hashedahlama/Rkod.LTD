<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new PDO('sqlite:rkod.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // استلام البيانات من النموذج
        $customer_name    = $_POST['customer_name'] ?? '';
        $receipt_date     = $_POST['receipt_date'] ?? date('Y-m-d');
        $payment_method   = $_POST['payment_method'] ?? '';
        $bank_name        = $_POST['bank_name'] ?? '';
        $transfer_number  = $_POST['transfer_number'] ?? '';
        $transfer_date    = $_POST['transfer_date'] ?? '';

        // استلام المنتجات كمصفوفة وتحويلها إلى JSON
        $items = $_POST['items'] ?? [];
        $items_json = json_encode($items, JSON_UNESCAPED_UNICODE);

        // حساب الإجماليات
        $total = 0;
        foreach ($items as $item) {
            $qty   = floatval($item['qty'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            $total += $qty * $price;
        }
        $vat = $total * 0.15;
        $total_with_vat = $total + $vat;

        // إدخال البيانات
        $stmt = $db->prepare("
            INSERT INTO receipts (
                customer_name, receipt_date, payment_method,
                bank_name, transfer_number, transfer_date,
                items, total, vat, total_with_vat
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $customer_name,
            $receipt_date,
            $payment_method,
            $bank_name,
            $transfer_number,
            $transfer_date,
            $items_json,
            $total,
            $vat,
            $total_with_vat
        ]);

        echo "✅ تم حفظ سند القبض بنجاح.";
    } catch (PDOException $e) {
        echo "❌ خطأ: " . $e->getMessage();
    }
} else {
    echo "❌ يجب إرسال البيانات باستخدام POST.";
}
?>
