#!/usr/bin/env python3
"""
remove_inserts.py

Удаляет из файла(ов) все SQL-операторы INSERT ... VALUES ...;
в которых встречается хотя бы одна из указанных "упоминаний" (подстрок).

Примеры упоминаний: "@", "@ivan", "example.com", "DELETE_ME_TAG" и т.д.

По умолчанию для каждого затронутого файла создаётся резервная копия с расширением .bak
"""

import re
import argparse
from pathlib import Path
import shutil
import sys

# Регулярка: от "INSERT" до ближайшей точки с запятой (включая многострочный текст)
# Используем ленивый квантификатор .*? и флаг DOTALL, IGNORECASE
INSERT_RE = re.compile(r'INSERT\s+INTO\b.*?;', re.IGNORECASE | re.DOTALL)

def process_text(text: str, mentions: list[str]) -> tuple[str, int]:
    """
    Удаляет все INSERT...; блоки, которые содержат любую из mentions.
    Возвращает (новый_текст, кол-во_удалённых_блоков).
    """
    removed = 0
    parts = []
    last_end = 0

    for m in INSERT_RE.finditer(text):
        block = m.group(0)
        block_lower = block.lower()
        # если любая упомянутая подстрока входит в блок — помечаем на удаление
        if any((mention.lower() in block_lower) for mention in mentions):
            removed += 1
            # пропускаем этот блок (не добавляем в parts)
        else:
            # сохраняем промежуток текста от last_end до начала блока и сам блок
            parts.append(text[last_end:m.start()])
            parts.append(block)
        last_end = m.end()

    # добавляем оставшийся хвост
    parts.append(text[last_end:])
    new_text = ''.join(parts)
    return new_text, removed

def process_file(path: Path, mentions: list[str], backup: bool = True, inplace: bool = True):
    text = path.read_text(encoding='utf-8', errors='replace')
    new_text, removed = process_text(text, mentions)
    if removed == 0:
        return removed
    if backup:
        bak = path.with_suffix(path.suffix + '.bak')
        # если bak уже существует — не перезаписываем; добавляем числовой суффикс
        i = 1
        candidate = bak
        while candidate.exists():
            candidate = path.with_suffix(path.suffix + f'.bak{i}')
            i += 1
        shutil.copy2(path, candidate)
    if inplace:
        path.write_text(new_text, encoding='utf-8')
    return removed

def main():
    p = argparse.ArgumentParser(description="Удаляет INSERT INTO ... VALUES ...; содержащие указанные упоминания.")
    p.add_argument('paths', nargs='+', help="Файл или папка (если папка — обработает все файлы с указанным расширением).")
    p.add_argument('-m', '--mentions', required=True,
                   help="Запятая-разделённый список подстрок, которые считаются 'упоминаниями'. Пример: '@,@ivan,example.com'")
    p.add_argument('-e', '--ext', default='.sql',
                   help="Расширение файлов для обработки, если указан путь — папка. По умолчанию .sql")
    p.add_argument('--no-backup', dest='backup', action='store_false', help="Отключить создание резервных копий .bak")
    p.add_argument('--dry-run', dest='dry', action='store_true', help="Не сохранять изменения, только показать отчёт")
    args = p.parse_args()

    mentions = [m.strip() for m in args.mentions.split(',') if m.strip()]
    if not mentions:
        print("Ошибка: список упоминаний пуст.", file=sys.stderr)
        sys.exit(2)

    total_removed = 0
    total_files = 0

    for pstr in args.paths:
        pth = Path(pstr)
        if pth.is_dir():
            for f in pth.rglob(f'*{args.ext}'):
                total_files += 1
                removed = 0
                if args.dry:
                    # dry-run: подсчитаем, не изменяя файлы
                    text = f.read_text(encoding='utf-8', errors='replace')
                    _, removed = process_text(text, mentions)
                else:
                    removed = process_file(f, mentions, backup=args.backup, inplace=True)
                if removed:
                    print(f"{f}: удалено {removed} INSERT-ов")
                total_removed += removed
        elif pth.is_file():
            total_files += 1
            if args.dry:
                text = pth.read_text(encoding='utf-8', errors='replace')
                _, removed = process_text(text, mentions)
            else:
                removed = process_file(pth, mentions, backup=args.backup, inplace=True)
            if removed:
                print(f"{pth}: удалено {removed} INSERT-ов")
            total_removed += removed
        else:
            print(f"Пропущен (не найден): {pth}", file=sys.stderr)

    print(f"\nГотово. Файлов обработано: {total_files}. Всего удалено INSERT-ов: {total_removed}.")
    if args.dry:
        print("Dry-run: файлы не были изменены.")

if __name__ == '__main__':
    main()
