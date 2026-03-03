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
echo    - cargando: auth_security.sql
%PSQL_CMD% -f "sql\logica_backend\auth_security.sql" || goto :error
echo    - cargando: catalog_master.sql
%PSQL_CMD% -f "sql\logica_backend\catalog_master.sql" || goto :error
echo    - cargando: client_panel.sql
%PSQL_CMD% -f "sql\logica_backend\client_panel.sql" || goto :error
echo    - cargando: ecommerce_core.sql
%PSQL_CMD% -f "sql\logica_backend\ecommerce_core.sql" || goto :error
echo    - cargando: admin_reports.sql
%PSQL_CMD% -f "sql\logica_backend\admin_reports.sql" || goto :error

echo [4/4] Poblando Semillas y Datos Maestros...
echo    - insertando: 00_geodata.sql
%PSQL_CMD% -f "sql\scripts\00_geodata.sql" || goto :error
echo    - insertando: 01_users_base.sql
%PSQL_CMD% -f "sql\scripts\01_users_base.sql" || goto :error
echo    - insertando: 02_users_extended.sql
%PSQL_CMD% -f "sql\scripts\02_users_extended.sql" || goto :error
echo    - insertando: 03_catalog.sql
%PSQL_CMD% -f "sql\scripts\03_catalog.sql" || goto :error
echo    - insertando: 04_activity.sql
%PSQL_CMD% -f "sql\scripts\04_activity.sql" || goto :error
echo    - insertando: 05_reviews.sql
%PSQL_CMD% -f "sql\scripts\05_reviews.sql" || goto :error
echo    - insertando: 06_configuracion_admin_pending.sql
%PSQL_CMD% -f "sql\scripts\06_configuracion_admin_pending.sql" || goto :error

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
