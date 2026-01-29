-- =====================================================
-- FUNCIÓN DE RECEPCIÓN DE PRODUCTOS DE PROVEEDOR
-- =====================================================
CREATE OR REPLACE FUNCTION fun_recepcion_proveedor(
    p_id_proveedor tab_Proveedor.id_proveedor%TYPE,
    p_arr_productos BIGINT[],
    p_arr_cantidades BIGINT[],
    p_observaciones TEXT DEFAULT NULL
) RETURNS BIGINT AS
$$
DECLARE
    v_reg_proveedor  RECORD;
    v_reg_producto   RECORD;
    v_id_recepcion   BIGINT;
    v_total_productos_recibidos BIGINT := 0;
    i INTEGER;
BEGIN
    -- =====================================================
    -- VALIDACIONES INICIALES
    -- =====================================================
    IF array_length(p_arr_productos, 1) IS DISTINCT FROM array_length(p_arr_cantidades, 1) THEN
        RAISE EXCEPTION 'Error: Los arrays de productos y cantidades deben tener la misma longitud';
    END IF;

    IF array_length(p_arr_productos, 1) < 1 THEN
        RAISE EXCEPTION 'Error: Debe incluir al menos un producto en la recepción';
    END IF;

    FOR i IN 1 .. array_length(p_arr_cantidades, 1) LOOP
        IF p_arr_cantidades[i] <= 0 THEN
            RAISE EXCEPTION 'Error: La cantidad para el producto ID % debe ser mayor a 0', p_arr_productos[i];
        END IF;
    END LOOP;

    IF (SELECT COUNT(DISTINCT unnest) FROM unnest(p_arr_productos)) != array_length(p_arr_productos, 1) THEN
        RAISE EXCEPTION 'Error: No se pueden recibir productos duplicados en la misma recepción';
    END IF;

    -- =====================================================
    -- VALIDAR PROVEEDOR
    -- =====================================================
    SELECT id_proveedor, nom_proveedor, estado
    INTO v_reg_proveedor
    FROM tab_Proveedor
    WHERE id_proveedor = p_id_proveedor;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error: El proveedor con ID % no existe', p_id_proveedor;
    END IF;

    IF v_reg_proveedor.estado = FALSE THEN
        RAISE EXCEPTION 'Error: El proveedor % está inactivo', v_reg_proveedor.nom_proveedor;
    END IF;

    -- =====================================================
    -- VALIDAR Y PROCESAR CADA PRODUCTO
    -- =====================================================
    FOR i IN 1 .. array_length(p_arr_productos, 1) LOOP
        SELECT id_producto, nom_producto, estado, stock
        INTO v_reg_producto
        FROM tab_Productos
        WHERE id_producto = p_arr_productos[i];

        IF NOT FOUND THEN
            RAISE EXCEPTION 'Error: El producto con ID % no existe', p_arr_productos[i];
        END IF;

        IF v_reg_producto.estado = FALSE THEN
            RAISE EXCEPTION 'Error: El producto % está inactivo y no puede recibir stock', v_reg_producto.nom_producto;
        END IF;

        -- Generar ID único para cada recepción
        SELECT COALESCE(MAX(id_recepcion), 0) + 1
        INTO v_id_recepcion
        FROM tab_Recepciones_Proveedor;

        -- Insertar recepción
        INSERT INTO tab_Recepciones_Proveedor (
            id_recepcion, id_producto, id_proveedor, cantidad_recibida, fecha_recepcion,
            usr_insert, fec_insert
        ) VALUES (
            v_id_recepcion,
            p_arr_productos[i],
            p_id_proveedor,
            p_arr_cantidades[i],
            CURRENT_TIMESTAMP,
            CURRENT_USER,
            CURRENT_TIMESTAMP
        );

        -- Actualizar stock del producto
        UPDATE tab_Productos
        SET stock = stock + p_arr_cantidades[i],
            fec_update = CURRENT_TIMESTAMP,
            usr_update = CURRENT_USER
        WHERE id_producto = p_arr_productos[i];

        -- Validar la actualización
        IF NOT FOUND THEN
            RAISE EXCEPTION 'Error: No se pudo actualizar el stock del producto ID %', p_arr_productos[i];
        END IF;

        -- Acumular total
        v_total_productos_recibidos := v_total_productos_recibidos + p_arr_cantidades[i];

        -- Log
        RAISE NOTICE 'Stock actualizado para %: % + % = %',
            v_reg_producto.nom_producto,
            v_reg_producto.stock,
            p_arr_cantidades[i],
            v_reg_producto.stock + p_arr_cantidades[i];
    END LOOP;

    -- =====================================================
    -- LOG FINAL
    -- =====================================================
    RAISE NOTICE '=== RESUMEN DE RECEPCIÓN ===';
    RAISE NOTICE 'Proveedor: %', v_reg_proveedor.nom_proveedor;
    RAISE NOTICE 'Total productos recibidos: %', v_total_productos_recibidos;
    RAISE NOTICE 'Número de líneas procesadas: %', array_length(p_arr_productos, 1);
    RAISE NOTICE 'Fecha/Hora: %', CURRENT_TIMESTAMP;
    IF p_observaciones IS NOT NULL THEN
        RAISE NOTICE 'Observaciones: %', p_observaciones;
    END IF;

    RETURN v_id_recepcion;

EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'Error en recepción de proveedor: %', SQLERRM;
        RAISE NOTICE 'La transacción será revertida automáticamente';
        RETURN -1;
END;
$$
LANGUAGE plpgsql;
