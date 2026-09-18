@echo off
cd /d "%~dp0"
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" tools\upgrade_v2.php
) else (
  php tools\upgrade_v2.php
)
pause
