#!/bin/bash
# setup_security.sh - Automatización de Seguridad RD Watch (UNIX)

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "Iniciando configuración de seguridad..."
cd "$BASE_DIR"

if ! command -v php &> /dev/null; then
    echo "ERROR: PHP no está instalado o no está en el PATH."
    exit 1
fi

php maintenance/initialize_security.php

if [ $? -eq 0 ]; then
    echo "Seguridad configurada correctamente."
else
    echo "Hubo un error configurando la seguridad."
    exit 1
fi
