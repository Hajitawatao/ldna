@echo off
REM Starts the LDNA app and the mock employee portal with PHP only (no Node.js needed).
REM Double-click this file, or run it from a terminal in this folder.

where php >nul 2>nul
if errorlevel 1 (
  echo PHP was not found. Install PHP 8.1+ or set PHP_BIN, for example:
  echo   set PHP_BIN=C:\xampp\php\php.exe
  pause
  exit /b 1
)

if "%PHP_BIN%"=="" set PHP_BIN=php

start "LDNA mock portal" "%PHP_BIN%" -S 0.0.0.0:8100 -t "%~dp0mock-portal"
start "LDNA app" "%PHP_BIN%" -S 0.0.0.0:5177 "%~dp0server.php"

echo.
echo   App:          http://localhost:5177/
echo   Mock portal:  http://localhost:8100/
echo   On the LAN, replace localhost with this PC's IP address.
echo.
echo   Close the two PHP windows to stop.
pause
