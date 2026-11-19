@echo off
setlocal
title Instalacao do IntelbrasTech Assinador Local

set "DESTINO=%LOCALAPPDATA%\IntelbrasTech\Assinador"
echo Instalando o IntelbrasTech Assinador Local em:
echo %DESTINO%
echo.

if not exist "%DESTINO%" mkdir "%DESTINO%"
copy /Y "%~dp0IntelbrasTech.Assinador.ps1" "%DESTINO%\IntelbrasTech.Assinador.ps1" >nul
copy /Y "%~dp0iniciar-assinador.cmd" "%DESTINO%\iniciar-assinador.cmd" >nul
copy /Y "%~dp0README.md" "%DESTINO%\README.md" >nul

powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$ws=New-Object -ComObject WScript.Shell; $s=$ws.CreateShortcut([Environment]::GetFolderPath('Desktop')+'\IntelbrasTech Assinador.lnk'); $s.TargetPath='%DESTINO%\iniciar-assinador.cmd'; $s.WorkingDirectory='%DESTINO%'; $s.IconLocation='powershell.exe,0'; $s.Save()"

echo.
echo Instalacao concluida. Um atalho foi criado na Area de Trabalho.
echo O certificado A1 do servidor nao foi alterado.
echo.
choice /C SN /M "Deseja iniciar o assinador agora"
if errorlevel 2 goto fim
start "" "%DESTINO%\iniciar-assinador.cmd"

:fim
pause
