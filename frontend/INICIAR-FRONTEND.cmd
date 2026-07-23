@echo off
title IntelbrasTech Frontend 5173
cd /d "%~dp0"

if not exist "C:\Python314\python.exe" (
  echo ERRO: Python nao encontrado em C:\Python314\python.exe
  pause
  exit /b 1
)

echo Iniciando frontend em http://localhost:5173...
echo Esta janela deve permanecer aberta.
echo.
"C:\Python314\python.exe" static_server.py

echo.
echo O frontend foi encerrado ou encontrou um erro.
pause
