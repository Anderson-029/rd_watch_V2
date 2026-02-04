-- ==========================================
-- FUNCIONES TRANSACCIONALES Y DE NEGOCIO
-- Funciones complejas que no son simples CRUD
-- ==========================================

-- Función para insertar un nuevo detalle de orden en tab_Detalle_Orden
CREATE OR REPLACE FUNCTION fun_insert_detalle_orden(
    Pid_orden               tab_Detalle_Orden.id_orden%TYPE,
    Pid_producto            tab_Detalle_Orden.id_producto%TYPE,
    Pcantidad               tab_Detalle_Orden.cantidad%TYPE,
    Pprecio_unitario        tab_Detalle_Orden.precio_unitario%TYPE,
    Pid_promocion_aplicada  tab_Detalle_Orden.id_promocion_aplicada%TYPE DEFAULT NULL
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_detalle_orden INT;
BEGIN
    SELECT COALESCE(MAX(id_detalle_orden), 0) + 1 INTO v_new_id_detalle_orden FROM tab_Detalle_Orden;

    INSERT INTO tab_Detalle_Orden (
        id_detalle_orden, id_orden, id_producto, cantidad, precio_unitario, id_promocion_aplicada
    ) VALUES (
        v_new_id_detalle_orden, Pid_orden, Pid_producto, Pcantidad, Pprecio_unitario, Pid_promocion_aplicada
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El ID de detalle de orden % ya existe o el producto % ya está en la orden %.', v_new_id_detalle_orden, Pid_producto, Pid_orden;
        RETURN FALSE;
    WHEN foreign_key_violation THEN
        RAISE WARNING 'Error: La orden, producto o promoción especificada no existe. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN check_violation THEN
        RAISE WARNING 'Error: La cantidad o precio unitario no cumplen con las restricciones. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar detalle de orden: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;

-- Función para insertar un nuevo servicio de orden
CREATE OR REPLACE FUNCTION fun_insert_orden_servicio(
    Pid_orden                   tab_Orden_Servicios.id_orden%TYPE,
    Pid_servicio                tab_Orden_Servicios.id_servicio%TYPE,
    Pcantidad                   tab_Orden_Servicios.cantidad%TYPE,
    Pprecio_servicio_aplicado   tab_Orden_Servicios.precio_servicio_aplicado%TYPE
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_orden_servicio INT;
BEGIN
    SELECT COALESCE(MAX(id_orden_servicio), 0) + 1 INTO v_new_id_orden_servicio FROM tab_Orden_Servicios;

    INSERT INTO tab_Orden_Servicios (
        id_orden_servicio, id_orden, id_servicio, cantidad, precio_servicio_aplicado
    ) VALUES (
        v_new_id_orden_servicio, Pid_orden, Pid_servicio, Pcantidad, Pprecio_servicio_aplicado
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El ID de servicio de orden % ya existe o el servicio % ya está en la orden %.', v_new_id_orden_servicio, Pid_servicio, Pid_orden;
        RETURN FALSE;
    WHEN foreign_key_violation THEN
        RAISE WARNING 'Error: La orden o el servicio especificado no existe. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN check_violation THEN
        RAISE WARNING 'Error: La cantidad o el precio del servicio no cumplen con las restricciones. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar servicio de orden: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;

-- Función para insertar una nueva factura
CREATE OR REPLACE FUNCTION fun_insert_factura(
    Pid_orden       tab_Facturas.id_orden%TYPE,
    Pid_usuario     tab_Facturas.id_usuario%TYPE,
    Ptotal_factura  tab_Facturas.total_factura%TYPE,
    Pestado_factura tab_Facturas.estado_factura%TYPE DEFAULT 'Emitida'
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_factura INT;
BEGIN
    SELECT COALESCE(MAX(id_factura), 0) + 1 INTO v_new_id_factura FROM tab_Facturas;

    INSERT INTO tab_Facturas (
        id_factura, id_orden, id_usuario, fecha_emision, total_factura, estado_factura
    ) VALUES (
        v_new_id_factura, Pid_orden, Pid_usuario, CURRENT_TIMESTAMP, Ptotal_factura, Pestado_factura
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El ID de factura % ya existe o la orden % ya tiene una factura asociada.', v_new_id_factura, Pid_orden;
        RETURN FALSE;
    WHEN foreign_key_violation THEN
        RAISE WARNING 'Error: La orden o el usuario especificado no existe. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar factura: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;

-- Función para insertar un nuevo detalle de factura
CREATE OR REPLACE FUNCTION fun_insert_detalle_factura(
    Pid_factura         tab_Detalle_Factura.id_factura%TYPE,
    Pid_producto        tab_Detalle_Factura.id_producto%TYPE,
    Pcantidad           tab_Detalle_Factura.cantidad%TYPE,
    Pprecio_unitario    tab_Detalle_Factura.precio_unitario%TYPE,
    Psubtotal_linea     tab_Detalle_Factura.subtotal_linea%TYPE
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_detalle_factura INT;
BEGIN
    SELECT COALESCE(MAX(id_detalle_factura), 0) + 1 INTO v_new_id_detalle_factura FROM tab_Detalle_Factura;

    INSERT INTO tab_Detalle_Factura (
        id_detalle_factura, id_factura, id_producto, cantidad, precio_unitario, subtotal_linea
    ) VALUES (
        v_new_id_detalle_factura, Pid_factura, Pid_producto, Pcantidad, Pprecio_unitario, Psubtotal_linea
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El ID de detalle de factura % ya existe o el producto % ya está en la factura %.', v_new_id_detalle_factura, Pid_producto, Pid_factura;
        RETURN FALSE;
    WHEN foreign_key_violation THEN
        RAISE WARNING 'Error: La factura o el producto especificado no existe. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN check_violation THEN
        RAISE WARNING 'Error: La cantidad no cumple con las restricciones. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar detalle de factura: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;

-- Función para insertar un nuevo pago
CREATE OR REPLACE FUNCTION fun_insert_pago(
    Pid_orden           tab_Pagos.id_orden%TYPE,
    Pmonto              tab_Pagos.monto%TYPE,
    Pid_metodo_pago     tab_Pagos.id_metodo_pago%TYPE,
    Pestado_pago        tab_Pagos.estado_pago%TYPE DEFAULT 'pendiente'
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_pago INT;
BEGIN
    SELECT COALESCE(MAX(id_pago), 0) + 1 INTO v_new_id_pago FROM tab_Pagos;

    INSERT INTO tab_Pagos (
        id_pago, id_orden, monto, id_metodo_pago, estado_pago, fecha_pago
    ) VALUES (
        v_new_id_pago, Pid_orden, Pmonto, Pid_metodo_pago, Pestado_pago, CURRENT_TIMESTAMP
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El ID de pago % ya existe o la orden % ya tiene un pago asociado.', v_new_id_pago, Pid_orden;
        RETURN FALSE;
    WHEN foreign_key_violation THEN
        RAISE WARNING 'Error: La orden o el método de pago especificado no existe. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN check_violation THEN
        RAISE WARNING 'Error: El monto o el estado del pago no cumplen con las restricciones. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar pago: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;

-- Función para actualizar factura
CREATE OR REPLACE FUNCTION fun_update_factura(
    Kid_factura     tab_Facturas.id_factura%TYPE,
    Kid_orden       tab_Facturas.id_orden%TYPE,
    Kid_usuario     tab_Facturas.id_usuario%TYPE,
    Ktotal_factura  tab_Facturas.total_factura%TYPE,
    Kestado_factura tab_Facturas.estado_factura%TYPE
) RETURNS BOOLEAN AS
$$
BEGIN
    UPDATE tab_Facturas SET
        id_orden = Kid_orden,
        id_usuario = Kid_usuario,
        total_factura = Ktotal_factura,
        estado_factura = Kestado_factura
    WHERE id_factura = Kid_factura;

    IF FOUND THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END;
$$
LANGUAGE PLPGSQL;

-- Función para actualizar un pago
CREATE OR REPLACE FUNCTION fun_update_pago(
    Kid_pago            tab_Pagos.id_pago%TYPE,
    Kid_orden           tab_Pagos.id_orden%TYPE,
    Kmonto              tab_Pagos.monto%TYPE,
    Kid_metodo_pago     tab_Pagos.id_metodo_pago%TYPE,
    Kestado_pago        tab_Pagos.estado_pago%TYPE
) RETURNS BOOLEAN AS
$$
BEGIN
    UPDATE tab_Pagos SET
        id_orden = Kid_orden,
        monto = Kmonto,
        id_metodo_pago = Kid_metodo_pago,
        estado_pago = Kestado_pago
    WHERE id_pago = Kid_pago;

    IF FOUND THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END;
$$
LANGUAGE PLPGSQL;
