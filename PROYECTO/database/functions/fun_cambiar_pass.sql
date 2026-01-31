-- ============================================
-- Cambiar contraseña
-- ============================================
CREATE OR REPLACE FUNCTION fun_cambiar_password(
    p_id_usuario      tab_Usuarios.id_usuario%TYPE,
    p_password_actual TEXT,
    p_password_nueva  TEXT
) RETURNS TABLE (
    status  TEXT,
    message TEXT
) AS $$
DECLARE
    v_u           RECORD;
    v_hash_actual tab_Usuarios.contra%TYPE;
    v_nuevo_salt  tab_Usuarios.salt%TYPE;
    v_nuevo_hash  tab_Usuarios.contra%TYPE;
BEGIN
    SELECT * INTO v_u
      FROM tab_Usuarios
     WHERE id_usuario = p_id_usuario;

    IF NOT FOUND THEN
        RETURN QUERY SELECT 'ERROR','Usuario no encontrado';
        RETURN;
    END IF;

    v_hash_actual := MD5(p_password_actual || v_u.salt);
    IF v_hash_actual <> v_u.contra THEN
        RETURN QUERY SELECT 'ERROR','Contraseña actual incorrecta';
        RETURN;
    END IF;

    IF LENGTH(p_password_nueva) < 8 THEN
        RETURN QUERY SELECT 'ERROR','La nueva contraseña debe tener al menos 8 caracteres';
        RETURN;
    END IF;

    v_nuevo_salt := MD5(random()::text || clock_timestamp()::text);
    v_nuevo_hash := MD5(p_password_nueva || v_nuevo_salt);

    UPDATE tab_Usuarios
       SET contra     = v_nuevo_hash,
           salt       = v_nuevo_salt,
           usr_update = CURRENT_USER,
           fec_update = CURRENT_TIMESTAMP
     WHERE id_usuario = p_id_usuario;

    RETURN QUERY SELECT 'SUCCESS','Contraseña actualizada correctamente';
END;
$$ LANGUAGE plpgsql;
