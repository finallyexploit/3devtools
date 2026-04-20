@echo off
title Neverlose Local Servers
echo ========================================
echo   Starting Neverlose Local Servers
echo ========================================
echo.

cd /d "C:\Users\sky7o\Desktop\testing\nl_crack\server"

echo [1/2] Starting HTTP Server (port 30031)...
start "Neverlose HTTP" cmd /k "python http_server.py"

echo [2/2] Starting WSS Server (port 30030)...
start "Neverlose WSS" cmd /k "python wss_server.py"

echo.
echo ========================================
echo   Servers started in separate windows!
echo ========================================
echo.
echo Press any key to close this window...
pause >nul