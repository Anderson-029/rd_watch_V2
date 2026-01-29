@echo off
set "PATH=%PATH%;C:\Program Files\PostgreSQL\18\bin"
setlocal enabledelayedexpansion

:: RD WATCH - Validador de Requisitos para Windows
:: Verifica que el entorno este listo para ejecutar el proyecto.

:: Cambiar al directorio raiz del proyecto
cd /d "%~dp0"..\..

echo [INFO] Verificando requisitos del sistema...
echo.

set REQUISITOS_OK=true

:: 1. Verificar PHP
php -v >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] PHP esta instalado.
) else (
    echo [ERROR] PHP no se encuentra en el PATH.
    set REQUISITOS_OK=false
)

:: 2. Verificar PostgreSQL (psql)
psql --version >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] PostgreSQL ^(psql^) esta instalado.
) else (
    echo [ERROR] psql no se encuentra en el PATH ^(necesario para la BD^).
    set REQUISITOS_OK=false
)

:: 3. Verificar Extensiones de PHP
echo.
echo [INFO] Verificando extensiones criticas de PHP...

set EXT_FAIL=false
for %%e in (pdo_pgsql pgsql fileinfo curl mbstring) do (
    php -m | findstr /i "%%e" >nul
    if !errorlevel! equ 0 (
        echo    - %%e: [OK]
    ) else (
        echo    - %%e: [FALTA]
        set EXT_FAIL=true
    )
)

if "!EXT_FAIL!"=="true" (
    echo.
    echo [AVISO] Faltan algunas extensiones en php.ini.
    set REQUISITOS_OK=false
)

:: 4. Verificar Archivo .env
if exist "backend\.env" (
    echo [OK] Archivo .env configurado en backend/.
) else (
    echo [AVISO] No se encontro backend\.env.
    echo         Copiando .env.example como base...
    copy "backend\.env.example" "backend\.env" >nul
    echo         [INFO] IMPORTANTE: Edita backend\.env con tus credenciales de BD.
)

echo.
if "!REQUISITOS_OK!"=="true" (
    echo [OK] Todo parece estar en orden, puedes seguir con la instalacion.
) else (
    echo [ERROR] Por favor, soluciona los errores arriba mencionados antes de continuar.
)
echo.
pause
