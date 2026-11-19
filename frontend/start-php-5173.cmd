@echo off
cd /d "%~dp0"
"C:\tools\php85\php.exe" -S 0.0.0.0:5173 static-router.php
