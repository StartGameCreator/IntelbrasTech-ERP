@echo off
setlocal
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALAR_PATCH_QRCODE_SEFAZ.ps1"
endlocal
