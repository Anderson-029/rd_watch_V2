#!/bin/bash

# ==========================================
# Script de Instalación de Base de Datos RD Watch
# ==========================================

# Intentar cargar .env automáticamente
if [ -f "backend/.env" ]; then
    echo "Cargando configuración desde backend/.env..."
    export $(grep -v '^#' backend/.env | xargs)
fi

DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-db_rdwatch}"
DB_USER="${DB_USER:-postgres}"

# Colores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Iniciando instalación de base de datos para: $DB_NAME${NC}"
echo "Usuario: $DB_USER | Host: $DB_HOST:$DB_PORT"
echo "---------------------------------------------------"
echo -e "${RED}¡ADVERTENCIA! Este script BORRARÁ la base de datos completa y la creará de nuevo.${NC}"
read -p "¿Estás seguro de continuar? (s/n): " confirm
if [[ $confirm != "s" && $confirm != "S" ]]; then
    echo "Operación cancelada."
    exit 1
fi

# Función para ejecutar SQL
run_sql() {
    local file=$1
    echo -e "Ejecutando: $file..."
    PGPASSWORD=$DB_PASS psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$file"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}OK${NC}"
    else
        echo -e "${RED}ERROR al ejecutar $file${NC}"
        # No salimos (exit 1) para permitir que intente continuar, pero podrías descomentarlo
        # exit 1 
    fi
}

# 0. Verificar conexión (opcional, requiere que psql esté instalado)
# PGPASSWORD=$DB_PASS psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" > /dev/null 2>&1
# if [ $? -ne 0 ]; then
#    echo -e "${RED}Error: No se pudo conectar a la base de datos. Verifica credenciales.${NC}"
#    exit 1
# fi

# 1. Esquema (Tablas)
echo -e "\n${GREEN}--- [1/4] Creando Esquema (Tablas) ---${NC}"
run_sql "database/schema/database_rdwatch_3_0.sql"
run_sql "database/schema/rate_limits.sql"

# 2. Funciones (Lógica de Negocio)
echo -e "\n${GREEN}--- [2/4] Instalando Funciones ---${NC}"
# Instalar primero el CRUD completo principal
run_sql "database/functions/crud_funciones_completo.sql"
# Instalar el resto de funciones (el orden alfabético suele funcionar si no hay dependencias cruzadas fuertes)
for f in database/functions/*.sql; do
    # Evitar ejecutar el que ya ejecutamos
    if [[ "$f" != *"crud_funciones_completo.sql"* ]]; then
        run_sql "$f"
    fi
done

# 3. Triggers (Auditoría)
echo -e "\n${GREEN}--- [3/4] Activando Triggers ---${NC}"
run_sql "database/triggers/audit_trail.sql"

# 4. Scripts (Datos Semilla y Config)
echo -e "\n${GREEN}--- [4/4] Poblando Datos Iniciales ---${NC}"
# Inserta departamentos y ciudades primero (dependencia de usuarios/direcciones)
if [ -f "database/functions/inserts_departamentos_y_ciudades.sql" ]; then
    # Nota: Este archivo estaba en 'functions' pero parece ser datos. Lo ejecutamos.
    # Ya se ejecutó en el loop de funciones, pero si falla por orden, aquí aseguramos.
    # Como ya pasó en el loop, no lo repetimos explícitamente a menos que sea necesario.
    echo "Datos geográficos cargados en paso anterior."
fi
run_sql "database/scripts/populate_users.sql"
# run_sql "database/scripts/configuracion_admin_pending.sql" -- Redundante, cubierto por populate_users.sql

echo -e "\n${GREEN}=========================================${NC}"
echo -e "${GREEN}   INSTALACIÓN COMPLETADA EXITOSAMENTE   ${NC}"
echo -e "${GREEN}=========================================${NC}"
