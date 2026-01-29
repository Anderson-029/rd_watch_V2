-- 1. ACTUALIZAR DATOS BÁSICOS DEL PERFIL
CREATE OR REPLACE FUNCTION fun_actualizar_perfil(
    p_id_usuario BIGINT,
    p_nombre     VARCHAR,
    p_telefono   BIGINT
) RETURNS TABLE (status TEXT, message TEXT) AS $$
BEGIN
    UPDATE tab_Usuarios
    SET nom_usuario = p_nombre,
        num_telefono_usuario = p_telefono,
        usr_update = CURRENT_USER,
        fec_update = CURRENT_TIMESTAMP
    WHERE id_usuario = p_id_usuario;

    RETURN QUERY SELECT 'SUCCESS', 'Perfil actualizado correctamente'::TEXT;
END;
$$ LANGUAGE plpgsql;

-- 2. GESTIONAR DIRECCIONES (Agregar o Actualizar)
CREATE OR REPLACE FUNCTION fun_gestionar_direccion(
    p_id_usuario BIGINT,
    p_direccion  VARCHAR,
    p_ciudad_id  SMALLINT,
    p_postal     VARCHAR
) RETURNS TABLE (status TEXT, message TEXT) AS $$
DECLARE
    v_existe BIGINT;
    v_next_id BIGINT;
BEGIN
    -- Verificar si el usuario ya tiene una dirección
    SELECT id_direccion INTO v_existe FROM tab_Direcciones_Envio 
    WHERE id_usuario = p_id_usuario LIMIT 1;

    IF v_existe IS NOT NULL THEN
        -- Actualizar dirección existente
        UPDATE tab_Direcciones_Envio
        SET direccion_completa = p_direccion,
            id_ciudad = p_ciudad_id,
            codigo_postal = p_postal,
            usr_update = CURRENT_USER,
            fec_update = CURRENT_TIMESTAMP
        WHERE id_direccion = v_existe;
        RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Dirección actualizada'::TEXT;
    ELSE
        -- Generar nuevo ID manualmente (sin SERIAL)
        SELECT COALESCE(MAX(id_direccion), 0) + 1 INTO v_next_id 
        FROM tab_Direcciones_Envio;
        
        -- Insertar nueva dirección
        INSERT INTO tab_Direcciones_Envio (
            id_direccion, id_usuario, direccion_completa, id_ciudad, 
            codigo_postal, es_predeterminada, usr_insert, fec_insert
        )
        VALUES (
            v_next_id, p_id_usuario, p_direccion, p_ciudad_id, p_postal, TRUE,
            CURRENT_USER, CURRENT_TIMESTAMP
        );
        RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Dirección guardada'::TEXT;
    END IF;
END;
$$ LANGUAGE plpgsql;
