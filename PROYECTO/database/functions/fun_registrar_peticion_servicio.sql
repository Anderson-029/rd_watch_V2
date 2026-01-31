CREATE OR REPLACE FUNCTION fun_registrar_peticion_servicio(
    p_id_usuario    tab_Usuarios.id_usuario%TYPE,
    p_id_servicio   tab_Servicios.id_servicio%TYPE,
    p_fecha_pref    DATE DEFAULT CURRENT_DATE,
    p_notas         TEXT DEFAULT NULL,
    p_prioridad     TEXT DEFAULT 'normal'
) RETURNS BIGINT AS
$$
DECLARE
    v_usr         RECORD;
    v_srv         RECORD;
    v_id_reserva  BIGINT;
BEGIN
    -- 1. VALIDAR USUARIO ACTIVO
    SELECT id_usuario, nom_usuario, activo INTO v_usr
    FROM tab_Usuarios WHERE id_usuario = p_id_usuario;

    IF NOT FOUND THEN RAISE EXCEPTION 'Error: El usuario no existe'; END IF;
    IF v_usr.activo = FALSE THEN RAISE EXCEPTION 'Error: Usuario inactivo'; END IF;

    -- 2. VALIDAR SERVICIO (Eliminamos la restricción de nombres fijos)
    SELECT id_servicio, nom_servicio INTO v_srv
    FROM tab_Servicios WHERE id_servicio = p_id_servicio;

    IF NOT FOUND THEN RAISE EXCEPTION 'Error: El servicio no existe'; END IF;

    -- 3. VALIDAR FECHA
    IF p_fecha_pref < CURRENT_DATE THEN
        RAISE EXCEPTION 'Error: La fecha no puede ser anterior a hoy';
    END IF;

    -- 4. INSERTAR RESERVA
    SELECT COALESCE(MAX(id_reserva),0) + 1 INTO v_id_reserva FROM tab_Reservas;

    INSERT INTO tab_Reservas (
        id_reserva, id_usuario, id_servicio, fecha_solicitada, fecha_preferida,
        notas_cliente, estado_reserva, prioridad, usr_insert, fec_insert
    ) VALUES (
        v_id_reserva, p_id_usuario, p_id_servicio, CURRENT_TIMESTAMP, p_fecha_pref,
        NULLIF(TRIM(p_notas),''), 'pendiente', p_prioridad, CURRENT_USER, CURRENT_TIMESTAMP
    );

    RETURN v_id_reserva;
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'Error: %', SQLERRM;
        RETURN -1;
END;
$$ LANGUAGE plpgsql;