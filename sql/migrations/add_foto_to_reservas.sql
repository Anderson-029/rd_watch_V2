/**
 * MIGRATION: Agregar columnas para fotos adjuntas en reservas
 * Fecha: 2026-02-11
 * Propósito: Permitir que el formulario de contacto guarde fotos adjuntas
 *            directamente en la base de datos como datos binarios (BYTEA)
 */

-- Agregar columnas para almacenar la foto y su extensión
ALTER TABLE tab_Reservas 
ADD COLUMN IF NOT EXISTS foto_adjunto BYTEA,
ADD COLUMN IF NOT EXISTS foto_extension VARCHAR(10);

-- Crear índice para búsquedas rápidas de reservas con fotos
CREATE INDEX IF NOT EXISTS idx_reservas_con_foto 
ON tab_Reservas (id_reserva) 
WHERE foto_adjunto IS NOT NULL;

-- Comentarios para documentación
COMMENT ON COLUMN tab_Reservas.foto_adjunto IS 'Archivo de imagen adjunto en formato binario (BYTEA). Formatos permitidos: JPG, PNG, SVG';
COMMENT ON COLUMN tab_Reservas.foto_extension IS 'Extensión del archivo adjunto (jpg, png, svg) para reconstruir el MIME type al servir el archivo';
