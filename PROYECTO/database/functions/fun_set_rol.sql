-- =====================================================
-- FUNCIÓN: Asignar rol a un usuario
-- =====================================================
CREATE OR REPLACE FUNCTION fun_set_rol_usuario(
    p_id_usuario tab_Usuarios.id_usuario%TYPE,
    p_rol        tab_Usuarios.rol%TYPE
) RETURNS TABLE (
    status  TEXT,
    message TEXT
) AS $$
DECLARE
    v_exist BOOLEAN;
BEGIN
    -- Verificar si el usuario existe
    SELECT EXISTS(
        SELECT 1 FROM tab_Usuarios WHERE id_usuario = p_id_usuario
    ) INTO v_exist;

    IF NOT v_exist THEN
        RETURN QUERY SELECT 
            'ERROR'::TEXT,
            'Usuario no encontrado'::TEXT;
        RETURN;
    END IF;

    -- Actualizar rol
    UPDATE tab_Usuarios
       SET rol = p_rol,
           usr_update = CURRENT_USER,
           fec_update = CURRENT_TIMESTAMP
     WHERE id_usuario = p_id_usuario;

    RETURN QUERY SELECT
        'SUCCESS'::TEXT,
        'Rol actualizado correctamente'::TEXT;

END;
$$ LANGUAGE plpgsql;
