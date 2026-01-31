-- ================================================================
-- FUNCIÓN: Procesar Checkout (Crear Orden desde Carrito)
-- ================================================================
CREATE OR REPLACE FUNCTION fun_checkout(
    p_id_usuario tab_Usuarios.id_usuario%TYPE,
    p_metodo_pago VARCHAR,
    p_direccion_envio VARCHAR
) RETURNS TEXT AS $$
DECLARE
    v_id_carrito tab_Carrito.id_carrito%TYPE;
    v_id_orden tab_Orden.id_orden%TYPE;
    v_total tab_Orden.total_orden%TYPE;
    v_costo_envio tab_Orden.total_orden%TYPE := 15000.00; -- Costo fijo envío
    v_stock_actual tab_Productos.stock%TYPE;
    v_item RECORD;
BEGIN
    -- 1. Obtener el carrito activo del usuario
    SELECT id_carrito INTO v_id_carrito
    FROM tab_Carrito
    WHERE id_usuario = p_id_usuario AND estado_carrito = 'activo';

    IF v_id_carrito IS NULL THEN
        RETURN 'ERROR: No hay carrito activo';
    END IF;

    -- 2. Validar Stock y Calcular Total real
    v_total := 0;
    
    FOR v_item IN 
        SELECT cd.id_producto, cd.cantidad, p.precio, p.stock
        FROM tab_Carrito_Detalle cd
        JOIN tab_Productos p ON cd.id_producto = p.id_producto
        WHERE cd.id_carrito = v_id_carrito
    LOOP
        -- Verificar si hay suficiente stock
        IF v_item.stock < v_item.cantidad THEN
            RETURN 'ERROR: Stock insuficiente para el producto ID ' || v_item.id_producto;
        END IF;
        
        -- Sumar al total
        v_total := v_total + (v_item.precio * v_item.cantidad);
    END LOOP;

    -- Si el carrito estaba vacío
    IF v_total = 0 THEN
        RETURN 'ERROR: El carrito está vacío';
    END IF;

    -- 3. Crear la Orden (Cabecera)
    -- Generar ID (simple max+1)
    SELECT COALESCE(MAX(id_orden), 0) + 1 INTO v_id_orden FROM tab_Orden;

    INSERT INTO tab_Orden (
        id_orden, id_usuario, fecha_orden, estado_orden, 
        concepto, total_orden
    ) VALUES (
        v_id_orden, 
        p_id_usuario, 
        CURRENT_TIMESTAMP, 
        'pendiente', -- Pendiente de confirmación de transferencia bancaria
        'Envío a: ' || p_direccion_envio || ' (' || p_metodo_pago || ')',
        v_total + v_costo_envio
    );

    -- 4. Mover detalles y Descontar Stock
    INSERT INTO tab_Detalle_Orden (
        id_detalle_orden, id_orden, id_producto, cantidad, precio_unitario
    )
    SELECT 
        (SELECT COALESCE(MAX(id_detalle_orden),0) FROM tab_Detalle_Orden) + ROW_NUMBER() OVER(),
        v_id_orden,
        cd.id_producto,
        cd.cantidad,
        p.precio
    FROM tab_Carrito_Detalle cd
    JOIN tab_Productos p ON cd.id_producto = p.id_producto
    WHERE cd.id_carrito = v_id_carrito;

    -- Actualizar Stock
    UPDATE tab_Productos p
    SET stock = p.stock - cd.cantidad
    FROM tab_Carrito_Detalle cd
    WHERE p.id_producto = cd.id_producto AND cd.id_carrito = v_id_carrito;

    -- 5. Cerrar el Carrito (Lo marcamos como 'convertido_a_orden')
    UPDATE tab_Carrito 
    SET estado_carrito = 'convertido_a_orden' 
    WHERE id_carrito = v_id_carrito;

    RETURN 'SUCCESS: Orden #' || v_id_orden || ' creada exitosamente';

EXCEPTION
    WHEN OTHERS THEN
        RETURN 'ERROR SQL: ' || SQLERRM;
END;
$$ LANGUAGE plpgsql;