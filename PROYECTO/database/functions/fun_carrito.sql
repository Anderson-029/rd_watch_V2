-- ===========================================
-- FUNCIÓN: Agregar producto al carrito
-- ===========================================
CREATE OR REPLACE FUNCTION fun_agregar_carrito(
    wid_usuario   tab_Usuarios.id_usuario%TYPE,
    wid_producto  tab_Productos.id_producto%TYPE,
    wcantidad     tab_Carrito_Detalle.cantidad%TYPE
) RETURNS TEXT AS
$$
DECLARE
    v_id_carrito         tab_Carrito.id_carrito%TYPE;
    v_id_carrito_detalle tab_Carrito_Detalle.id_carrito_detalle%TYPE;
    v_reg_producto       RECORD;
BEGIN
    -- ===============================
    -- VALIDAR USUARIO
    -- ===============================
    IF NOT EXISTS (
        SELECT 1 FROM tab_Usuarios 
        WHERE id_usuario = wid_usuario AND activo = TRUE
    ) THEN
        RETURN 'ERROR: Usuario no válido o inactivo';
    END IF;

    -- ===============================
    -- VALIDAR PRODUCTO
    -- ===============================
    SELECT * INTO v_reg_producto
    FROM tab_Productos
    WHERE id_producto = wid_producto;

    IF NOT FOUND THEN
        RETURN 'ERROR: Producto no existe';
    END IF;

    IF v_reg_producto.estado = FALSE THEN
        RETURN 'ERROR: Producto inactivo';
    END IF;

    IF v_reg_producto.stock < wcantidad THEN
        RETURN 'ERROR: Stock insuficiente (disponible: ' || v_reg_producto.stock || ')';
    END IF;

    -- ===============================
    -- BUSCAR O CREAR CARRITO ACTIVO
    -- ===============================
    SELECT id_carrito INTO v_id_carrito
    FROM tab_Carrito
    WHERE id_usuario = wid_usuario 
      AND estado_carrito = 'activo'
    LIMIT 1;

    IF NOT FOUND THEN
        -- Crear nuevo carrito
        SELECT COALESCE(MAX(id_carrito),0)+1 INTO v_id_carrito FROM tab_Carrito;

        INSERT INTO tab_Carrito (
            id_carrito, id_usuario, fecha_creacion, fecha_ultima_actualizacion,
            estado_carrito, usr_insert, fec_insert
        ) VALUES (
            v_id_carrito, wid_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP,
            'activo', CURRENT_USER, CURRENT_TIMESTAMP
        );
    END IF;

    -- ===============================
    -- AGREGAR O ACTUALIZAR DETALLE
    -- ===============================
    IF EXISTS (
        SELECT 1 FROM tab_Carrito_Detalle
        WHERE id_carrito = v_id_carrito AND id_producto = wid_producto
    ) THEN
        -- Sumar cantidad al producto ya en el carrito
        UPDATE tab_Carrito_Detalle
        SET cantidad   = cantidad + wcantidad,
            usr_update = CURRENT_USER,
            fec_update = CURRENT_TIMESTAMP
        WHERE id_carrito = v_id_carrito 
          AND id_producto = wid_producto;

        UPDATE tab_Carrito
        SET fecha_ultima_actualizacion = CURRENT_TIMESTAMP,
            usr_update = CURRENT_USER,
            fec_update = CURRENT_TIMESTAMP
        WHERE id_carrito = v_id_carrito;

    ELSE
        -- Generar nuevo id_carrito_detalle
        SELECT COALESCE(MAX(id_carrito_detalle),0)+1 
        INTO v_id_carrito_detalle 
        FROM tab_Carrito_Detalle;

        -- Insertar producto nuevo en el carrito
        INSERT INTO tab_Carrito_Detalle (
            id_carrito_detalle, id_carrito, id_producto, cantidad,
            usr_insert, fec_insert
        ) VALUES (
            v_id_carrito_detalle, v_id_carrito, wid_producto, wcantidad,
            CURRENT_USER, CURRENT_TIMESTAMP
        );

        UPDATE tab_Carrito
        SET fecha_ultima_actualizacion = CURRENT_TIMESTAMP,
            usr_update = CURRENT_USER,
            fec_update = CURRENT_TIMESTAMP
        WHERE id_carrito = v_id_carrito;
    END IF;

    RETURN 'SUCCESS: Producto agregado al carrito (Carrito ID: ' || v_id_carrito || ')';

EXCEPTION
    WHEN OTHERS THEN
        RETURN 'ERROR: ' || SQLERRM;
END;
$$
LANGUAGE plpgsql;
