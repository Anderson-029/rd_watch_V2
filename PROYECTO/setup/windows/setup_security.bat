@echo off
:: setup_security.bat - Automatización de Seguridad RD Watch (WINDOWS)
setlocal enabledelayedexpansion

echo Iniciando configuracion de seguridad...

:: Detectar ruta del script
set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%..\.."

:: Verificar PHP
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo ERROR: PHP no esta instalado o no esta en el PATH.
    pause
    exit /b 1
)

:: Ejecutar script de mantenimiento
php maintenance\initialize_security.php

if %ERRORLEVEL% equ 0 (
    echo Seguridad configurada correctamente.
) else (
    echo Hubo un error configurando la seguridad.
    pause
    exit /b 1
)

pause
