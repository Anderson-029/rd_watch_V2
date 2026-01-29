-- ================================================================
-- FUNCIÓN: Generar Factura desde Orden
-- ================================================================
CREATE OR REPLACE FUNCTION fun_generar_factura(
    p_id_orden tab_Orden.id_orden%TYPE
) RETURNS TEXT AS $$
DECLARE
    v_id_factura tab_Facturas.id_factura%TYPE;
    v_id_usuario tab_Usuarios.id_usuario%TYPE;
    v_total_orden tab_Orden.total_orden%TYPE;
    v_factura_existe BOOLEAN;
BEGIN
    -- 1. Verificar si la orden existe y obtener datos
    SELECT id_usuario, total_orden INTO v_id_usuario, v_total_orden
    FROM tab_Orden
    WHERE id_orden = p_id_orden;

    IF v_id_usuario IS NULL THEN
        RETURN 'ERROR: Orden no encontrada';
    END IF;

    -- 2. Verificar si ya existe una factura para esta orden
    SELECT EXISTS(SELECT 1 FROM tab_Facturas WHERE id_orden = p_id_orden) INTO v_factura_existe;

    IF v_factura_existe THEN
        -- Si ya existe, retornar el ID existente
        SELECT id_factura INTO v_id_factura FROM tab_Facturas WHERE id_orden = p_id_orden;
        RETURN 'SUCCESS: Factura existente #' || v_id_factura;
    END IF;

    -- 3. Generar nuevo ID para la factura
    SELECT COALESCE(MAX(id_factura), 0) + 1 INTO v_id_factura FROM tab_Facturas;

    -- 4. Crear la factura (cabecera)
    INSERT INTO tab_Facturas (
        id_factura, id_orden, id_usuario, fecha_emision, 
        total_factura, estado_factura
    ) VALUES (
        v_id_factura,
        p_id_orden,
        v_id_usuario,
        CURRENT_TIMESTAMP,
        v_total_orden,
        'Emitida'
    );

    -- 5. Insertar los detalles de la factura desde la orden
    INSERT INTO tab_Detalle_Factura (
        id_detalle_factura, id_factura, id_producto, 
        cantidad, precio_unitario, subtotal_linea
    )
    SELECT 
        (SELECT COALESCE(MAX(id_detalle_factura), 0) FROM tab_Detalle_Factura) + ROW_NUMBER() OVER(),
        v_id_factura,
        det_orden.id_producto,
        det_orden.cantidad,
        det_orden.precio_unitario,
        det_orden.cantidad * det_orden.precio_unitario
    FROM tab_Detalle_Orden det_orden
    WHERE det_orden.id_orden = p_id_orden;

    RETURN 'SUCCESS: Factura #' || v_id_factura || ' generada';

EXCEPTION
    WHEN OTHERS THEN
        RETURN 'ERROR SQL: ' || SQLERRM;
END;
$$ LANGUAGE plpgsql;
