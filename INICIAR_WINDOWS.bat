@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
  echo PHP nao foi encontrado no PATH. Configure o PHP 8.3+ antes de continuar.
  pause
  exit /b 1
)
if not exist "config.local.php" (
  copy "config.example.php" "config.local.php" >nul
  echo Foi criado config.local.php.
  echo Edite as credenciais do MySQL nesse arquivo e execute novamente.
  pause
  exit /b 1
)
php tools\install.php
if errorlevel 1 (
  echo Falha ao instalar. Verifique o MySQL e o arquivo config.local.php.
  pause
  exit /b 1
)
echo.
echo Sistema iniciado em http://127.0.0.1:8000
start "" "http://127.0.0.1:8000"
php -S 127.0.0.1:8000 -t public public\router.php
pause
