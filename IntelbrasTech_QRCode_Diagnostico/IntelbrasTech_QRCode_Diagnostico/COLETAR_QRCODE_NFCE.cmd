@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0COLETAR_QRCODE_NFCE.ps1" -Projeto "D:\Skill\IntelbrasTech-ERP"
echo.
pause
