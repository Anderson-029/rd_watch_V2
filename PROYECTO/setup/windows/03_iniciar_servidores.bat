@echo off
set "PATH=%PATH%;C:\Program Files\PostgreSQL\18\bin"
setlocal enabledelayedexpansion

:: RD WATCH - Script de Inicio para Windows (v1.0)
:: Levanta los servidores de desarrollo de Frontend y Backend.

echo [INFO] Iniciando entorno RD Watch para Windows...

:: 1. Configurar Rutas
cd /d "%~dp0"..\..
set "BASE_DIR=%cd%\"
set "LOG_DIR=%BASE_DIR%logs_servidores"

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

:: 2. Iniciar Backend (Puerto 8001)
:: 2. Iniciar Backend (Puerto 8001)
echo [INFO] Iniciando Backend en puerto 8001...
cd /d "%BASE_DIR%backend"
start /b php -S localhost:8001 > "%LOG_DIR%\backend_server.log" 2>&1
set BACKEND_STATUS=VIVO

:: 3. Iniciar Frontend (Puerto 8000)
echo [INFO] Iniciando Frontend en puerto 8000...
cd /d "%BASE_DIR%frontend\public"
start /b php -S localhost:8000 > "%LOG_DIR%\frontend_server.log" 2>&1
set FRONTEND_STATUS=VIVO

:: 4. Verificación
timeout /t 3 /nobreak > nul

echo.
echo [INFO] Verificacion de estabilidad:
echo    - Backend:  http://localhost:8001 [%BACKEND_STATUS%]
echo    - Frontend: http://localhost:8000 [%FRONTEND_STATUS%]
echo.
echo [OK] Ecosistema RD Watch listo!
echo Logs disponibles en: %LOG_DIR%
echo.
echo Nota: Para detener los servidores, cierra esta ventana (o usa Task Manager para matar procesos 'php').
pause
