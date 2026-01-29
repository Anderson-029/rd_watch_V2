@echo off
setlocal enabledelayedexpansion

:: RD WATCH - Smoke Test & Verificacion Final (v1.0)
:: Realiza pruebas de conectividad reales antes de abrir la web.

:: Cambiar al directorio raiz del proyecto
cd /d "%~dp0"..\..

echo [INFO] Iniciando Smoke Test del ecosistema...
echo.

:: 1. Verificar Frontend (Puerto 8000)
echo [TEST] Probando Frontend (http://localhost:8000)...
powershell -Command "$status = try { (Invoke-WebRequest -Uri 'http://localhost:8000' -UseBasicParsing -TimeoutSec 2).StatusCode } catch { 0 }; exit $status" >nul 2>&1
if !errorlevel! equ 200 (
    echo    [OK] Frontend respondiendo correctamente.
) else (
    echo    [ERROR] El Frontend no responde. Asegurese de iniciar los servidores.
)

:: 2. Verificar Backend (Puerto 8001 / Health Check)
echo [TEST] Probando API y Base de Datos (Health Check)...
powershell -Command "$response = try { Invoke-RestMethod -Uri 'http://localhost:8001/api/health_check.php' -TimeoutSec 2 } catch { $null }; if($response -and $response.ok) { exit 0 } else { exit 1 }" >nul 2>&1
if !errorlevel! equ 0 (
    echo    [OK] API y Base de Datos conectadas y saludables.
) else (
    echo    [ERROR] Fallo la prueba de salud de la API.
)

echo.
echo [INFO] Resultado Final:
if !errorlevel! equ 0 (
    echo    [EXITO] TODO LISTO! El sistema es estable.
) else (
    echo    [AVISO] Hay problemas pendientes. Revisa los logs en logs_servidores/.
)
echo.
pause
