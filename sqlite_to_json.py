import sqlite3
import json

# rkod.db (مثلاً: mydb.db)
db_file = 'rkod.db'

conn = sqlite3.connect(db_file)
cursor = conn.cursor()

# جلب جميع أسماء الجداول
cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
tables = cursor.fetchall()

db_dict = {}

for table_name in tables:
    table = table_name[0]
    cursor.execute(f"SELECT * FROM {table}")
    rows = cursor.fetchall()
    # جلب أسماء الأعمدة
    cursor.execute(f"PRAGMA table_info({table})")
    columns = [col[1] for col in cursor.fetchall()]
    db_dict[table] = [dict(zip(columns, row)) for row in rows]

with open('database.json', 'w', encoding='utf-8') as f:
    json.dump(db_dict, f, ensure_ascii=False, indent=4)

conn.close()