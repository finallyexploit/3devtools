#include "neverlose.h"
#include <psapi.h>
#pragma comment(lib, "psapi.lib")

void patch_author_name() {
    printf("[*] Patching...\n");
    
    // Используем известный базовый адрес из логов
    char* start = (char*)0x412A0000;
    char* end = start + 0x800000;  // 8 MB — достаточно для модуля
    
    printf("[*] Scanning: 0x%p - 0x%p\n", start, end);
    
    const char* old_name = "kaktusior";
    const char* new_name = "hassen133";
    size_t name_len = 9;
    
    int found = 0;
    
    for (char* p = start; p < end - name_len; p++) {
        __try {
            if (memcmp(p, old_name, name_len) == 0) {
                printf("[+] Found at 0x%p\n", p);
                
                DWORD oldProtect;
                VirtualProtect(p, name_len, PAGE_READWRITE, &oldProtect);
                memcpy(p, new_name, name_len);
                VirtualProtect(p, name_len, oldProtect, &oldProtect);
                
                printf("[+] Patched to: %s\n", p);
                found++;
            }
        }
        __except(EXCEPTION_EXECUTE_HANDLER) {
            // Достигли недоступной памяти
            break;
        }
    }
    
    printf("[*] Patched %d occurrence(s)\n", found);
    fflush(stdout);
}
void patch_menu_title() {
    printf("[*] Patching menu title...\n");
    char* base = (char*)g_neverlose.base();
    
    char* title_addr = base + 0x435560;
    
    const char* new_title = "EVERMISS";
    
    DWORD oldProtect;
    VirtualProtect(title_addr, 9, PAGE_READWRITE, &oldProtect);
    memcpy(title_addr, new_title, 9);
    VirtualProtect(title_addr, 9, oldProtect, &oldProtect);
    
    printf("[+] Menu title patched at 0x%p\n", title_addr);
    fflush(stdout);
}
void patch_menu_title_loop() {
    printf("[*] Waiting for menu to open...\n");
    
    char* base = (char*)g_neverlose.base();
    char* title_addr = base + 0x435560;
    const char* new_title = "hauntz666";
    DWORD oldProtect;
    VirtualProtect(title_addr, 9, PAGE_READWRITE, &oldProtect);
    memcpy(title_addr, new_title, 9);
    VirtualProtect(title_addr, 9, oldProtect, &oldProtect);
    while (true) {
        MEMORY_BASIC_INFORMATION mbi;
        for (char* ptr = 0; ptr < (char*)0x7FFFFFFF; ) {
            if (!VirtualQuery(ptr, &mbi, sizeof(mbi))) break;
            
            if (mbi.State == MEM_COMMIT && mbi.Protect != PAGE_NOACCESS) {
                char* end = (char*)mbi.BaseAddress + mbi.RegionSize - 9;
                __try {
                    for (char* p = (char*)mbi.BaseAddress; p < end; p++) {
                        if (memcmp(p, "Neverlose", 9) == 0) {
                            VirtualProtect(p, 9, PAGE_READWRITE, &oldProtect);
                            memcpy(p, "HASSEN133", 9);
                            VirtualProtect(p, 9, oldProtect, &oldProtect);
                        }
                    }
                }
                __except(EXCEPTION_EXECUTE_HANDLER) {}
            }
            ptr = (char*)mbi.BaseAddress + mbi.RegionSize;
        }
        
        Sleep(5000); // Патчим каждые 5 секунд
    }
}
NTSTATUS NTAPI MainThread(LPVOID lpThreadParameter)
{
    AllocConsole();
    freopen("CONOUT$", "w", stdout);
    printf("[+] MainThread started (hModule=0x%p)\n", lpThreadParameter);
    fflush(stdout);

    printf("[*] Calling map()...\n"); fflush(stdout);
    g_neverlose.map((HMODULE)lpThreadParameter);
    printf("[+] map() done (base=0x%p)\n", g_neverlose.base()); fflush(stdout);

    printf("[*] Waiting for serverbrowser.dll...\n"); fflush(stdout);
    while (!GetModuleHandleW(L"serverbrowser.dll"))
        Sleep(100);
    printf("[+] serverbrowser.dll loaded\n"); fflush(stdout);

    printf("[*] Calling fix_dump()...\n"); fflush(stdout);
    g_neverlose.fix_dump();
    printf("[+] fix_dump() done\n"); fflush(stdout);

    printf("[*] Calling set_veh()...\n"); fflush(stdout);
    g_neverlose.set_veh();
    printf("[+] set_veh() done\n"); fflush(stdout);

    printf("[*] Calling setup_hooks()...\n"); fflush(stdout);
    g_neverlose.setup_hooks();
    printf("[+] setup_hooks() done\n"); fflush(stdout);

    printf("[*] Calling spoof()...\n"); fflush(stdout);
    g_neverlose.spoof();
    printf("[+] spoof() done\n"); fflush(stdout);

    printf("[+] All init done. Press PAGE DOWN to call entry().\n"); fflush(stdout);
    while (!(GetAsyncKeyState(VK_NEXT) & 0x8000)) Sleep(0);

    printf("[*] Calling entry()...\n"); fflush(stdout);
    g_neverlose.entry();
    printf("[+] entry() returned\n"); fflush(stdout);
    printf("[*] Waiting 15 seconds for GUI to load...\n");
    Sleep(15000);
    
    // patch_author_name();
    // patch_menu_title();
    // patch_menu_title_loop();
    
    // ========== CRACKED BY HASSEN TEAM ==========
    printf("\n");
    printf("========================================\n");
    printf("                                      \n");
    printf("    NEVERLOSE CRACK BY HASSEN TEAM    \n");
    printf("         t.me/an5ropic && t.me/hauntz1337                            \n");
    printf("         t.me/an5ropic && t.me/hauntz1337                             \n");
    printf("         t.me/an5ropic && t.me/hauntz1337                             \n");
    printf("    All features unlocked             \n");
    printf("    Build: 1941              \n");
    printf("    Cracked: 2026 19 04             \n");
    printf("                                      \n");
    printf("========================================\n");
    printf("\n");
    printf("[*] Cracked by hassen team | t.me/an5ropic && t.me/hauntz1337\n");
    printf("[*] Waiting for game...\n");
    printf("[*] Press PAGE DOWN to open menu\n");
    printf("\n");
    fflush(stdout);
    // ============================================
    printf("[*] MainThread running, waiting...\n");
    while (true) {
        Sleep(1000);
    }

    return STATUS_SUCCESS;
}

BOOL WINAPI DllMain(HINSTANCE hinstDLL, DWORD fdwReason, LPVOID lpvReserved)
{
    if (fdwReason == DLL_PROCESS_ATTACH)
    {
        DisableThreadLibraryCalls(hinstDLL);
        OutputDebugStringA("[neverlose] DllMain: DLL_PROCESS_ATTACH\n");
        HANDLE hThread;
        NTSTATUS status = NtCreateThreadEx(&hThread, THREAD_ALL_ACCESS, NULL, NtCurrentProcess(), MainThread, hinstDLL, THREAD_CREATE_FLAGS_NONE, 0, 0, 0, NULL);
        if (NT_SUCCESS(status))
        {
            OutputDebugStringA("[neverlose] DllMain: thread created OK\n");
            NtClose(hThread);
        }
        else
        {
            char buf[128];
            sprintf(buf, "[neverlose] DllMain: NtCreateThreadEx FAILED (0x%08lX)\n", status);
            OutputDebugStringA(buf);
            return FALSE;
        }
    }

    return TRUE;
}