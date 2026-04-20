import sqlite3
import os

def check_sql_syntax(input_file, output_file):
    # Перевіряємо, чи існує файл
    if not os.path.exists(input_file):
        print(f"Помилка: Файл {input_file} не знайдено.")
        return

    with open(input_file, 'r', encoding='utf-8') as f:
        sql_script = f.read()

    # Створюємо підключення до бази в оперативній пам'яті
    conn = sqlite3.connect(":memory:")
    cursor = conn.cursor()
    
    result_message = ""
    
    try:
        # Намагаємося виконати скрипт
        # executescript дозволяє виконувати кілька команд одночасно
        cursor.executescript(sql_script)
        result_message = "✅ SQL-код успішно перевірено. Синтаксичних помилок не виявлено."
        print("Все ок!")
        
    except sqlite3.Error as e:
        # Якщо виникла помилка, записуємо її текст
        result_message = f"❌ Помилка в SQL синтаксисі:\n{str(e)}"
        print(f"Знайдено помилку: {e}")
        
    finally:
        conn.close()

    # Записуємо результат у TXT файл
    with open(output_file, 'w', encoding='utf-8') as f_out:
        f_out.write(result_message)
    
    print(f"Результат збережено у {output_file}")

# Запуск
if __name__ == "__main__":
    check_sql_syntax("data.sql", "result.txt")