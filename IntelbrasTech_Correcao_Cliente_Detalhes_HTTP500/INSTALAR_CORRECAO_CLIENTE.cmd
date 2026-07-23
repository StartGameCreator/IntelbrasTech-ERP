@echo off
setlocal
chcp 65001 >nul
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALAR_CORRECAO_CLIENTE.ps1"
set "RC=%ERRORLEVEL%"
echo.
if not "%RC%"=="0" echo A instalação terminou com erro. Código: %RC%
pause
exit /b %RC%
