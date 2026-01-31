#!/bin/bash
# RD Watch - Script de Configuracion de Base de Datos (Unix)

# Cambiar al directorio raiz del proyecto para que las rutas relativas funcionen
cd "$(dirname "$0")/../.."
BASE_DIR=$(pwd)

# Variables
DB_NAME="rdwatch"
DB_USER="postgres"
DB_HOST="localhost"
DB_PORT="5432"
export PGPASSWORD="ander123"

echo "[INFO] Iniciando configuracion de base de datos RD Watch..."

# 0. Crear Base de Datos si no existe
echo "[INFO] Verificando existencia de la base de datos '$DB_NAME'..."
DB_EXISTS=$(psql -h $DB_HOST -p $DB_PORT -U $DB_USER -lqt | cut -d \| -f 1 | grep -w $DB_NAME)

if [ -z "$DB_EXISTS" ]; then
    echo "[INFO] La base de datos no existe. Creandola..."
    createdb -h $DB_HOST -p $DB_PORT -U $DB_USER $DB_NAME
    if [ $? -eq 0 ]; then
        echo "[OK] Base de datos '$DB_NAME' creada con exito."
    else
        echo "[ERROR] No se pudo crear la base de datos."
        exit 1
    fi
else
    echo "[INFO] La base de datos '$DB_NAME' ya existe."
fi

# 1. Esquema Principal
echo "[INFO] Cargando esquema principal..."
psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f "database/schema/database_rdwatch_3_0.sql" --quiet

# 2. Tablas Adicionales y Rate Limits
echo "[INFO] Cargando tablas de control adicionales..."
psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f "database/schema/rate_limits.sql" --quiet

# 3. Funciones (CRUD y otros)
echo "[INFO] Cargando funciones..."
for f in database/functions/*.sql; do
    psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f "$f" --quiet
done

# 4. Disparadores (Triggers)
echo "[INFO] Cargando disparadores..."
for t in database/triggers/*.sql; do
    psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f "$t" --quiet
done

# 5. Scripts de Datos Iniciales y Usuarios
echo "[INFO] Cargando datos iniciales y usuarios de prueba..."
for s in database/scripts/*.sql; do
    echo "  - Procesando: $s"
    psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f "$s" --quiet
done

echo "[OK] Configuracion de base de datos completada con exito."

# 6. Automatizacion de Seguridad
echo "[INFO] Aplicando blindaje de seguridad automatica..."
bash "$BASE_DIR/setup/unix/setup_security.sh"
