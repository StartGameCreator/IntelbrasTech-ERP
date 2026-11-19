@echo off
setlocal

title IntelbrasTech Backend 8033
cd /d "%~dp0"

set "PHP_EXE=C:\tools\php85\php.exe"
set "HOST=127.0.0.1"
set "PORT=8033"

if not exist "%PHP_EXE%" (
    echo.
    echo ERRO: PHP nao encontrado em:
    echo %PHP_EXE%
    echo.
    pause
    exit /b 1
)

if not exist "artisan" (
    echo.
    echo ERRO: arquivo artisan nao encontrado em:
    echo %CD%
    echo.
    pause
    exit /b 1
)

echo.
echo ================================================
echo  IntelbrasTech ERP - Backend Laravel
echo ================================================
echo.
echo Endereco: http://%HOST%:%PORT%
echo Pasta:    %CD%
echo.
echo Esta janela deve permanecer aberta.
echo Pressione CTRL+C para encerrar.
echo.

"%PHP_EXE%" artisan serve --host=%HOST% --port=%PORT%

echo.
echo O backend foi encerrado ou encontrou um erro.
pause

endlocal