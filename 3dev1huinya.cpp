#include <iostream>
#include <string>
#include <map>
#include <limits>

// База користувачів (логін -> пароль)
const std::map<std::string, std::string> users = {
    {"admin", "admin123"},
    {"user1", "password1"},
    {"user2", "qwerty456"}
};

void clearScreen() {
#ifdef _WIN32
    system("cls");
#else
    system("clear");
#endif
}

void printBanner() {
    std::cout << "??????????????????????????????????\n";
    std::cout << "?         АВТОРИЗАЦІЯ             ?\n";
    std::cout << "??????????????????????????????????\n\n";
}

bool login(const std::string& username, const std::string& password) {
    auto it = users.find(username);
    if (it != users.end() && it->second == password) {
        return true;
    }
    return false;
}

void hiddenInput(std::string& password) {
    char ch;
    password.clear();

#ifdef _WIN32
#include <conio.h>
    while ((ch = _getch()) != '\r') {
        if (ch == '\b') {
            if (!password.empty()) {
                password.pop_back();
                std::cout << "\b \b";
            }
        }
        else {
            password += ch;
            std::cout << '*';
        }
    }
    std::cout << "\n";
#else
    system("stty -echo");
    std::getline(std::cin, password);
    system("stty echo");
    std::cout << "\n";
#endif
}

int main() {
    const int MAX_ATTEMPTS = 3;
    std::string username, password;
    int attempts = 0;

    clearScreen();
    printBanner();

    while (attempts < MAX_ATTEMPTS) {
        std::cout << "Логін: ";
        std::getline(std::cin, username);

        std::cout << "Пароль: ";
        hiddenInput(password);

        if (login(username, password)) {
            std::cout << "\n? Авторизація успішна! Ласкаво просимо, " << username << "!\n";
            std::cout << "\n--- Завантаження... ---\n";

            // Тут твій основний код після авторизації
            std::cout << "\n[Loader запущено]\n";
            break;
        }
        else {
            attempts++;
            int remaining = MAX_ATTEMPTS - attempts;

            std::cout << "\n? Невірний логін або пароль!";
            if (remaining > 0) {
                std::cout << " Залишилось спроб: " << remaining << "\n\n";
            }
            else {
                std::cout << "\n?? Перевищено кількість спроб. Доступ заблоковано.\n";
            }
        }
    }

    std::cout << "\nНатисніть Enter для виходу...";
    std::cin.ignore(std::numeric_limits<std::streamsize>::max(), '\n');
    std::cin.get();

    return 0;
}