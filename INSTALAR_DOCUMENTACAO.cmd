@echo off
setlocal
cd /d "%~dp0"
set "DESTINO=D:\Skill\IntelbrasTech-ERP"
if not exist "%DESTINO%\backend" (
  echo ERRO: Projeto nao encontrado em %DESTINO%
  pause
  exit /b 1
)
for %%F in (README.md CHANGELOG.md ROADMAP.md CONTRIBUTING.md SECURITY.md LICENSE .gitignore) do copy /Y "%%F" "%DESTINO%\%%F" >nul
if exist "%DESTINO%\docs" (
  set "BKP=%DESTINO%\docs-backup-antes-DOC01"
  if exist "%BKP%" rmdir /S /Q "%BKP%"
  move "%DESTINO%\docs" "%BKP%" >nul
)
xcopy "docs" "%DESTINO%\docs\" /E /I /Y >nul
if errorlevel 1 (
  echo ERRO durante a copia.
  pause
  exit /b 1
)
echo.
echo DOCUMENTACAO DOC-01 INSTALADA COM SUCESSO.
echo Destino: %DESTINO%
echo.
pause
