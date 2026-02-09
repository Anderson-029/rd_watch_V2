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

# 1. Cargar configuración
if [ -f "$ENV_FILE" ]; then
    log "${BLUE}Cargando configuración desde $ENV_FILE...${NC}"
    export $(grep -v '^#' "$ENV_FILE" | xargs)
else
    log "${RED}Error: Archivo .env no encontrado.${NC}"
    exit 1
fi

[ -z "$DB_NAME" ] && { log "${RED}DB_NAME no definido.${NC}"; exit 1; }
[ -z "$DB_PASS" ] && { log "${RED}DB_PASS no definido.${NC}"; exit 1; }

export PGPASSWORD="$DB_PASS"
export PGCLIENTENCODING=UTF8
export PGCONNECT_TIMEOUT=10

# Función auxiliar para psql
run_psql() {
    local db="$1"
    local file="$2"
    log "Ejecutando ${YELLOW}$(basename "$file")${NC}..."
    psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$db" -f "$file" >> "$LOG_FILE" 2>&1
}

# 2. Verificar conexión y recrear DB
log "\n${BLUE}--- Paso 1: Preparando Base de Datos ---${NC}"
if ! psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "postgres" -c "SELECT 1" > /dev/null 2>&1; then
    log "${RED}No se pudo conectar al servidor PostgreSQL en $DB_HOST:$DB_PORT.${NC}"
    log "Verifique que el servidor esté activo y las credenciales sean correctas."
    exit 1
fi

log "Terminando sesiones activas en '$DB_NAME'..."
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "postgres" -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();" >> "$LOG_FILE" 2>&1

log "Eliminando base de datos '$DB_NAME' si existe..."
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "postgres" -c "DROP DATABASE IF EXISTS \"$DB_NAME\";" >> "$LOG_FILE" 2>&1

log "Creando base de datos '$DB_NAME'..."
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "postgres" -c "CREATE DATABASE \"$DB_NAME\";" >> "$LOG_FILE" 2>&1

# 3. Esquema
log "\n${BLUE}--- Paso 2: Estructura (Schema) ---${NC}"
run_psql "$DB_NAME" "$BASE_DIR/sql/schema/database_rdwatch_3_0.sql"

# 4. Funciones y Procedimientos
log "\n${BLUE}--- Paso 3: Funciones y Procedimientos ---${NC}"

# Ejecutar todos los archivos .sql en la carpeta de funciones
# Estos están organizados modularmente (crud_usuarios.sql, crud_productos.sql, etc.)
for f in "$BASE_DIR"/sql/functions/*.sql; do
    # Ignorar archivos de inserción de datos masivos que se ejecutan después
    [[ "$f" == *"inserts_"* ]] && continue
    
    [ -e "$f" ] || continue
    run_psql "$DB_NAME" "$f"
done

# 5. Triggers
log "\n${BLUE}--- Paso 4: Triggers ---${NC}"
for t in "$BASE_DIR"/sql/triggers/*.sql; do
    [ -e "$t" ] || continue
    run_psql "$DB_NAME" "$t"
done

# 6. Datos
log "\n${BLUE}--- Paso 5: Carga de Datos ---${NC}"
# Departamentos y Ciudades (Referencial)
if [ -f "$BASE_DIR/sql/functions/inserts_departamentos_y_ciudades.sql" ]; then
    run_psql "$DB_NAME" "$BASE_DIR/sql/functions/inserts_departamentos_y_ciudades.sql"
fi

# Scripts adicionales de población (Usuarios, etc.)
for s in "$BASE_DIR"/sql/scripts/*.sql; do
    [ -e "$s" ] || continue
    # Ignorar create_reviews_table ya que está en schema, pero si existe en scripts lo ejecutamos con precaución
    # Si create_reviews_table.sql tiene IF NOT EXISTS está bien.
    run_psql "$DB_NAME" "$s"
done

log "\n${GREEN}==========================================${NC}"
log "${GREEN}   INSTALACIÓN COMPLETADA EXITOSAMENTE    ${NC}"
log "${GREEN}==========================================${NC}"
log "Log guardado en: $LOG_FILE"
