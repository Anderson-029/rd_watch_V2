-- ===========================================
-- FUNCIÓN: Historial de Movimientos de un Producto
-- ===========================================
CREATE OR REPLACE FUNCTION fun_historial_producto(
    wid_producto tab_Productos.id_producto%TYPE
) RETURNS TABLE (
    fecha_movimiento TIMESTAMP,
    tipo_movimiento  TEXT,
    cantidad         BIGINT,
    referencia       TEXT
) AS
$$
BEGIN
    -- =====================================
    -- VALIDAR QUE EL PRODUCTO EXISTA
    -- =====================================
    IF NOT EXISTS (SELECT 1 FROM tab_Productos WHERE id_producto = wid_producto) THEN
        RAISE EXCEPTION 'Error: El producto con ID % no existe', wid_producto;
    END IF;

    -- =====================================
    -- RETORNAR MOVIMIENTOS
    -- =====================================
    RETURN QUERY
    (
        -- Entradas desde recepciones de proveedor
        SELECT 
            r.fecha_recepcion AS fecha_movimiento,
            'Recepción'::TEXT AS tipo_movimiento,
            r.cantidad_recibida AS cantidad,
            ('Proveedor: ' || p.nom_proveedor)::TEXT AS referencia
        FROM tab_Recepciones_Proveedor r
        JOIN tab_Proveedor p ON p.id_proveedor = r.id_proveedor
        WHERE r.id_producto = wid_producto

        UNION ALL

        -- Salidas desde facturas
        SELECT 
            f.fecha_emision AS fecha_movimiento,
            'Venta'::TEXT AS tipo_movimiento,
            -df.cantidad AS cantidad,  -- negativo para salidas
            ('Factura: ' || f.id_factura || ' - Cliente: ' || u.nom_usuario)::TEXT AS referencia
        FROM tab_Detalle_Factura df
        JOIN tab_Facturas f ON f.id_factura = df.id_factura
        JOIN tab_Usuarios u ON u.id_usuario = f.id_usuario
        WHERE df.id_producto = wid_producto
    )
    ORDER BY fecha_movimiento;

END;
$$
LANGUAGE plpgsql;
