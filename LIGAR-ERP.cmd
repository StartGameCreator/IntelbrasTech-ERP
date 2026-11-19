@echo off
setlocal
set "ERP_ROOT=%~dp0"

echo Iniciando IntelbrasTech ERP...

start "IntelbrasTech Backend 8033" cmd /k call "%ERP_ROOT%backend\INICIAR-BACKEND.cmd"
start "IntelbrasTech Frontend 5173" cmd /k call "%ERP_ROOT%frontend\INICIAR-FRONTEND.cmd"

echo.
echo Backend:  http://127.0.0.1:8033
echo Frontend: http://localhost:5173
echo.
echo As duas janelas de servico devem permanecer abertas.
echo Aguarde alguns segundos para o navegador carregar.

timeout /t 5 /nobreak >nul
start "IntelbrasTech ERP" http://localhost:5173/login
endlocal
