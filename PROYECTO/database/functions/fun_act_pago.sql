-- ===========================================
-- FUNCIÓN: Actualizar Método de Pago de Usuario
-- ===========================================
CREATE OR REPLACE FUNCTION fun_actualizar_pago(
    wid_usuario          tab_Usuarios.id_usuario%TYPE,
    wid_metodo_pago      tab_Metodos_Pago.id_metodo_pago%TYPE,
    wnum_tarjeta         VARCHAR,   -- últimos 4 dígitos u otro identificador
    wfecha_vencimiento   DATE DEFAULT NULL
) RETURNS TEXT AS
$$
DECLARE
    v_reg_usuario RECORD;
    v_reg_metodo  RECORD;
BEGIN
    -- ===============================
    -- VALIDAR USUARIO
    -- ===============================
    SELECT * INTO v_reg_usuario
    FROM tab_Usuarios
    WHERE id_usuario = wid_usuario;

    IF NOT FOUND THEN
        RETURN 'ERROR: Usuario con ID ' || wid_usuario || ' no existe';
    END IF;

    IF v_reg_usuario.activo = FALSE THEN
        RETURN 'ERROR: Usuario ' || v_reg_usuario.nom_usuario || ' está inactivo';
    END IF;

    -- ===============================
    -- VALIDAR MÉTODO DE PAGO
    -- ===============================
    SELECT * INTO v_reg_metodo
    FROM tab_Metodos_Pago
    WHERE id_metodo_pago = wid_metodo_pago;

    IF NOT FOUND THEN
        RETURN 'ERROR: Método de pago con ID ' || wid_metodo_pago || ' no existe';
    END IF;

    -- ===============================
    -- ACTUALIZAR O INSERTAR PAGO
    -- ===============================
    IF EXISTS (
        SELECT 1 FROM tab_Usuario_Metodo_Pago
        WHERE id_usuario = wid_usuario
          AND id_metodo_pago = wid_metodo_pago
    ) THEN
        -- Actualizar
        UPDATE tab_Usuario_Metodo_Pago
        SET num_tarjeta = wnum_tarjeta,
            fecha_vencimiento = wfecha_vencimiento,
            usr_update = CURRENT_USER,
            fec_update = CURRENT_TIMESTAMP
        WHERE id_usuario = wid_usuario
          AND id_metodo_pago = wid_metodo_pago;

        RETURN 'SUCCESS: Método de pago actualizado correctamente';
    ELSE
        -- Insertar nuevo
        INSERT INTO tab_Usuario_Metodo_Pago (
            id_usuario, id_metodo_pago, num_tarjeta, fecha_vencimiento,
            usr_insert, fec_insert
        ) VALUES (
            wid_usuario, wid_metodo_pago, wnum_tarjeta, wfecha_vencimiento,
            CURRENT_USER, CURRENT_TIMESTAMP
        );

        RETURN 'SUCCESS: Método de pago registrado correctamente';
    END IF;

EXCEPTION
    WHEN OTHERS THEN
        RETURN 'ERROR: ' || SQLERRM;
END;
$$
LANGUAGE plpgsql;
