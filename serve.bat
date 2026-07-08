@echo off
setlocal
set "PROJECT_DIR=%~dp0"
set "PHP_DIR=C:\Users\kashi\.config\herd-lite\bin"
set "NODE_DIR=D:\laragon\bin\nodejs\node-v22"
set "PATH=%PHP_DIR%;%NODE_DIR%;%PATH%"

cd /d "%PROJECT_DIR%"

if not exist "vendor" (
    echo Installing Composer dependencies...
    call composer install
)

if not exist "node_modules" (
    echo Installing npm dependencies...
    call npm install
)

echo Starting Vite dev server for CSS/JS...
start "Vite Dev Server" cmd /k "cd /d "%PROJECT_DIR%" && set PATH=%NODE_DIR%;%PATH% && npm run dev"

echo Starting Laravel PHP server...
start "" http://127.0.0.1:8000
php artisan serve

pause
