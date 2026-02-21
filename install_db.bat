@echo off
setlocal EnableDelayedExpansion

REM Configuración de colores (simulados) y log
set "BASE_DIR=%~dp0"
set "ENV_FILE=%BASE_DIR%src\backend\.env"
set "LOG_FILE=%BASE_DIR%install_db.log"

REM Agregar ruta de PostgreSQL al PATH si no existe
set "PG_PATH=C:\Program Files\PostgreSQL\17\bin"
if exist "%PG_PATH%" set "PATH=%PATH%;%PG_PATH%"

REM Limpiar log anterior
echo. > "%LOG_FILE%"

echo ==================================================
echo      Iniciando instalacion de base de datos
echo ==================================================
echo.

REM 1. Cargar configuración desde .env
if not exist "%ENV_FILE%" (
    echo [ERROR] Archivo .env no encontrado en: %ENV_FILE%
    pause
    exit /b 1
)

echo [INFO] Cargando configuracion...
for /f "usebackq tokens=1* delims==" %%A in ("%ENV_FILE%") do (
    if "%%A"=="DB_HOST" set DB_HOST=%%B
    if "%%A"=="DB_PORT" set DB_PORT=%%B
    if "%%A"=="DB_NAME" set DB_NAME=%%B
    if "%%A"=="DB_USER" set DB_USER=%%B
    if "%%A"=="DB_PASS" set DB_PASS=%%B
)

if "%DB_NAME%"=="" ( echo [ERROR] DB_NAME no definido & pause & exit /b 1 )
if "%DB_PASS%"=="" ( echo [ERROR] DB_PASS no definido & pause & exit /b 1 )

REM Configurar variables de entorno para psql
set PGPASSWORD=%DB_PASS%
set PGCLIENTENCODING=UTF8

REM 2. Verificar conexión y recrear DB
echo [INFO] Verificando conexion a PostgreSQL...
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "SELECT 1" >NUL 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] No se pudo conectar a PostgreSQL en %DB_HOST%:%DB_PORT%
    echo Verifique credenciales y que el servicio este corriendo.
    pause
    exit /b 1
)

echo [INFO] Terminando sesiones activas en '%DB_NAME%'...
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '%DB_NAME%' AND pid <> pg_backend_pid();" >> "%LOG_FILE%" 2>&1

echo [INFO] Recreando base de datos '%DB_NAME%'...
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "DROP DATABASE IF EXISTS \"%DB_NAME%\";" >> "%LOG_FILE%" 2>&1
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "CREATE DATABASE \"%DB_NAME%\";" >> "%LOG_FILE%" 2>&1

REM 3. Paso 1: Estructura Base (Schema)
echo.
echo [INFO] --- Paso 1: Estructura (Schema) ---
call :run_psql "%BASE_DIR%sql\schema\database_rdwatch_3_0.sql"

REM 4. Paso 2: Funciones y Procedimientos CRUD
echo.
echo [INFO] --- Paso 3: Funciones y Procedimientos CRUD ---
for %%f in ("%BASE_DIR%sql\functions\*.sql") do (
    echo %%~nxf | find /i "inserts_" >NUL
    if errorlevel 1 call :run_psql "%%f"
)

REM 6. Paso 4: Triggers de Auditoria
echo.
echo [INFO] --- Paso 4: Triggers ---
for %%f in ("%BASE_DIR%sql\triggers\*.sql") do (
    call :run_psql "%%f"
)

REM 7. Paso 5: Logica de Negocio (Backend Kernel)
echo.
echo [INFO] --- Paso 5: Logica de Negocio ---
for %%f in ("%BASE_DIR%sql\logica_backend\*.sql") do (
    call :run_psql "%%f"
)

REM 8. Paso 6: Carga de Datos (Seeds)
echo.
echo [INFO] --- Paso 6: Carga de Datos ---

REM 6.1. Datos referenciales geográficos
if exist "%BASE_DIR%sql\functions\inserts_departamentos_y_ciudades.sql" (
    call :run_psql "%BASE_DIR%sql\functions\inserts_departamentos_y_ciudades.sql"
)

REM 6.2. Scripts maestros numerados (01-05)
echo [INFO] Cargando scripts maestros en orden...
for /l %%i in (1,1,5) do (
    set "NUM=0%%i"
    set "NUM=!NUM:~-2!"
    for %%f in ("%BASE_DIR%sql\scripts\!NUM!_*.sql") do (
        call :run_psql "%%f"
    )
)

REM 6.3. Seeders y configuraciones adicionales
echo [INFO] Cargando configuraciones adicionales...
if exist "%BASE_DIR%sql\scripts\configuracion_admin_pending.sql" call :run_psql "%BASE_DIR%sql\scripts\configuracion_admin_pending.sql"
if exist "%BASE_DIR%sql\scripts\create_reviews_table.sql" call :run_psql "%BASE_DIR%sql\scripts\create_reviews_table.sql"

echo.
echo ==================================================
echo      INSTALACION COMPLETADA EXITOSAMENTE
echo ==================================================
echo Log guardado en: install_db.log
pause
exit /b 0

REM Subrutina para ejecutar psql
:run_psql
set "FILE=%~1"
echo Ejecutando %~nx1...
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%FILE%" >> "%LOG_FILE%" 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Fallo al ejecutar %~nx1. Revise el log.
    exit /b 1
)
goto :eof
