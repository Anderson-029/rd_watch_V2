-- ============================================
-- Iniciar sesión (login) - VERSIÓN FINAL CON VARIABLES INTERMEDIAS
-- ============================================
DROP FUNCTION IF EXISTS fun_login_usuario(character varying, text);

CREATE OR REPLACE FUNCTION fun_login_usuario(
    p_email     tab_Usuarios.correo_usuario%TYPE,
    p_password  TEXT
) RETURNS TABLE (
    status     TEXT,
    message    TEXT,
    ret_id_usuario tab_Usuarios.id_usuario%TYPE,
    ret_nombre     tab_Usuarios.nom_usuario%TYPE,
    ret_email      tab_Usuarios.correo_usuario%TYPE,
    ret_telefono   tab_Usuarios.num_telefono_usuario%TYPE,
    ret_direccion  tab_Usuarios.direccion_principal%TYPE,
    ret_token      TEXT
) AS $$
DECLARE
    v_usuario        RECORD;
    v_hash           tab_Usuarios.contra%TYPE;
    v_token          TEXT;
    v_intentos       tab_Usuarios.intentos_fallidos%TYPE;
    -- Variables de retorno con tipos de la tabla
    v_ret_id         tab_Usuarios.id_usuario%TYPE;
    v_ret_nombre     tab_Usuarios.nom_usuario%TYPE;
    v_ret_email      tab_Usuarios.correo_usuario%TYPE;
    v_ret_telefono   tab_Usuarios.num_telefono_usuario%TYPE;
    v_ret_direccion  tab_Usuarios.direccion_principal%TYPE;
BEGIN
    -- Buscar por correo (case-insensitive)
    SELECT u.* INTO v_usuario
      FROM tab_Usuarios u
     WHERE LOWER(u.correo_usuario) = LOWER(p_email);

    IF NOT FOUND THEN
        status := 'ERROR';
        message := 'Credenciales inválidas';
        ret_id_usuario := NULL;
        ret_nombre := NULL;
        ret_email := NULL;
        ret_telefono := NULL;
        ret_direccion := NULL;
        ret_token := NULL;
        RETURN NEXT;
        RETURN;
    END IF;

    IF NOT v_usuario.activo THEN
        status := 'ERROR';
        message := 'Usuario inactivo. Contacte al administrador';
        ret_id_usuario := NULL;
        ret_nombre := NULL;
        ret_email := NULL;
        ret_telefono := NULL;
        ret_direccion := NULL;
        ret_token := NULL;
        RETURN NEXT;
        RETURN;
    END IF;

    IF v_usuario.bloqueado THEN
        status := 'ERROR';
        message := 'Usuario bloqueado por múltiples intentos fallidos';
        ret_id_usuario := NULL;
        ret_nombre := NULL;
        ret_email := NULL;
        ret_telefono := NULL;
        ret_direccion := NULL;
        ret_token := NULL;
        RETURN NEXT;
        RETURN;
    END IF;

    -- Verificar contraseña (DESACTIVADO EN SQL, SE HACE EN PHP)
    -- v_hash := MD5(p_password || v_usuario.salt);

    -- Éxito parcial: Encontramos el usuario, devolvemos data para que PHP verifique
    v_ret_id := v_usuario.id_usuario;
    v_ret_nombre := v_usuario.nom_usuario;
    v_ret_email := v_usuario.correo_usuario;
    v_ret_telefono := v_usuario.num_telefono_usuario;
    v_ret_direccion := COALESCE(v_usuario.direccion_principal, '');
    v_token := MD5(v_usuario.id_usuario::text || v_usuario.correo_usuario || clock_timestamp()::text);

    -- Retornar valores (status SUCCESS aquí solo significa 'Usuario Encontrado')
    status := 'SUCCESS';
    message := 'Usuario encontrado';
    ret_id_usuario := v_ret_id;
    ret_nombre := v_ret_nombre;
    ret_email := v_ret_email;
    ret_telefono := v_ret_telefono;
    ret_direccion := v_ret_direccion;
    ret_token := v_usuario.contra; -- DEVOLVEMOS EL HASH EN ESTE CAMPO PARA PHP
    RETURN NEXT;
END;
$$ LANGUAGE plpgsql;
