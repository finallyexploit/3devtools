#!/usr/bin/env python3
"""
Decrypt module.bin → patch name → encrypt back
"""
from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad, pad

# Ключ из crypto_capture.log
KEY = bytes.fromhex("643831562A4B0F652559061470494A7D")

def patch_module(input_file, output_file):
    with open(input_file, 'rb') as f:
        data = f.read()
    
    # IV = первые 16 байт
    iv = data[:16]
    encrypted = data[16:]
    
    print(f"[*] IV: {iv.hex()}")
    print(f"[*] Encrypted size: {len(encrypted)} bytes")
    
    # Расшифровываем
    cipher_dec = AES.new(KEY, AES.MODE_CBC, iv)
    decrypted = cipher_dec.decrypt(encrypted)
    
    # Убираем PKCS#7 паддинг
    try:
        decompressed = unpad(decrypted, 16)
        print(f"[+] Unpadded size: {len(decompressed)} bytes")
    except:
        # Пробуем убрать паддинг вручную
        pad_len = decrypted[-1]
        if pad_len <= 16:
            decompressed = decrypted[:-pad_len]
            print(f"[+] Manual unpad: removed {pad_len} bytes")
        else:
            decompressed = decrypted
    
    # Ищем и меняем
    if b'kaktusior' in decompressed:
        print("[+] Found 'kaktusior'")
        decompressed = decompressed.replace(b'kaktusior', b'hassen133')
        print("[+] Replaced with 'hassen133'")
    else:
        print("[!] 'kaktusior' not found!")
        # Сохраняем для анализа
        with open('decrypted_debug.bin', 'wb') as f:
            f.write(decompressed)
        print("[*] Saved decrypted_debug.bin")
        return False
    
    # Добавляем PKCS#7 паддинг
    padded = pad(decompressed, 16)
    print(f"[+] Padded size: {len(padded)} bytes")
    
    # Шифруем обратно
    cipher_enc = AES.new(KEY, AES.MODE_CBC, iv)
    encrypted_new = cipher_enc.encrypt(padded)
    
    # Сохраняем с IV
    output = iv + encrypted_new
    
    with open(output_file, 'wb') as f:
        f.write(output)
    
    print(f"[+] Saved: {output_file}")
    print(f"[+] Size: {len(output)} bytes (original: {len(data)})")
    
    if len(output) == len(data):
        print("[+] Size matches! Ready to use.")
    else:
        print(f"[!] Size changed: {len(output)} vs {len(data)}")
    
    return True

if __name__ == "__main__":
    import sys
    
    input_file = sys.argv[1] if len(sys.argv) > 1 else "module_original.bin"
    output_file = sys.argv[2] if len(sys.argv) > 2 else "module.bin"
    
    print(f"[*] Input:  {input_file}")
    print(f"[*] Output: {output_file}")
    print()
    
    if patch_module(input_file, output_file):
        print("\n[+] SUCCESS! Copy module.bin to data/ folder")
    else:
        print("\n[!] FAILED! Check decrypted_debug.bin")