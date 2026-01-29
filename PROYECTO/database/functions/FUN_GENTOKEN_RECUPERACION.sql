-- ============================================
-- Generar token de recuperación
-- ============================================
CREATE OR REPLACE FUNCTION fun_generar_token_recuperacion(
    p_email tab_Usuarios.correo_usuario%TYPE
) RETURNS TABLE (
    status  TEXT,
    message TEXT,
    token   TEXT
) AS $$
DECLARE
    v_id    tab_Usuarios.id_usuario%TYPE;
    v_token TEXT;
BEGIN
    SELECT id_usuario INTO v_id
      FROM tab_Usuarios
     WHERE LOWER(correo_usuario)=LOWER(p_email)
       AND activo = TRUE;

    IF NOT FOUND THEN
        RETURN QUERY SELECT 'ERROR','Email no registrado',NULL::TEXT;
        RETURN;
    END IF;

    v_token := MD5(v_id::text || p_email || clock_timestamp()::text || random()::text);

    UPDATE tab_Usuarios
       SET token_recuperacion = v_token,
           token_expiracion   = CURRENT_TIMESTAMP + INTERVAL '1 hour',
           usr_update         = CURRENT_USER,
           fec_update         = CURRENT_TIMESTAMP
     WHERE id_usuario = v_id;

    RETURN QUERY SELECT 'SUCCESS','Token de recuperación generado', v_token;
END;
$$ LANGUAGE plpgsql;


-- ============================================
-- Recuperar contraseña con token
-- ============================================
CREATE OR REPLACE FUNCTION fun_recuperar_password(
    p_token          TEXT,
    p_password_nueva TEXT
) RETURNS TABLE (
    status  TEXT,
    message TEXT
) AS $$
DECLARE
    v_u          RECORD;
    v_nuevo_salt tab_Usuarios.salt%TYPE;
    v_nuevo_hash tab_Usuarios.contra%TYPE;
BEGIN
    IF LENGTH(p_password_nueva) < 8 THEN
        RETURN QUERY SELECT 'ERROR','La contraseña debe tener al menos 8 caracteres';
        RETURN;
    END IF;

    SELECT * INTO v_u
      FROM tab_Usuarios
     WHERE token_recuperacion = p_token
       AND token_expiracion   > CURRENT_TIMESTAMP;

    IF NOT FOUND THEN
        RETURN QUERY SELECT 'ERROR','Token inválido o expirado';
        RETURN;
    END IF;

    v_nuevo_salt := MD5(random()::text || clock_timestamp()::text);
    v_nuevo_hash := MD5(p_password_nueva || v_nuevo_salt);

    UPDATE tab_Usuarios
       SET contra            = v_nuevo_hash,
           salt              = v_nuevo_salt,
           token_recuperacion= NULL,
           token_expiracion  = NULL,
           bloqueado         = FALSE,
           intentos_fallidos = 0,
           usr_update        = CURRENT_USER,
           fec_update        = CURRENT_TIMESTAMP
     WHERE id_usuario = v_u.id_usuario;

    RETURN QUERY SELECT 'SUCCESS','Contraseña recuperada correctamente';
END;
$$ LANGUAGE plpgsql;
