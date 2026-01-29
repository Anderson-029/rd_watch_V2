#!/bin/bash

# RD WATCH - Validador de Requisitos para Unix (Linux/macOS) v1.0
# Verifica que el entorno esté listo para ejecutar el proyecto RD WATCH.

# Colores para la terminal
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
GOLD='\033[0;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================================================${NC}"
echo -e "${GOLD}           RD WATCH - Verificación de Requisitos (Unix)         ${NC}"
echo -e "${BLUE}================================================================${NC}"
echo

REQUISITOS_OK=true

# Cambiar al directorio raíz del proyecto
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/../.."
BASE_DIR=$(pwd)

echo -e "[INFO] Directorio base: ${BLUE}$BASE_DIR${NC}"
echo

# 1. Verificar PHP
echo -e "[INFO] 1. Verificando PHP..."
if command -v php >/dev/null 2>&1; then
    PHP_VERSION=$(php -v | head -n 1)
    echo -e "${GREEN}   [OK] PHP está instalado: $PHP_VERSION${NC}"
    
    # Verificar versión mínima (ejemplo 7.4)
    PHP_VER_NUM=$(php -r 'echo PHP_VERSION_ID;')
    if [ "$PHP_VER_NUM" -lt 70400 ]; then
        echo -e "${YELLOW}   [AVISO] Se recomienda PHP 7.4 o superior para mejor compatibilidad.${NC}"
    fi
else
    echo -e "${RED}   [ERROR] PHP no se encuentra instalado o no está en el PATH.${NC}"
    REQUISITOS_OK=false
fi

# 2. Verificar PostgreSQL (psql)
echo -e "\n[INFO] 2. Verificando PostgreSQL (psql)..."
if command -v psql >/dev/null 2>&1; then
    PSQL_VERSION=$(psql --version | head -n 1)
    echo -e "${GREEN}   [OK] psql está instalado: $PSQL_VERSION${NC}"
else
    echo -e "${RED}   [ERROR] psql no se encuentra en el PATH (necesario para la base de datos).${NC}"
    REQUISITOS_OK=false
fi

# 3. Verificar Extensiones de PHP
echo -e "\n[INFO] 3. Verificando extensiones críticas de PHP..."
EXT_FAIL=false
# Lista de extensiones necesarias para el proyecto RD Watch
EXTENSIONS=("pdo_pgsql" "pgsql" "fileinfo" "curl" "mbstring" "openssl" "json" "session")

for ext in "${EXTENSIONS[@]}"; do
    if php -m | grep -iq "^$ext$" >/dev/null 2>&1; then
        echo -e "   - $ext: ${GREEN}[OK]${NC}"
    else
        echo -e "   - $ext: ${RED}[FALTA]${NC}"
        EXT_FAIL=true
    fi
done

if [ "$EXT_FAIL" = true ]; then
    echo -e "\n${RED}[ERROR] Faltan extensiones críticas de PHP.${NC}"
    echo -e "        Por favor, instálalas (ej: sudo apt install php-pgsql php-curl, etc.)${NC}"
    REQUISITOS_OK=false
fi

# 4. Verificar Archivo .env
echo -e "\n[INFO] 4. Verificando configuración del servicio (.env)..."
BACKEND_DIR="$BASE_DIR/backend"
if [ -f "$BACKEND_DIR/.env" ]; then
    echo -e "${GREEN}   [OK] Archivo .env detectado en backend/.${NC}"
else
    echo -e "${YELLOW}   [AVISO] No se encontró $BACKEND_DIR/.env.${NC}"
    if [ -f "$BACKEND_DIR/.env.example" ]; then
        echo -e "         Copiando .env.example como base..."
        cp "$BACKEND_DIR/.env.example" "$BACKEND_DIR/.env"
        echo -e "         ${BLUE}[INFO] IMPORTANTE: Edita $BACKEND_DIR/.env con tus credenciales de BD.${NC}"
    else
        echo -e "${RED}   [ERROR] No se encontró $BACKEND_DIR/.env.example en el backend.${NC}"
        REQUISITOS_OK=false
    fi
fi

# 5. Verificar Permisos de Directorios
echo -e "\n[INFO] 5. Verificando permisos de escritura en directorios clave..."
DIRS_PERM=("$BACKEND_DIR/logs" "$BACKEND_DIR/tmp" "$BASE_DIR/logs_servidores")

for d in "${DIRS_PERM[@]}"; do
    if [ ! -d "$d" ]; then
        mkdir -p "$d" 2>/dev/null
        if [ $? -eq 0 ]; then
            echo -e "   - $d: ${GREEN}[CREADO]${NC}"
        else
            echo -e "   - $d: ${RED}[ERROR AL CREAR]${NC}"
            REQUISITOS_OK=false
            continue
        fi
    fi
    
    if [ -w "$d" ]; then
        echo -e "   - $d: ${GREEN}[PERMISOS OK]${NC}"
    else
        echo -e "   - $d: ${YELLOW}[CORRIGIENDO PERMISOS]${NC}"
        chmod -R 775 "$d" 2>/dev/null
        if [ -w "$d" ]; then
            echo -e "     -> ${GREEN}Corregido.${NC}"
        else
            echo -e "     -> ${RED}Fallo. Ejecuta: sudo chmod -R 777 $d${NC}"
            REQUISITOS_OK=false
        fi
    fi
done

echo -e "\n${BLUE}================================================================${NC}"
if [ "$REQUISITOS_OK" = true ]; then
    echo -e "${GREEN}[CONCLUIDO] El entorno está listo para la ejecución.${NC}"
    echo -e "Próximo paso: Ejecutar configuración de BD o iniciar servidores."
else
    echo -e "${RED}[ERROR] El entorno NO cumple con todos los requisitos.${NC}"
    echo -e "Por favor, soluciona los problemas listados arriba."
fi
echo -e "${BLUE}================================================================${NC}"
echo
