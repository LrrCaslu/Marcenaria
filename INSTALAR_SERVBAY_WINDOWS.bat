@echo off
setlocal
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALAR_SERVBAY_WINDOWS.ps1"
set "RESULT=%ERRORLEVEL%"
echo.
if not "%RESULT%"=="0" echo A instalacao apresentou um erro. Verifique as mensagens acima.
pause
exit /b %RESULT%
