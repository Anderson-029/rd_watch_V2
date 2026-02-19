@echo off
setlocal EnableDelayedExpansion

REM ============================================================================
REM 🎯 RD WATCH V2 - INSTALADOR DE BASE DE DATOS (WINDOWS)
REM ----------------------------------------------------------------------------
REM Propósito: Automatizar la instalación de la BD en el orden correcto.
REM ============================================================================

set "BASE_DIR=%~dp0"
set "ENV_FILE=%BASE_DIR%src\backend\.env"
set "LOG_FILE=%BASE_DIR%install_db.log"

REM 1. LIMPIAR LOG ANTERIOR
echo. > "%LOG_FILE%"

echo ==================================================
echo      Iniciando instalacion de base de datos
echo ==================================================
echo.

REM 2. CARGAR CONFIGURACIÓN DESDE .env
if not exist "%ENV_FILE%" (
    echo [ERROR] Archivo .env no encontrado en: %ENV_FILE%
    pause
    exit /b 1
)

echo [INFO] Cargando configuracion desde .env...
for /f "usebackq tokens=1* delims==" %%A in ("%ENV_FILE%") do (
    set "KEY=%%A"
    set "VAL=%%B"
    if /i "!KEY!"=="DB_HOST" set DB_HOST=!VAL!
    if /i "!KEY!"=="DB_PORT" set DB_PORT=!VAL!
    if /i "!KEY!"=="DB_NAME" set DB_NAME=!VAL!
    if /i "!KEY!"=="DB_USER" set DB_USER=!VAL!
    if /i "!KEY!"=="DB_PASS" set DB_PASS=!VAL!
)

REM 3. VALIDAR VARIABLES CRÍTICAS
if "%DB_NAME%"=="" ( echo [ERROR] DB_NAME no definido & pause & exit /b 1 )
if "%DB_PASS%"=="" ( echo [ERROR] DB_PASS no definido & pause & exit /b 1 )

REM 4. DETECTAR PSQL DINÁMICAMENTE
where psql >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [INFO] psql no encontrado en PATH, buscando rutas comunes...
    set "POSTGRES_SEARCH_PATHS="C:\Program Files\PostgreSQL\18\bin";"C:\Program Files\PostgreSQL\17\bin";"C:\Program Files\PostgreSQL\16\bin";"C:\Program Files\PostgreSQL\15\bin""
    for %%P in (!POSTGRES_SEARCH_PATHS!) do (
        if exist "%%~P\psql.exe" (
            set "PATH=%PATH%;%%~P"
            goto :psql_found
        )
    )
    echo [ERROR] No se encontro psql.exe. Instale PostgreSQL o agreguelo al PATH.
    pause
    exit /b 1
)
:psql_found

set PGPASSWORD=%DB_PASS%
set PGCLIENTENCODING=UTF8

REM 5. RECREAR BASE DE DATOS
echo [INFO] Verificando conexion a PostgreSQL (%DB_HOST%:%DB_PORT%)...
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "SELECT 1" >NUL 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] No se pudo conectar a PostgreSQL. Verifique credenciales y el servicio.
    pause
    exit /b 1
)

echo [INFO] Reiniciando base de datos '%DB_NAME%'...
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '%DB_NAME%' AND pid <> pg_backend_pid();" >> "%LOG_FILE%" 2>&1
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "DROP DATABASE IF EXISTS \"%DB_NAME%\";" >> "%LOG_FILE%" 2>&1
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "CREATE DATABASE \"%DB_NAME%\";" >> "%LOG_FILE%" 2>&1

REM 6. EJECUCIÓN SECUENCIAL DE SQL
echo.
echo ==================================================
echo      EJECUTANDO MODULOS SQL (PASO A PASO)
echo ==================================================

REM --- PASO 1: ESQUEMA BASE ---
echo [PASO 1] Creando Estructura (Schema)...
call :run_sql "%BASE_DIR%sql\schema\database_rdwatch_3_0.sql"

REM --- PASO 2: TABLAS DE COMPLEMENTO ---
echo [PASO 2] Creando Tablas adicionales...
if exist "%BASE_DIR%sql\scripts\create_reviews_table.sql" call :run_sql "%BASE_DIR%sql\scripts\create_reviews_table.sql"

REM --- PASO 3: FUNCIONES Y LÓGICA ---
echo [PASO 3] Cargando Funciones (Lógica)...
for %%f in ("%BASE_DIR%sql\functions\*.sql") do (
    echo %%~nxf | find /i "inserts_" >nul
    if errorlevel 1 call :run_sql "%%f"
)

REM --- PASO 4: TRIGGERS ---
echo [PASO 4] Cargando Triggers...
for %%f in ("%BASE_DIR%sql\triggers\*.sql") do (
    call :run_sql "%%f"
)

REM --- PASO 5: DATOS DE REFERENCIA ---
echo [PASO 5] Cargando Datos de Referencia (Ciudades)...
if exist "%BASE_DIR%sql\functions\inserts_departamentos_y_ciudades.sql" call :run_sql "%BASE_DIR%sql\functions\inserts_departamentos_y_ciudades.sql"

REM --- PASO 6: SCRIPTS DE DATOS Y SEEDS (ORDENADOS) ---
echo [PASO 6] Cargando Datos Base y Seeds (Numerados)...
for /f "delims=" %%f in ('dir /b /on "%BASE_DIR%sql\scripts\*.sql"') do (
    set "FNAME=%%f"
    if /i not "!FNAME!"=="create_reviews_table.sql" (
        call :run_sql "%BASE_DIR%sql\scripts\!FNAME!"
    )
)

echo.
echo ==================================================
echo      INSTALACION COMPLETADA EXITOSAMENTE
echo ==================================================
echo Log detallado en: install_db.log
pause
exit /b 0

REM --- SUBRUTINA DE EJECUCIÓN ---
:run_sql
set "FILE=%~1"
echo   - Procesando: %~nx1
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%FILE%" >> "%LOG_FILE%" 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR FATAL] Fallo en el script: %~nx1
    echo Revise install_db.log para mas detalles.
    pause
    exit /b 1
)
goto :eof

