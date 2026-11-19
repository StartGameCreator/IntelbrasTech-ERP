@echo off
cd /d "%~dp0"
if not exist "..\..\server-logs" mkdir "..\..\server-logs"
echo [%date% %time%] iniciando frontend 5173 > "..\..\server-logs\frontend-5173.log"
"C:\tools\php85\php.exe" -S 0.0.0.0:5173 static-router.php >> "..\..\server-logs\frontend-5173.log" 2>&1
echo [%date% %time%] frontend encerrado com codigo %errorlevel% >> "..\..\server-logs\frontend-5173.log"
pause
