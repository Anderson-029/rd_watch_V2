-- =====================================================
-- Tabla: tab_Rate_Limits
-- Almacena los intentos de acciones para control de tasa (Rate Limiting).
-- Esto previene ataques de fuerza bruta y denegación de servicio.
-- =====================================================

CREATE TABLE IF NOT EXISTS tab_Rate_Limits (
    id_rate_limit   SERIAL PRIMARY KEY,
    nom_accion      VARCHAR(50) NOT NULL,    -- Nombre de la acción (ej: 'login', 'signup')
    identificador   VARCHAR(100) NOT NULL,   -- Identificador único (IP o ID de usuario)
    fec_intento     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Auditoría (Siguiendo el estándar del proyecto)
    usr_insert      VARCHAR(100) DEFAULT CURRENT_USER,
    fec_insert      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para optimizar la consulta de intentos recientes
CREATE INDEX IF NOT EXISTS idx_rate_limit_lookup 
    ON tab_Rate_Limits (nom_accion, identificador, fec_intento);

-- Comentario de la tabla
COMMENT ON TABLE tab_Rate_Limits IS 'Registro de intentos de acciones sensibles para control de flujo y seguridad.';
