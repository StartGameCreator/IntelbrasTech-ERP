@echo off
chcp 65001 >nul
title IntelbrasTech ERP - Fase 23.1
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALAR_FASE23_1.ps1"
echo.
pause
