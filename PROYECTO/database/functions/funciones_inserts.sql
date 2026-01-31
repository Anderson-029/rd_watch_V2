-- Función para insertar un nuevo usuario en tab_Usuarios
-- Retorna TRUE si la inserción fue exitosa, FALSE en caso de error (ej. id_usuario o correo_usuario duplicado)
CREATE OR REPLACE FUNCTION fun_insert_usuario(
    Pnom_usuario            tab_Usuarios.nom_usuario%TYPE,
    Pcorreo_usuario         tab_Usuarios.correo_usuario%TYPE,
    Pnum_telefono_usuario   tab_Usuarios.num_telefono_usuario%TYPE,
    Pdireccion_principal    tab_Usuarios.direccion_principal%TYPE,
    Pactivo                 tab_Usuarios.activo%TYPE DEFAULT TRUE -- Valor por defecto si no se especifica
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_usuario INT;
BEGIN
    -- Generar el próximo id_usuario
    SELECT COALESCE(MAX(id_usuario), 0) + 1 INTO v_new_id_usuario FROM tab_Usuarios;

    INSERT INTO tab_Usuarios (
        id_usuario, nom_usuario, correo_usuario, num_telefono_usuario,
        direccion_principal, fecha_registro, activo
        -- Las columnas de auditoría (usr_insert, fec_insert) serán llenadas por el trigger
    ) VALUES (
        v_new_id_usuario, Pnom_usuario, Pcorreo_usuario, Pnum_telefono_usuario,
        Pdireccion_principal, CURRENT_TIMESTAMP, Pactivo
    );

    RETURN TRUE; -- Inserción exitosa
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El correo electrónico % ya está registrado o el ID de usuario % ya existe.', Pcorreo_usuario, v_new_id_usuario;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar usuario: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;


CREATE OR REPLACE FUNCTION public.fun_insert_productos(
    wid_producto tab_Productos.id_producto%TYPE,         -- BIGINT
    wid_marca tab_Productos.id_marca%TYPE,               -- BIGINT
    wnom_producto tab_Productos.nom_producto%TYPE,       -- VARCHAR
    wdescripcion tab_Productos.descripcion%TYPE,         -- TEXT
    wprecio tab_Productos.precio%TYPE,                   -- NUMERIC
    wid_categoria tab_Productos.id_categoria%TYPE,       -- INTEGER
    wid_subcategoria tab_Productos.id_subcategoria%TYPE, -- INTEGER
    wstock tab_Productos.stock%TYPE,                     -- SMALLINT
    wurl_imagen tab_Productos.url_imagen%TYPE DEFAULT NULL -- VARCHAR
) RETURNS TEXT AS
$BODY$
DECLARE 
    wproducto tab_Productos.id_producto%TYPE;
    wmarca_existe BOOLEAN := FALSE;
    wcategoria_existe BOOLEAN := FALSE;
    wsubcategoria_existe BOOLEAN := FALSE;
BEGIN
    -- ID duplicado
    SELECT id_producto INTO wproducto FROM tab_Productos WHERE id_producto = wid_producto;
    IF FOUND THEN
        RETURN 'ERROR: El producto con ID ' || wid_producto || ' ya existe';
    END IF;

    -- Nombre duplicado
    SELECT id_producto INTO wproducto FROM tab_Productos
    WHERE LOWER(TRIM(nom_producto)) = LOWER(TRIM(wnom_producto));
    IF FOUND THEN
        RETURN 'ERROR: Ya existe un producto con el nombre: ' || wnom_producto;
    END IF;

    -- Obligatorios
    IF wid_producto IS NULL OR wid_producto <= 0 THEN
        RETURN 'ERROR: El ID del producto debe ser positivo';
    END IF;
    IF wnom_producto IS NULL OR TRIM(wnom_producto) = '' THEN
        RETURN 'ERROR: El nombre del producto no puede estar vacío';
    END IF;
    IF wdescripcion IS NULL OR TRIM(wdescripcion) = '' THEN
        RETURN 'ERROR: La descripción del producto no puede estar vacía';
    END IF;

    -- Rangos
    IF wprecio IS NULL OR wprecio <= 0 THEN
        RETURN 'ERROR: El precio debe ser mayor a cero';
    END IF;
    IF wstock IS NULL OR wstock < 0 THEN
        RETURN 'ERROR: El stock no puede ser negativo';
    END IF;

    -- FK Marca (OJO: tab_Marcas en plural)
    SELECT EXISTS(SELECT 1 FROM tab_Marcas WHERE id_marca = wid_marca AND estado_marca = TRUE)
    INTO wmarca_existe;
    IF NOT wmarca_existe THEN
        RETURN 'ERROR: La marca con ID ' || wid_marca || ' no existe o está inactiva';
    END IF;

    -- FK Categoría
    SELECT EXISTS(SELECT 1 FROM tab_Categorias WHERE id_categoria = wid_categoria AND estado = TRUE)
    INTO wcategoria_existe;
    IF NOT wcategoria_existe THEN
        RETURN 'ERROR: La categoría con ID ' || wid_categoria || ' no existe o está inactiva';
    END IF;

    -- FK Subcategoría
    SELECT EXISTS(
        SELECT 1 FROM tab_Subcategorias 
        WHERE id_categoria = wid_categoria 
          AND id_subcategoria = wid_subcategoria 
          AND estado = TRUE
    ) INTO wsubcategoria_existe;
    IF NOT wsubcategoria_existe THEN
        RETURN 'ERROR: La subcategoría ' || wid_subcategoria || ' no existe, está inactiva o no pertenece a la categoría ' || wid_categoria;
    END IF;

    -- URL imagen
    IF wurl_imagen IS NOT NULL AND TRIM(wurl_imagen) <> '' THEN
        IF LENGTH(wurl_imagen) > 255 THEN
            RETURN 'ERROR: La URL de la imagen no puede superar 255 caracteres';
        END IF;
        IF wurl_imagen NOT LIKE 'http://%' AND wurl_imagen NOT LIKE 'https://%' THEN
            RETURN 'ERROR: La URL de la imagen debe comenzar con http:// o https://';
        END IF;
    END IF;

    -- Inserción
    BEGIN
        INSERT INTO tab_Productos (
            id_producto, id_marca, nom_producto, descripcion, precio,
            id_categoria, id_subcategoria, stock, url_imagen, estado
        ) VALUES (
            wid_producto, wid_marca, TRIM(wnom_producto), TRIM(wdescripcion), wprecio,
            wid_categoria, wid_subcategoria, wstock,
            CASE WHEN wurl_imagen IS NULL OR TRIM(wurl_imagen) = '' THEN NULL ELSE TRIM(wurl_imagen) END,
            TRUE
        );
        RETURN 'SUCCESS: Producto insertado correctamente con ID ' || wid_producto;
    EXCEPTION
        WHEN OTHERS THEN
            RETURN 'ERROR: ' || SQLERRM;
    END;
END;
$BODY$
LANGUAGE plpgsql;


-- Función para insertar una nueva orden en tab_Orden
-- Nota: Esta función solo inserta la cabecera de la orden. Los detalles (productos/servicios)
-- se manejarían con funciones separadas o una función más compleja que reciba arrays de ítems.
CREATE OR REPLACE FUNCTION fun_insert_orden(
    Pid_usuario     tab_Orden.id_usuario%TYPE,
    Pestado_orden   tab_Orden.estado_orden%TYPE DEFAULT 'pendiente', -- Estado inicial por defecto
    Pconcepto       tab_Orden.concepto%TYPE DEFAULT NULL, -- CORRECCIÓN: Ahora tiene un valor por defecto
    Ptotal_orden    tab_Orden.total_orden%TYPE DEFAULT 0.00 -- Total inicial, podría ser calculado después
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_orden INT;
BEGIN
    -- Generar el próximo id_orden
    SELECT COALESCE(MAX(id_orden), 0) + 1 INTO v_new_id_orden FROM tab_Orden;

    INSERT INTO tab_Orden (
        id_orden, id_usuario, fecha_orden, estado_orden, concepto, total_orden
    ) VALUES (
        v_new_id_orden, Pid_usuario, CURRENT_TIMESTAMP, Pestado_orden, Pconcepto, Ptotal_orden
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El ID de orden % ya existe.', v_new_id_orden;
        RETURN FALSE;
    WHEN foreign_key_violation THEN
        RAISE WARNING 'Error: El usuario especificado (ID: %) no existe. Detalles: %', Pid_usuario, SQLERRM;
        RETURN FALSE;
    WHEN check_violation THEN
        RAISE WARNING 'Error: El estado de la orden o el total no cumplen con las restricciones. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar orden: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;


-- Función para insertar un nuevo detalle de orden en tab_Detalle_Orden
-- Esta función asume que la orden y el producto ya existen.
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
    -- Generar el próximo id_detalle_orden
    SELECT COALESCE(MAX(id_detalle_orden), 0) + 1 INTO v_new_id_detalle_orden FROM tab_Detalle_Orden;

    INSERT INTO tab_Detalle_Orden (
        id_detalle_orden, id_orden, id_producto, cantidad, precio_unitario, id_promocion_aplicada
    ) VALUES (
        v_new_id_detalle_orden, Pid_orden, Pid_producto, Pcantidad, Pprecio_unitario, Pid_promocion_aplicada
    );

    -- Opcional: Aquí podrías añadir lógica para decrementar el stock del producto en tab_Productos
    -- UPDATE tab_Productos SET stock = stock - Pcantidad WHERE id_producto = Pid_producto;
    -- Considera si esta lógica debería estar aquí o en un trigger/función de orden más compleja.

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


-- Función para insertar un nuevo servicio de orden en tab_Orden_Servicios
-- Esta función asume que la orden y el servicio ya existen.
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
    -- Generar el próximo id_orden_servicio
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


-- Función para insertar una nueva factura en tab_Facturas
-- Nota: Esta función solo inserta la cabecera de la factura. Los detalles
-- se manejarían con una función separada o una función más compleja.
CREATE OR REPLACE FUNCTION fun_insert_factura(
    Pid_orden       tab_Facturas.id_orden%TYPE,
    Pid_usuario     tab_Facturas.id_usuario%TYPE,
    Ptotal_factura  tab_Facturas.total_factura%TYPE,
    Pestado_factura tab_Facturas.estado_factura%TYPE DEFAULT 'Emitida' -- Estado inicial por defecto
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_factura INT;
BEGIN
    -- Generar el próximo id_factura
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


-- Función para insertar un nuevo detalle de factura en tab_Detalle_Factura
-- Esta función asume que la factura y el producto ya existen.
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
    -- Generar el próximo id_detalle_factura
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


-- Función para insertar un nuevo pago en tab_Pagos
-- Retorna TRUE si la inserción fue exitosa, FALSE en caso de error.
CREATE OR REPLACE FUNCTION fun_insert_pago(
    Pid_orden           tab_Pagos.id_orden%TYPE,
    Pmonto              tab_Pagos.monto%TYPE,
    Pid_metodo_pago     tab_Pagos.id_metodo_pago%TYPE,
    Pestado_pago        tab_Pagos.estado_pago%TYPE DEFAULT 'pendiente' -- Estado inicial por defecto
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_pago INT;
BEGIN
    -- Generar el próximo id_pago
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


-- Función para insertar una nueva recepción de proveedor en tab_Recepciones_Proveedor
-- Esta función también actualiza el stock del producto en tab_Productos.
CREATE OR REPLACE FUNCTION fun_insert_recepcion_proveedor(
    Pid_producto        tab_Recepciones_Proveedor.id_producto%TYPE,
    Pid_proveedor       tab_Recepciones_Proveedor.id_proveedor%TYPE,
    Pcantidad_recibida  tab_Recepciones_Proveedor.cantidad_recibida%TYPE
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_recepcion INT;
BEGIN
    -- Generar el próximo id_recepcion
    SELECT COALESCE(MAX(id_recepcion), 0) + 1 INTO v_new_id_recepcion FROM tab_Recepciones_Proveedor;

    -- Iniciar una transacción para asegurar atomicidad
    BEGIN
        INSERT INTO tab_Recepciones_Proveedor (
            id_recepcion, id_producto, id_proveedor, cantidad_recibida, fecha_recepcion
        ) VALUES (
            v_new_id_recepcion, Pid_producto, Pid_proveedor, Pcantidad_recibida, CURRENT_TIMESTAMP
        );

        -- Actualizar el stock del producto
        UPDATE tab_Productos
        SET stock = stock + Pcantidad_recibida
        WHERE id_producto = Pid_producto;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'El producto con ID % no fue encontrado al intentar actualizar el stock.', Pid_producto;
        END IF;

        RETURN TRUE;
    EXCEPTION
        WHEN unique_violation THEN
            RAISE WARNING 'Error: El ID de recepción % ya existe.', v_new_id_recepcion;
            RETURN FALSE;
        WHEN foreign_key_violation THEN
            RAISE WARNING 'Error: El producto o proveedor especificado no existe. Detalles: %', SQLERRM;
            RETURN FALSE;
        WHEN check_violation THEN
            RAISE WARNING 'Error: La cantidad recibida no cumple con las restricciones. Detalles: %', SQLERRM;
            RETURN FALSE;
        WHEN OTHERS THEN
            RAISE WARNING 'Error inesperado al insertar recepción o actualizar stock: %', SQLERRM;
            RETURN FALSE;
    END;
END;
$$
LANGUAGE PLPGSQL;


-- Función para insertar un nuevo empleado en tab_Empleados
-- Retorna TRUE si la inserción fue exitosa, FALSE en caso de error.
CREATE OR REPLACE FUNCTION fun_insert_empleado(
    Pnum_documento          tab_Empleados.num_documento%TYPE,
    Pnom_empleado           tab_Empleados.nom_empleado%TYPE,
    Papellido_empleado      tab_Empleados.apellido_empleado%TYPE,
    Pcorreo                 tab_Empleados.correo%TYPE,
    Ptelefono               tab_Empleados.telefono%TYPE,
    Ppuesto                 tab_Empleados.puesto%TYPE,
    Pfecha_contratacion     tab_Empleados.fecha_contratacion%TYPE
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_empleado INT;
BEGIN
    -- Generar el próximo id_empleado
    SELECT COALESCE(MAX(id_empleado), 0) + 1 INTO v_new_id_empleado FROM tab_Empleados;

    INSERT INTO tab_Empleados (
        id_empleado, num_documento, nom_empleado, apellido_empleado,
        correo, telefono, puesto, fecha_contratacion
    ) VALUES (
        v_new_id_empleado, Pnum_documento, Pnom_empleado, Papellido_empleado,
        Pcorreo, Ptelefono, Ppuesto, Pfecha_contratacion
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El número de documento % o el correo % ya están registrados o el ID de empleado % ya existe.', Pnum_documento, Pcorreo, v_new_id_empleado;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar empleado: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;


-- Función para insertar una nueva promoción en tab_Promociones
-- Retorna TRUE si la inserción fue exitosa, FALSE en caso de error.
CREATE OR REPLACE FUNCTION fun_insert_promocion(
    Pdescripcion    tab_Promociones.descripcion%TYPE,
    Pdescuento      tab_Promociones.descuento%TYPE,
    Pfecha_inicio   tab_Promociones.fecha_inicio%TYPE,
    Pfecha_fin      tab_Promociones.fecha_fin%TYPE
) RETURNS BOOLEAN AS
$$
DECLARE
    v_new_id_promocion INT;
BEGIN
    -- Generar el próximo id_promocion
    SELECT COALESCE(MAX(id_promocion), 0) + 1 INTO v_new_id_promocion FROM tab_Promociones;

    INSERT INTO tab_Promociones (
        id_promocion, descripcion, descuento, fecha_inicio, fecha_fin
    ) VALUES (
        v_new_id_promocion, Pdescripcion, Pdescuento, Pfecha_inicio, Pfecha_fin
    );

    RETURN TRUE;
EXCEPTION
    WHEN unique_violation THEN
        RAISE WARNING 'Error: El ID de promoción % ya existe.', v_new_id_promocion;
        RETURN FALSE;
    WHEN check_violation THEN
        RAISE WARNING 'Error: El descuento o las fechas no cumplen con las restricciones. Detalles: %', SQLERRM;
        RETURN FALSE;
    WHEN OTHERS THEN
        RAISE WARNING 'Error inesperado al insertar promoción: %', SQLERRM;
        RETURN FALSE;
END;
$$
LANGUAGE PLPGSQL;