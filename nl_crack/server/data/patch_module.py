#!/usr/bin/env python3


from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad, pad
import sys
import os
KEY = bytes.fromhex("643831562A4B0F652559061470494A7D")

def hexdump(data, offset=0, length=256):
    for i in range(0, min(length, len(data)), 16):
        hex_str = ' '.join(f'{b:02X}' for b in data[offset+i:offset+i+16])
        ascii_str = ''.join(chr(b) if 32 <= b < 127 else '.' for b in data[offset+i:offset+i+16])
        print(f"  {offset+i:08X}: {hex_str:<48} {ascii_str}")

def search_pattern(data, pattern, case_sensitive=True):
    results = []
    search_bytes = pattern.encode() if isinstance(pattern, str) else pattern
    
    if not case_sensitive and isinstance(pattern, str):
        data_lower = data.lower()
        search_bytes = search_bytes.lower()
        pos = 0
        while True:
            pos = data_lower.find(search_bytes, pos)
            if pos == -1:
                break
            results.append(pos)
            pos += 1
    else:
        pos = 0
        while True:
            pos = data.find(search_bytes, pos)
            if pos == -1:
                break
            results.append(pos)
            pos += 1
    
    return results

def patch_module(input_file, output_file):
    print(f"[*] Loading: {input_file}")
    with open(input_file, 'rb') as f:
        data = f.read()
    
    print(f"[*] File size: {len(data)} bytes")
    
    iv = data[:16]
    encrypted = data[16:]
    
    print(f"[*] IV: {iv.hex()}")
    
    cipher_dec = AES.new(KEY, AES.MODE_CBC, iv)
    decrypted = cipher_dec.decrypt(encrypted)

    try:
        decompressed = unpad(decrypted, 16)
        print(f"[+] Unpadded: {len(decompressed)} bytes")
    except:
        pad_len = decrypted[-1]
        if pad_len <= 16:
            decompressed = decrypted[:-pad_len]
            print(f"[+] Manual unpad: removed {pad_len} bytes")
        else:
            decompressed = decrypted
            print(f"[!] Could not unpad, using raw data")
    
    print(f"[*] Data size: {len(decompressed)} bytes\n")
    
    while True:
        print("=" * 60)
        print("  NEVERLOSE MODULE PATCHER - INTERACTIVE MODE")
        print("=" * 60)
        print("  1. Search for string")
        print("  2. Search for hex bytes")
        print("  3. Replace string")
        print("  4. Replace hex bytes")
        print("  5. Show hexdump at offset")
        print("  6. Save and exit")
        print("  7. Exit without saving")
        print("=" * 60)
        
        choice = input("Select option [1-7]: ").strip()
        
        if choice == '1':
            search_str = input("Enter string to search: ")
            case_sens = input("Case sensitive? [Y/n]: ").strip().lower()
            case_sensitive = case_sens != 'n'
            
            results = search_pattern(decompressed, search_str, case_sensitive)
            
            if results:
                print(f"\n[+] Found {len(results)} match(es):")
                for i, pos in enumerate(results[:20]): 
                    print(f"  [{i}] Offset: 0x{pos:08X} ({pos})")
                    start = max(0, pos - 20)
                    end = min(len(decompressed), pos + len(search_str) + 30)
                    context = decompressed[start:end]
                    match_pos = pos - start
                    before = context[:match_pos]
                    match = context[match_pos:match_pos+len(search_str)]
                    after = context[match_pos+len(search_str):]
                    
                    printable_before = ''.join(chr(b) if 32 <= b < 127 else '.' for b in before)
                    printable_match = ''.join(chr(b) if 32 <= b < 127 else '.' for b in match)
                    printable_after = ''.join(chr(b) if 32 <= b < 127 else '.' for b in after)
                    
                    print(f"      Context: {printable_before}[{printable_match}]{printable_after}")
                
                if len(results) > 20:
                    print(f"  ... and {len(results) - 20} more")
            else:
                print(f"\n[!] '{search_str}' not found")
            
            print()
            
        elif choice == '2':
            hex_str = input("Enter hex bytes (e.g., '6B616B747573696F72'): ").strip().replace(' ', '')
            try:
                search_bytes = bytes.fromhex(hex_str)
                results = search_pattern(decompressed, search_bytes, True)
                
                if results:
                    print(f"\n[+] Found {len(results)} match(es):")
                    for i, pos in enumerate(results[:10]):
                        print(f"  [{i}] Offset: 0x{pos:08X} ({pos})")
                        hexdump(decompressed, max(0, pos - 16), 48)
                else:
                    print(f"\n[!] Hex pattern not found")
            except ValueError:
                print(f"\n[!] Invalid hex string")
            print()
            
        elif choice == '3':
            old_str = input("Enter string to replace: ")
            
            results = search_pattern(decompressed, old_str, True)
            if not results:
                print(f"\n[!] '{old_str}' not found")
                print()
                continue
            
            print(f"\n[+] Found {len(results)} occurrence(s)")
            new_str = input("Enter new string: ")
            
            if len(new_str) != len(old_str):
                print(f"\n[!] WARNING: Length mismatch!")
                print(f"    Old: {len(old_str)} chars")
                print(f"    New: {len(new_str)} chars")
                confirm = input("This may corrupt data. Continue? [y/N]: ").strip().lower()
                if confirm != 'y':
                    print("[*] Cancelled")
                    print()
                    continue
            
            print(f"\n[*] Preview:")
            print(f"    Old: {old_str}")
            print(f"    New: {new_str}")
            confirm = input("Apply? [y/N]: ").strip().lower()
            
            if confirm == 'y':
                count = decompressed.count(old_str.encode())
                decompressed = decompressed.replace(old_str.encode(), new_str.encode())
                print(f"[+] Replaced {count} occurrence(s)")
            else:
                print("[*] Cancelled")
            print()
            
        elif choice == '4':
            old_hex = input("Enter hex bytes to replace: ").strip().replace(' ', '')
            try:
                old_bytes = bytes.fromhex(old_hex)
                results = search_pattern(decompressed, old_bytes, True)
                
                if not results:
                    print(f"\n[!] Hex pattern not found")
                    print()
                    continue
                
                print(f"\n[+] Found {len(results)} occurrence(s)")
                new_hex = input("Enter new hex bytes: ").strip().replace(' ', '')
                new_bytes = bytes.fromhex(new_hex)
                
                if len(old_bytes) != len(new_bytes):
                    print(f"\n[!] WARNING: Length mismatch!")
                    print(f"    Old: {len(old_bytes)} bytes")
                    print(f"    New: {len(new_bytes)} bytes")
                    confirm = input("Continue? [y/N]: ").strip().lower()
                    if confirm != 'y':
                        print("[*] Cancelled")
                        print()
                        continue
                
                confirm = input("Apply? [y/N]: ").strip().lower()
                if confirm == 'y':
                    decompressed = decompressed.replace(old_bytes, new_bytes)
                    print(f"[+] Replaced successfully")
                else:
                    print("[*] Cancelled")
            except ValueError:
                print(f"\n[!] Invalid hex string")
            print()
            
        elif choice == '5':
            offset_str = input("Enter offset (hex or dec): ").strip()
            try:
                if offset_str.startswith('0x') or offset_str.startswith('0X'):
                    offset = int(offset_str, 16)
                else:
                    offset = int(offset_str)
                
                length_str = input("Bytes to show [256]: ").strip()
                length = int(length_str) if length_str else 256
                
                print(f"\n[*] Hexdump at 0x{offset:08X}:\n")
                hexdump(decompressed, offset, length)
            except ValueError:
                print(f"\n[!] Invalid offset")
            print()
            
        elif choice == '6':
            print("\n[*] Packing and encrypting...")
            
            padded = pad(decompressed, 16)
            print(f"[+] Padded: {len(padded)} bytes")
            
            cipher_enc = AES.new(KEY, AES.MODE_CBC, iv)
            encrypted_new = cipher_enc.encrypt(padded)
            
            output = iv + encrypted_new
            
            with open(output_file, 'wb') as f:
                f.write(output)
            
            print(f"[+] Saved: {output_file}")
            print(f"[+] Size: {len(output)} bytes (original: {len(data)})")
            
            if len(output) == len(data):
                print("[+] Size matches original - safe to use!")
            else:
                print(f"[!] Size changed: {len(output)} vs {len(data)} - may cause issues!")
            
            print("\n[*] Done!")
            return True
            
        elif choice == '7':
            print("\n[*] Exiting without saving")
            return False

def main():
    print("=" * 60)
    print("  NEVERLOSE MODULE.BIN INTERACTIVE PATCHER")
    print("  cracked by hassen team | t.me/an5ropic")
    print("=" * 60)
    print()
    
    if len(sys.argv) >= 2:
        input_file = sys.argv[1]
    else:
        input_file = input("Input file [module.bin]: ").strip()
        if not input_file:
            input_file = "module.bin"
    
    if len(sys.argv) >= 3:
        output_file = sys.argv[2]
    else:
        output_file = input("Output file [module_patched.bin]: ").strip()
        if not output_file:
            output_file = "module_patched.bin"
    
    if not os.path.exists(input_file):
        print(f"[!] File not found: {input_file}")
        return
    
    print()
    patch_module(input_file, output_file)

if __name__ == "__main__":
    main()