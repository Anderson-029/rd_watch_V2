#!/bin/bash

# RD WATCH - Lanzador Maestro para Unix (Linux/macOS)
# Orquesta el despliegue completo del ecosistema.

# Colores
GOLD='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${GOLD}================================================================${NC}"
echo -e "${GOLD}           RD WATCH - Lanzador Maestro de Ecosistema           ${NC}"
echo -e "${GOLD}================================================================${NC}"
echo

# 0. Dar permisos
chmod +x setup/unix/*.sh

# 1. Verificar Requisitos
echo -e "${BLUE}[PASO 1] Verificando requisitos...${NC}"
./setup/unix/00_verificar_requisitos.sh
if [ $? -ne 0 ]; then
    echo -e "${RED}[ERROR] Requisitos no cumplidos. Abortando.${NC}"
    exit 1
fi

# 2. Configurar Base de Datos
echo -e "\n${BLUE}[PASO 2] Configurando Base de Datos...${NC}"
./setup/unix/01_configurar_bd.sh
if [ $? -ne 0 ]; then
    echo -e "${YELLOW}[AVISO] Error en configuración de BD o ya estaba configurada.${NC}"
    echo "Si es la primera vez, revisa los requisitos y credenciales en backend/.env"
fi

# 3. Iniciar Servidores
echo -e "\n${BLUE}[PASO 3] Iniciando Servidores...${NC}"
./setup/unix/02_iniciar_servidores.sh
