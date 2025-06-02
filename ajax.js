// ملف ajax.js
// هذا الملف مخصص لإجراء طلبات AJAX لإضافة المنتجات إلى السلة وعرضها دون إعادة تحميل الصفحة

// عند الضغط على زر إضافة المنتج
document.addEventListener("DOMContentLoaded", function () {
    const addProductForm = document.querySelector("form"); // عدل إذا كان لديك أكثر من فورم
    if (addProductForm) {
        addProductForm.addEventListener("submit", function (e) {
            // نحدد إذا كان الضغط على زر إضافة المنتج وليس "حفظ العملية"
            const clickedBtn = document.activeElement;
            if (clickedBtn && clickedBtn.name === "add_to_cart") {
                e.preventDefault();

                let formData = new FormData(addProductForm);
                formData.append("ajax", "1");

                fetch("sales.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // تحديث جدول السلة فقط
                        document.getElementById("cart-table-body").innerHTML = data.cart_html;
                        // إعادة تعيين الحقول
                        addProductForm.reset();
                    } else if (data.message) {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    alert("حدث خطأ أثناء الاتصال بالخادم");
                });
            }
        });
    }

    // حذف منتج من السلة عبر أجاكس
    document.addEventListener("click", function(e){
        if (e.target && e.target.classList.contains("remove-btn-ajax")) {
            e.preventDefault();
            let idx = e.target.dataset.idx;
            let formData = new FormData();
            formData.append("remove_item", "1");
            formData.append("item_index", idx);
            formData.append("ajax", "1");

            fetch("sales.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("cart-table-body").innerHTML = data.cart_html;
                }
            })
            .catch(err => {
                alert("حدث خطأ أثناء الحذف");
            });
        }
    });
});