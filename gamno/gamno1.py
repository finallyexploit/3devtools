import random

letters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
digits = "0123456789"
symbols = "!@#$%^&*"

all_chars = letters + digits + symbols

length = int(input("Введи довжину пароля: "))

password = ""

for i in range(length):
    password += random.choice(all_chars)

print("Згенерований пароль:", password)