@echo off
setlocal EnableDelayedExpansion
set "BASE_DIR=%~dp0"
set "ENV_FILE=%BASE_DIR%src\backend\.env"
set "LOG_FILE=%BASE_DIR%install_db.log"
set "PSQL_PATH=C:\Program Files\PostgreSQL\18\bin\psql.exe"

REM --- ESTÉTICA ---
echo ==================================================
echo       RELOJERÍA RD WATCH - INSTALACIÓN BD
echo ==================================================
echo.

REM 1. Validar psql
if not exist "%PSQL_PATH%" (
    echo [ERROR] No se encuentra psql.exe en: %PSQL_PATH%
    echo Verifique la ruta en el archivo .bat
    pause
    exit /b 1
)

REM 2. Cargar configuración
if not exist "%ENV_FILE%" (
    echo [ERROR] No existe el archivo .env en: %ENV_FILE%
    pause
    exit /b 1
)

for /f "usebackq tokens=1* delims==" %%A in ("%ENV_FILE%") do (
    if "%%A"=="DB_HOST" set DB_HOST=%%B
    if "%%A"=="DB_PORT" set DB_PORT=%%B
    if "%%A"=="DB_NAME" set DB_NAME=%%B
    if "%%A"=="DB_USER" set DB_USER=%%B
    if "%%A"=="DB_PASS" set DB_PASS=%%B
)

set PGPASSWORD=%DB_PASS%
set PGCLIENTENCODING=UTF8

REM 3. Preparar Base de Datos
echo [INFO] Recreando base de datos '%DB_NAME%' en %DB_HOST%...
echo. > "%LOG_FILE%"
"%PSQL_PATH%" -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '%DB_NAME%' AND pid <> pg_backend_pid();" >> "%LOG_FILE%" 2>&1
"%PSQL_PATH%" -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "DROP DATABASE IF EXISTS \"%DB_NAME%\";" >> "%LOG_FILE%" 2>&1
"%PSQL_PATH%" -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "CREATE DATABASE \"%DB_NAME%\";" >> "%LOG_FILE%" 2>&1

if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] No se pudo crear la base de datos. Revise install_db.log
    pause
    exit /b 1
)

REM 4. Ejecución de Scripts en Orden
echo [INFO] Instalando Estructura...
call :run_sql "%BASE_DIR%sql\schema\database_rdwatch_3_0.sql"

echo [INFO] Instalando Funciones...
for %%f in ("%BASE_DIR%sql\functions\*.sql") do (
    echo %%~nxf | find /i "inserts_" >NUL
    if errorlevel 1 call :run_sql "%%f"
)

echo [INFO] Instalando Triggers...
for %%f in ("%BASE_DIR%sql\triggers\*.sql") do call :run_sql "%%f"

echo [INFO] Cargando Datos Iniciales...
if exist "%BASE_DIR%sql\functions\inserts_departamentos_y_ciudades.sql" call :run_sql "%BASE_DIR%sql\functions\inserts_departamentos_y_ciudades.sql"

REM 5. Población de Scripts (Solo 01 y 02 según lo acordado)
for %%f in ("%BASE_DIR%sql\scripts\*.sql") do call :run_sql "%%f"

echo.
echo ==================================================
echo      PROCESO FINALIZADO EXITOSAMENTE
echo ==================================================
echo Usuario Admin: admin@rdwatch.com / admin123.
echo Usuario Cliente: cliente@rdwatch.com / cliente123.
echo.
pause
exit /b 0

:run_sql
set "FILE=%~1"
echo   - Procesando: %~nx1
"%PSQL_PATH%" -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%FILE%" >> "%LOG_FILE%" 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo     [!] ERROR en %~nx1. Verifique install_db.log
    exit /b 1
)
goto :eof
