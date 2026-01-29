#!/bin/bash

# RD WATCH - Script de Inicio Automatico (v2.0 Elite)
# Levanta los servidores de desarrollo de Frontend y Backend de forma robusta y portable.

echo "[INFO] Iniciando entorno RD Watch..."

# 1. Definir rutas relativas (Portabilidad Total)
# Esto permite que el script funcione sin importar dónde esté la carpeta del proyecto.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_DIR="$BASE_DIR/logs_servidores"

# Colores para la terminal
GREEN='\033[0;32m'
RED='\033[0;31m'
GOLD='\033[0;33m'
NC='\033[0m' # No Color

# Crear directorio de logs si no existe
mkdir -p "$LOG_DIR"

# Función para verificar y liberar puertos
check_and_clear_port() {
    local port=$1
    local name=$2
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null ; then
        echo -e "${GOLD}[AVISO] El puerto $port ($name) esta ocupado.${NC}"
        echo -e "   Intentando liberar puerto..."
        fuser -k $port/tcp 2>/dev/null
        sleep 1
        
        if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null ; then
            echo -e "${RED}[ERROR] No se pudo liberar el puerto $port.${NC}"
            echo -e "   El proceso requiere permisos de superusuario o es un servicio del sistema."
            return 1
        fi
    fi
    return 0
}

# 2. Limpieza y validacion de puertos
echo -e "[INFO] Validando disponibilidad de red..."
check_and_clear_port 8000 "Frontend" || FRONT_FAIL=1
check_and_clear_port 8001 "Backend" || BACK_FAIL=1

# 3. Iniciar Backend (Puerto 8001)
if [ -z "$BACK_FAIL" ]; then
    echo -e "[INFO] Iniciando Backend en puerto 8001..."
    cd "$BASE_DIR/backend"
    php -S localhost:8001 > "$LOG_DIR/backend_server.log" 2>&1 &
    BACKEND_PID=$!
else
    echo -e "${RED}[ERROR] Backend no iniciado por conflicto de puerto.${NC}"
fi

# 4. Iniciar Frontend (Puerto 8000)
if [ -z "$FRONT_FAIL" ]; then
    echo -e "[INFO] Iniciando Frontend en puerto 8000..."
    cd "$BASE_DIR/frontend/public"
    php -S localhost:8000 > "$LOG_DIR/frontend_server.log" 2>&1 &
    FRONTEND_PID=$!
else
    echo -e "${RED}[ERROR] Frontend no iniciado por conflicto de puerto.${NC}"
fi

# 5. Verificacion de ejecucion real
echo -e "\n[INFO] Verificando estabilidad..."
sleep 2

SUCCESS=true

if [ -n "$BACKEND_PID" ] && ps -p $BACKEND_PID > /dev/null; then
    echo -e "   - Backend:  ${GREEN}http://localhost:8001 [VIVO]${NC}"
else
    echo -e "   - Backend:  ${RED}[FALLO] Revisa $LOG_DIR/backend_server.log${NC}"
    SUCCESS=false
fi

if [ -n "$FRONTEND_PID" ] && ps -p $FRONTEND_PID > /dev/null; then
    echo -e "   - Frontend: ${GREEN}http://localhost:8000 [VIVO]${NC}"
else
    echo -e "   - Frontend: ${RED}[FALLO] Revisa $LOG_DIR/frontend_server.log${NC}"
    SUCCESS=false
fi

if [ "$SUCCESS" = true ]; then
    echo -e "\n${GREEN}[OK] ¡Ecosistema RD Watch listo para trabajar!${NC}"
else
    echo -e "\n${RED}[AVISO] Hubo problemas al iniciar algunos servicios.${NC}"
    echo -e "Tip: Si los puertos siguen ocupados, intenta correr: ${GOLD}sudo fuser -k 8000/tcp 8001/tcp${NC}"
fi

echo -e "\nLogs en: $LOG_DIR"
echo "Presiona Ctrl+C para finalizar este script (los servidores seguiran en segundo plano)."
