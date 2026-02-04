-- ============================================
-- Registrar nuevo usuario (con salt + hash)
-- ============================================
CREATE OR REPLACE FUNCTION fun_registrar_usuario(
    p_nombre     tab_Usuarios.nom_usuario%TYPE,
    p_email      tab_Usuarios.correo_usuario%TYPE,
    p_telefono   tab_Usuarios.num_telefono_usuario%TYPE,
    p_password   TEXT,
    p_direccion  tab_Usuarios.direccion_principal%TYPE DEFAULT NULL
) RETURNS TABLE (
    status       TEXT,
    message      TEXT,
    id_usuario   tab_Usuarios.id_usuario%TYPE,
    token        TEXT
) 
LANGUAGE plpgsql
AS $$
DECLARE
    v_id_usuario      tab_Usuarios.id_usuario%TYPE;
    v_email_existente BOOLEAN;
    v_salt            tab_Usuarios.salt%TYPE;
    v_password_hash   tab_Usuarios.contra%TYPE;
    v_token           TEXT;
BEGIN
    -- 1. Validar si el correo ya existe (case-insensitive recomendado)
    SELECT EXISTS(
        SELECT 1
        FROM tab_Usuarios u
        WHERE LOWER(u.correo_usuario) = LOWER(p_email)
    )
    INTO v_email_existente;

    IF v_email_existente THEN
        status     := 'ERROR';
        message    := 'El email ya está registrado';
        id_usuario := NULL;
        token      := NULL;
        RETURN NEXT;
        RETURN;
    END IF;

    -- 2. Validar longitud mínima de contraseña
    IF LENGTH(p_password) < 8 THEN
        status     := 'ERROR';
        message    := 'La contraseña debe tener al menos 8 caracteres';
        id_usuario := NULL;
        token      := NULL;
        RETURN NEXT;
        RETURN;
    END IF;

    -- 3. Generar nuevo id_usuario (si no usas secuencia/identity)
    SELECT COALESCE(MAX(u.id_usuario), 0) + 1
    INTO v_id_usuario
    FROM tab_Usuarios u;

    -- 4. Generar salt opcional (Bcrypt incluye su propio salt internamente)
    v_salt          := MD5(random()::text || clock_timestamp()::text);
    v_password_hash := p_password; -- Se asume que viene hasheado desde PHP

    -- 5. Insertar el usuario
    INSERT INTO tab_Usuarios (
        id_usuario, nom_usuario, correo_usuario, num_telefono_usuario,
        direccion_principal, fecha_registro, activo,
        contra, salt, intentos_fallidos, bloqueado,
        usr_insert, fec_insert
    ) VALUES (
        v_id_usuario, p_nombre, p_email, p_telefono,
        p_direccion, CURRENT_TIMESTAMP, TRUE,
        v_password_hash, v_salt, 0, FALSE,
        CURRENT_USER, CURRENT_TIMESTAMP
    );

    -- 6. Generar token (por ahora solo como ejemplo con MD5)
    v_token := MD5(v_id_usuario::text || p_email || clock_timestamp()::text);

    -- 7. Asignar valores de retorno
    status     := 'SUCCESS';
    message    := 'Usuario registrado correctamente';
    id_usuario := v_id_usuario;
    token      := v_token;
    RETURN NEXT;

EXCEPTION
    WHEN OTHERS THEN
        status     := 'ERROR';
        message    := 'Error al registrar: ' || SQLERRM;
        id_usuario := NULL;
        token      := NULL;
        RETURN NEXT;
END;
$$;
