-- Función para actualizar un usuario en tab_Usuarios
CREATE OR REPLACE FUNCTION fun_update_usuario(
    Kid_usuario             tab_Usuarios.id_usuario%TYPE,
    Knom_usuario            tab_Usuarios.nom_usuario%TYPE,
    Kcorreo_usuario         tab_Usuarios.correo_usuario%TYPE,
    Knum_telefono_usuario   tab_Usuarios.num_telefono_usuario%TYPE,
    Kdireccion_principal    tab_Usuarios.direccion_principal%TYPE,
    Kactivo                 tab_Usuarios.activo%TYPE
) RETURNS BOOLEAN AS
$$
BEGIN
    UPDATE tab_Usuarios SET
        nom_usuario = Knom_usuario,
        correo_usuario = Kcorreo_usuario,
        num_telefono_usuario = Knum_telefono_usuario,
        direccion_principal = Kdireccion_principal,
        activo = Kactivo
    WHERE id_usuario = Kid_usuario;

    -- Verifica si alguna fila fue afectada por la operación UPDATE
    IF FOUND THEN
        RETURN TRUE; -- El usuario fue encontrado y actualizado
    ELSE
        RETURN FALSE; -- El usuario no fue encontrado, no se realizó ninguna actualización
    END IF;
END;
$$
LANGUAGE PLPGSQL;


-- Función para actualizar un producto en tab_Productos
CREATE OR REPLACE FUNCTION fun_update_producto(
    Kid_producto        tab_Productos.id_producto%TYPE,
    Kid_marca           tab_Productos.id_marca%TYPE,
    Knom_producto       tab_Productos.nom_producto%TYPE,
    Kdescripcion        tab_Productos.descripcion%TYPE,
    Kprecio             tab_Productos.precio%TYPE,
    Kid_categoria       tab_Productos.id_categoria%TYPE,
    Kid_subcategoria    tab_Productos.id_subcategoria%TYPE,
    Kstock              tab_Productos.stock%TYPE,
    Kurl_imagen         tab_Productos.url_imagen%TYPE,
    Kestado             tab_Productos.estado%TYPE
) RETURNS BOOLEAN AS
$$
BEGIN
    UPDATE tab_Productos SET
        id_marca = Kid_marca,
        nom_producto = Knom_producto,
        descripcion = Kdescripcion,
        precio = Kprecio,
        id_categoria = Kid_categoria,
        id_subcategoria = Kid_subcategoria,
        stock = Kstock,
        url_imagen = Kurl_imagen,
        estado = Kestado
    WHERE id_producto = Kid_producto;

    IF FOUND THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END;
$$
LANGUAGE PLPGSQL;


-- Función para actualizar una orden en tab_Orden
CREATE OR REPLACE FUNCTION fun_update_orden(
    Kid_orden       tab_Orden.id_orden%TYPE,
    Kid_usuario     tab_Orden.id_usuario%TYPE,
    Kestado_orden   tab_Orden.estado_orden%TYPE,
    Kconcepto       tab_Orden.concepto%TYPE,
    Ktotal_orden    tab_Orden.total_orden%TYPE
) RETURNS BOOLEAN AS
$$
BEGIN
    UPDATE tab_Orden SET
        id_usuario = Kid_usuario,
        estado_orden = Kestado_orden,
        concepto = Kconcepto,
        total_orden = Ktotal_orden
    WHERE id_orden = Kid_orden;

    IF FOUND THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END;
$$
LANGUAGE PLPGSQL;


-- Función para actualizar una factura en tab_Facturas
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


-- Función para actualizar un pago en tab_Pagos
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


-- Función para actualizar una promoción en tab_Promociones
CREATE OR REPLACE FUNCTION fun_update_promocion(
    Kid_promocion   tab_Promociones.id_promocion%TYPE,
    Kdescripcion    tab_Promociones.descripcion%TYPE,
    Kdescuento      tab_Promociones.descuento%TYPE,
    Kfecha_inicio   tab_Promociones.fecha_inicio%TYPE,
    Kfecha_fin      tab_Promociones.fecha_fin%TYPE
) RETURNS BOOLEAN AS
$$
BEGIN
    UPDATE tab_Promociones SET
        descripcion = Kdescripcion,
        descuento = Kdescuento,
        fecha_inicio = Kfecha_inicio,
        fecha_fin = Kfecha_fin
    WHERE id_promocion = Kid_promocion;

    IF FOUND THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END;
$$
LANGUAGE PLPGSQL;


-- Función para actualizar un empleado en tab_Empleados
CREATE OR REPLACE FUNCTION fun_update_empleado(
    Kid_empleado            tab_Empleados.id_empleado%TYPE,
    Knum_documento          tab_Empleados.num_documento%TYPE,
    Knom_empleado           tab_Empleados.nom_empleado%TYPE,
    Kapellido_empleado      tab_Empleados.apellido_empleado%TYPE,
    Kcorreo                 tab_Empleados.correo%TYPE,
    Ktelefono               tab_Empleados.telefono%TYPE,
    Kpuesto                 tab_Empleados.puesto%TYPE,
    Kfecha_contratacion     tab_Empleados.fecha_contratacion%TYPE
) RETURNS BOOLEAN AS
$$
BEGIN
    UPDATE tab_Empleados SET
        num_documento = Knum_documento,
        nom_empleado = Knom_empleado,
        apellido_empleado = Kapellido_empleado,
        correo = Kcorreo,
        telefono = Ktelefono,
        puesto = Kpuesto,
        fecha_contratacion = Kfecha_contratacion
    WHERE id_empleado = Kid_empleado;

    IF FOUND THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END;
$$
LANGUAGE PLPGSQL;
