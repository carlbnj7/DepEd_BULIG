@echo off
cd /d "%~dp0"
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" tools\create_admin.php
) else (
  php tools\create_admin.php
)
pause
