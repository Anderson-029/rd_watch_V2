-- ==========================================
-- FUNCIÓN: Obtener historial de pedidos del usuario
-- ==========================================
CREATE OR REPLACE FUNCTION fun_obtener_pedidos_usuario(
    p_id_usuario tab_Usuarios.id_usuario%TYPE
) RETURNS TABLE (
    id_orden INT,
    fecha_orden TIMESTAMP,
    estado_orden VARCHAR,
    total_orden DECIMAL,
    concepto VARCHAR
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        o.id_orden,
        o.fecha_orden,
        o.estado_orden,
        o.total_orden,
        o.concepto
    FROM tab_Orden o
    WHERE o.id_usuario = p_id_usuario
    ORDER BY o.fecha_orden DESC;
END;
$$ LANGUAGE plpgsql;

-- ==========================================
-- FUNCIÓN: Obtener dirección del usuario
-- ==========================================
CREATE OR REPLACE FUNCTION fun_obtener_direccion_usuario(
    p_id_usuario tab_Usuarios.id_usuario%TYPE
) RETURNS TABLE (
    id_direccion BIGINT,
    direccion_completa VARCHAR,
    id_ciudad SMALLINT,
    codigo_postal VARCHAR,
    es_predeterminada BOOLEAN
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        d.id_direccion,
        d.direccion_completa,
        d.id_ciudad,
        d.codigo_postal,
        d.es_predeterminada
    FROM tab_Direcciones_Envio d
    WHERE d.id_usuario = p_id_usuario
    ORDER BY d.es_predeterminada DESC
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- ==========================================
-- FUNCIÓN: Obtener método de pago del usuario
-- ==========================================
CREATE OR REPLACE FUNCTION fun_obtener_metodo_pago_usuario(
    p_id_usuario tab_Usuarios.id_usuario%TYPE
) RETURNS TABLE (
    id_metodo_pago SMALLINT,
    nombre_metodo VARCHAR,
    num_tarjeta VARCHAR,
    fecha_vencimiento DATE
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        mp.id_metodo_pago,
        mp.nombre_metodo,
        ump.num_tarjeta,
        ump.fecha_vencimiento
    FROM tab_Usuario_Metodo_Pago ump
    INNER JOIN tab_Metodos_Pago mp ON ump.id_metodo_pago = mp.id_metodo_pago
    WHERE ump.id_usuario = p_id_usuario
    ORDER BY ump.fec_insert DESC
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- ==========================================
-- FUNCIÓN: Actualizar método de pago 
-- ==========================================
CREATE OR REPLACE FUNCTION fun_actualizar_metodo_pago(
    p_id_usuario tab_Usuarios.id_usuario%TYPE,
    p_id_metodo_pago tab_Metodos_Pago.id_metodo_pago%TYPE,
    p_num_tarjeta VARCHAR,
    p_fecha_vencimiento DATE DEFAULT NULL
) RETURNS TABLE (
    status TEXT,
    message TEXT
) AS $$
DECLARE
    v_usuario_existe BOOLEAN;
    v_metodo_existe BOOLEAN;
BEGIN
    -- Validar usuario
    SELECT EXISTS(
        SELECT 1 FROM tab_Usuarios WHERE id_usuario = p_id_usuario AND activo = TRUE
    ) INTO v_usuario_existe;
    
    IF NOT v_usuario_existe THEN
        RETURN QUERY SELECT 'ERROR', 'Usuario no válido o inactivo';
        RETURN;
    END IF;

    -- Validar método de pago
    SELECT EXISTS(
        SELECT 1 FROM tab_Metodos_Pago WHERE id_metodo_pago = p_id_metodo_pago
    ) INTO v_metodo_existe;
    
    IF NOT v_metodo_existe THEN
        RETURN QUERY SELECT 'ERROR', 'Método de pago no válido';
        RETURN;
    END IF;

    -- Verificar si ya existe una relación
    IF EXISTS (
        SELECT 1 FROM tab_Usuario_Metodo_Pago
        WHERE id_usuario = p_id_usuario AND id_metodo_pago = p_id_metodo_pago
    ) THEN
        -- Actualizar
        UPDATE tab_Usuario_Metodo_Pago
        SET num_tarjeta = p_num_tarjeta,
            fecha_vencimiento = p_fecha_vencimiento,
            usr_update = CURRENT_USER,
            fec_update = CURRENT_TIMESTAMP
        WHERE id_usuario = p_id_usuario AND id_metodo_pago = p_id_metodo_pago;
        
        RETURN QUERY SELECT 'SUCCESS', 'Método de pago actualizado correctamente';
    ELSE
        -- Insertar nuevo
        INSERT INTO tab_Usuario_Metodo_Pago (
            id_usuario_metodo_pago, id_usuario, id_metodo_pago, 
            num_tarjeta, fecha_vencimiento, usr_insert, fec_insert
        ) VALUES (
            (SELECT COALESCE(MAX(id_usuario_metodo_pago), 0) + 1 FROM tab_Usuario_Metodo_Pago),
            p_id_usuario, p_id_metodo_pago, p_num_tarjeta, p_fecha_vencimiento,
            CURRENT_USER, CURRENT_TIMESTAMP
        );
        
        RETURN QUERY SELECT 'SUCCESS', 'Método de pago registrado correctamente';
    END IF;

EXCEPTION
    WHEN OTHERS THEN
        RETURN QUERY SELECT 'ERROR', 'Error: ' || SQLERRM;
END;
$$ LANGUAGE plpgsql;