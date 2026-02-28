@echo off
title RDWATCH - Instalador Maestro v2.1
color 0B
setlocal enabledelayedexpansion

echo =====================================================
echo    RDWATCH - INSTALADOR DE BASE DE DATOS (PSQL)
echo =====================================================

:: --- CONFIGURACIÓN ---
set PG_PSQL="C:\Program Files\PostgreSQL\17\bin\psql.exe"
set DB_HOST=10.5.213.111
set DB_NAME=db_rdwatch
set DB_USER=gr_rdwatch
set PGPASSWORD=rdwatch123

:: Comando base para psql
set PSQL_CMD=%PG_PSQL% -h %DB_HOST% -U %DB_USER% -d %DB_NAME% -q

echo.
echo [1/4] Cargando Esquema Base...
%PSQL_CMD% -f "sql\schema\database_rdwatch_3_0.sql" || goto :error

echo [2/4] Instalando Triggers y Auditoria...
%PSQL_CMD% -f "sql\triggers\audit_trail.sql" || goto :error

echo [3/4] Desplegando Logica de Backend (Funciones)...
for %%f in (sql\logica_backend\*.sql) do (
    echo    - cargando: %%~nxf
    %PSQL_CMD% -f "%%f" || goto :error
)

echo [4/4] Poblando Semillas y Datos Maestros...
for %%f in (sql\scripts\*.sql) do (
    echo    - insertando: %%~nxf
    %PSQL_CMD% -f "%%f" || goto :error
)

echo.
echo =====================================================
echo    INSTALACION COMPLETADA EXITOSAMENTE (V2.1)
echo =====================================================
pause
exit /b 0

:error
echo.
echo [ERROR] La instalacion fallo en el ultimo paso.
pause
exit /b 1
