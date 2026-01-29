-- ============================================
-- Verificar token (por "recencia" de sesión)
-- ============================================
CREATE OR REPLACE FUNCTION fun_verificar_token(
    p_id_usuario tab_Usuarios.id_usuario%TYPE,
    p_token      TEXT
) RETURNS BOOLEAN AS $$
DECLARE
    v_ultimo_acceso tab_Usuarios.ultimo_acceso%TYPE;
BEGIN
    SELECT ultimo_acceso INTO v_ultimo_acceso
      FROM tab_Usuarios
     WHERE id_usuario = p_id_usuario;

    IF NOT FOUND THEN
        RETURN FALSE;
    END IF;

    -- Aquí asumimos token "stateless"; validamos ventana de 7 días
    RETURN v_ultimo_acceso > (CURRENT_TIMESTAMP - INTERVAL '7 days');
END;
$$ LANGUAGE plpgsql;
