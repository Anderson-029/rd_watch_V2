@echo off
set "PATH=%PATH%;C:\Program Files\PostgreSQL\18\bin"
set PAGER=
setlocal enabledelayedexpansion

:: RD Watch - Script de Configuracion de Base de Datos para Windows

:: Cambiar al directorio raiz del proyecto
cd /d "%~dp0"..\..
set "BASE_DIR=%cd%"

set DB_NAME=rdwatch
set DB_USER=postgres
set DB_HOST=localhost
set DB_PORT=5432
set PGPASSWORD=ander123

echo [INFO] Iniciando configuracion de base de datos RD Watch...

:: 0. Crear Base de Datos si no existe
echo [INFO] Verificando existencia de la base de datos '%DB_NAME%'...
psql -P pager=off -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -tAc "SELECT 1 FROM pg_database WHERE datname='%DB_NAME%'" | findstr "1" >nul
if %errorlevel% neq 0 (
    echo [INFO] La base de datos no existe. Creandola...
    createdb -h %DB_HOST% -p %DB_PORT% -U %DB_USER% %DB_NAME%
    if %errorlevel% equ 0 (
        echo [OK] Base de datos '%DB_NAME%' creada con exito.
    ) else (
        echo [ERROR] No se pudo crear la base de datos.
        pause
        exit /b
    )
) else (
    echo [INFO] La base de datos '%DB_NAME%' ya existe.
)

:: 1. Esquema Principal
echo [INFO] Cargando esquema principal...
psql -P pager=off -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "database\schema\database_rdwatch_3_0.sql" --quiet

:: 2. Tablas Adicionales
echo [INFO] Cargando tablas de control adicionales...
psql -P pager=off -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "database\schema\rate_limits.sql" --quiet

:: 3. Funciones
echo [INFO] Cargando funciones...
for %%f in (database\functions\*.sql) do (
    psql -P pager=off -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%%f" --quiet
)

:: 4. Disparadores (Triggers)
echo [INFO] Cargando disparadores...
for %%t in (database\triggers\*.sql) do (
    psql -P pager=off -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%%t" --quiet
)

:: 5. Scripts de Datos Iniciales y Usuarios
echo [INFO] Cargando datos iniciales y usuarios de prueba...
for %%s in (database\scripts\*.sql) do (
    echo   - Procesando: %%s
    psql -P pager=off -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%%s" --quiet
)

echo [OK] Configuracion de base de datos completada con exito.

:: 6. Automatizacion de Seguridad
echo [INFO] Aplicando blindaje de seguridad automatica...
call "%BASE_DIR%\setup\windows\setup_security.bat"
pause
