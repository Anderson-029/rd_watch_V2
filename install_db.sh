#!/bin/bash

# Configuración de seguridad y errores
set -e
set -o pipefail

# Colores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directorio base
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$BASE_DIR/src/backend/.env"
LOG_FILE="$BASE_DIR/install_db.log"

# Limpiar log anterior
> "$LOG_FILE"

log() {
    echo -e "$1"
    echo -e "$(date '+%Y-%m-%d %H:%M:%S') $1" >> "$LOG_FILE"
}

error_handler() {
    log "${RED}Error en la línea $1 del script.${NC}"
    exit 1
}
trap 'error_handler $LINENO' ERR

log "${YELLOW}=== Iniciando instalación de base de datos RD Watch V2 ===${NC}"

# 1. Cargar configuración de forma robusta
if [ -f "$ENV_FILE" ]; then
    log "${BLUE}Cargando configuración desde $ENV_FILE...${NC}"
    # Leer el archivo línea por línea para evitar problemas con espacios o caracteres especiales
    while IFS='=' read -r key value || [ -n "$key" ]; do
        # Ignorar comentarios y líneas vacías
        [[ "$key" =~ ^#.*$ ]] || [ -z "$key" ] && continue
        # Limpiar espacios y exportar
        key=$(echo "$key" | xargs)
        value=$(echo "$value" | xargs)
        export "$key=$value"
    done < "$ENV_FILE"
else
    log "${RED}Error: Archivo .env no encontrado en $ENV_FILE.${NC}"
    exit 1
fi

[ -z "$DB_NAME" ] && { log "${RED}Error: DB_NAME no definido en el .env.${NC}"; exit 1; }
[ -z "$DB_PASS" ] && { log "${RED}Error: DB_PASS no definido en el .env.${NC}"; exit 1; }

export PGPASSWORD="$DB_PASS"
export PGCLIENTENCODING=UTF8

# Función auxiliar para psql con manejo de errores mejorado
run_psql() {
    local db="$1"
    local file="$2"
    local filename=$(basename "$file")
    log "Ejecutando ${YELLOW}$filename${NC}..."
    if ! psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$db" -f "$file" >> "$LOG_FILE" 2>&1; then
        log "${RED}Fallo crítico en $filename. Ver install_db.log para detalles.${NC}"
        return 1
    fi
}

# 2. Verificar conexión y recrear DB
log "\n${BLUE}--- Paso 1: Preparando Base de Datos ---${NC}"
if ! psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "postgres" -c "SELECT 1" > /dev/null 2>&1; then
    log "${RED}No se pudo conectar al servidor PostgreSQL en $DB_HOST:$DB_PORT.${NC}"
    exit 1
fi

log "Terminando sesiones activas y recreando '$DB_NAME'..."
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "postgres" <<EOF >> "$LOG_FILE" 2>&1
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();
DROP DATABASE IF EXISTS "$DB_NAME";
CREATE DATABASE "$DB_NAME";
EOF

# 3. Esquema
log "\n${BLUE}--- Paso 2: Estructura (Schema) ---${NC}"
run_psql "$DB_NAME" "$BASE_DIR/sql/schema/database_rdwatch_3_0.sql"

# 4. Funciones y Procedimientos
log "\n${BLUE}--- Paso 3: Funciones y Procedimientos ---${NC}"
for f in "$BASE_DIR"/sql/functions/*.sql; do
    [[ "$f" == *"inserts_"* ]] && continue
    [ -e "$f" ] && run_psql "$DB_NAME" "$f"
done

# 5. Triggers
log "\n${BLUE}--- Paso 4: Triggers ---${NC}"
for t in "$BASE_DIR"/sql/triggers/*.sql; do
    [ -e "$t" ] && run_psql "$DB_NAME" "$t"
done

# 6. Datos y Población
log "\n${BLUE}--- Paso 5: Carga de Datos y Población ---${NC}"

# 6.1. Departamentos y Ciudades (Referencia obligatoria)
if [ -f "$BASE_DIR/sql/functions/inserts_departamentos_y_ciudades.sql" ]; then
    run_psql "$DB_NAME" "$BASE_DIR/sql/functions/inserts_departamentos_y_ciudades.sql"
fi

# 6.2. Scripts de Población y Seeders (Ejecución secuencial controlada)
# El orden numérico (01, 02, 03...) asegura que se cumplan las dependencias.
for s in "$BASE_DIR"/sql/scripts/*.sql; do
    [ -e "$s" ] || continue
    run_psql "$DB_NAME" "$s"
done

log "\n${GREEN}==========================================${NC}"
log "${GREEN}   INSTALACIÓN COMPLETADA EXITOSAMENTE    ${NC}"
log "${GREEN}==========================================${NC}"
log "Log guardado en: $LOG_FILE"
