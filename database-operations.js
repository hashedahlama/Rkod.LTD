import { database } from './firebase-config';
import { ref, set, onValue, push, remove } from "firebase/database";

// كتابة بيانات
export function writeUserData(userId, name, email) {
  set(ref(database, 'users/' + userId), {
    username: name,
    email: email,
    lastActive: new Date().toISOString()
  });
}

// قراءة بيانات في الوقت الفعلي
export function listenToUserData(userId, callback) {
  const userRef = ref(database, 'users/' + userId);
  onValue(userRef, (snapshot) => {
    const data = snapshot.val();
    callback(data);
  });
}

// إضافة عنصر جديد إلى قائمة
export function addToList(listName, item) {
  const listRef = ref(database, listName);
  push(listRef, item);
}

// حذف بيانات
export function deleteData(path) {
  const dataRef = ref(database, path);
  remove(dataRef);
} 