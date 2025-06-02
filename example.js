import { writeUserData, listenToUserData, addToList, deleteData } from './database-operations';

// مثال على كتابة بيانات مستخدم
writeUserData('user123', 'أحمد', 'ahmed@example.com');

// مثال على الاستماع للتغييرات في بيانات المستخدم
listenToUserData('user123', (userData) => {
  if (userData) {
    console.log('بيانات المستخدم:', userData);
  } else {
    console.log('لا توجد بيانات للمستخدم');
  }
});

// مثال على إضافة عنصر إلى قائمة
addToList('tasks', {
  title: 'مهمة جديدة',
  description: 'وصف المهمة',
  createdAt: new Date().toISOString()
});

// مثال على حذف بيانات
// deleteData('users/user123'); 