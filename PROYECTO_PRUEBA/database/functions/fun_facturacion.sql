-- ===============================================================
-- Función para realizar la facturación de una orden
-- Esta función procesa una orden, valida los parámetros de entrada, y calcula los totales de la factura.
-- ===============================================================

CREATE OR REPLACE FUNCTION fun_facturar_rdwatch(
    p_id_usuario       tab_Usuarios.id_usuario%TYPE,           -- ID del usuario que realiza la facturación
    p_arr_productos    BIGINT[],                                -- Array de IDs de los productos a facturar
    p_arr_cantidades   SMALLINT[],                              -- Array de las cantidades de cada producto
    p_aplicar_descuento BOOLEAN DEFAULT FALSE,                  -- Indica si se aplica un descuento
    p_id_metodo_pago   tab_Metodos_Pago.id_metodo_pago%TYPE DEFAULT 1  -- ID del método de pago (por defecto 1)
)
RETURNS tab_Facturas.id_factura%TYPE  -- La función retorna el ID de la factura creada
LANGUAGE plpgsql
AS $$
DECLARE
    -- Declaración de variables
    v_id_orden      tab_Orden.id_orden%TYPE;       -- Variable para almacenar el ID de la orden
    v_id_factura    tab_Facturas.id_factura%TYPE;  -- Variable para almacenar el ID de la factura

    v_precio_unitario   tab_Productos.precio%TYPE;  -- Precio unitario de cada producto
    v_stock_actual      tab_Productos.stock%TYPE;   -- Stock disponible de cada producto

    v_subtotal_linea    NUMERIC;  -- Subtotal para cada producto (precio * cantidad)
    v_total_orden       NUMERIC := 0;  -- Total acumulado de la orden

    i INT;  -- Variable para el ciclo de iteración sobre los productos
BEGIN
    -- ----------------------------
    -- Validaciones de parámetros
    -- ----------------------------

    -- Validamos que los arrays de productos y cantidades tengan la misma longitud
    IF array_length(p_arr_productos, 1) IS DISTINCT FROM array_length(p_arr_cantidades, 1) THEN
        RAISE EXCEPTION 'Error: Los arrays de productos y cantidades deben tener la misma longitud';  -- Excepción si no coinciden
    END IF;

    -- Validamos que se hayan proporcionado productos, es decir, que el array de productos no esté vacío
    IF array_length(p_arr_productos, 1) IS NULL OR array_length(p_arr_productos, 1) < 1 THEN
        RAISE EXCEPTION 'Error: Debe incluir al menos un producto en la factura';  -- Excepción si el array está vacío
    END IF;

    -- Validamos que el usuario exista en la base de datos
    PERFORM 1 FROM tab_Usuarios WHERE id_usuario = p_id_usuario;  -- Verificamos que el ID del usuario esté en la tabla de usuarios
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error: El usuario con ID % no existe', p_id_usuario;  -- Excepción si no se encuentra el usuario
    END IF;

    -- Validamos que el método de pago exista en la base de datos
    PERFORM 1 FROM tab_Metodos_Pago WHERE id_metodo_pago = p_id_metodo_pago;  -- Verificamos que el ID del método de pago esté en la tabla de métodos de pago
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error: El método de pago con ID % no existe', p_id_metodo_pago;  -- Excepción si no se encuentra el método de pago
    END IF;

    -- -------------------------------------------------------
    -- Primer pase: validar productos/stock y acumular totales
    -- -------------------------------------------------------
    -- Iteramos sobre los productos recibidos para verificar el stock y calcular el subtotal
    FOR i IN 1 .. array_length(p_arr_productos, 1) LOOP
        -- Validamos que las cantidades de los productos sean mayores a cero
        IF p_arr_cantidades[i] <= 0 THEN
            RAISE EXCEPTION 'Error: La cantidad para el producto ID % debe ser mayor a 0', p_arr_productos[i];  -- Excepción si la cantidad es 0 o negativa
        END IF;

        -- Seleccionamos el precio y el stock del producto actual, bloqueando la fila para garantizar coherencia en el stock
        SELECT precio, stock
          INTO v_precio_unitario, v_stock_actual
          FROM tab_Productos
         WHERE id_producto = p_arr_productos[i]
         FOR UPDATE;  -- Bloquea la fila de producto para garantizar que el stock no cambie mientras procesamos la factura

        -- Validamos que el producto exista en la base de datos
        IF NOT FOUND THEN
            RAISE EXCEPTION 'Error: El producto con ID % no existe', p_arr_productos[i];  -- Excepción si no se encuentra el producto
        END IF;

        -- Validamos que haya suficiente stock disponible para el producto
        IF v_stock_actual < p_arr_cantidades[i] THEN
            RAISE EXCEPTION 'Error: Stock insuficiente para el producto %. Disponible: %, Solicitado: %',
                             p_arr_productos[i], v_stock_actual, p_arr_cantidades[i];  -- Excepción si no hay suficiente stock
        END IF;

        -- Calculamos el subtotal de la línea para este producto (precio unitario * cantidad)
        v_subtotal_linea := v_precio_unitario * p_arr_cantidades[i];

        -- Si se aplica descuento, este es el lugar para implementarlo, aunque por ahora está como un placeholder
        IF p_aplicar_descuento THEN
            v_subtotal_linea := v_subtotal_linea;  -- Aquí podrías aplicar el descuento si lo deseas
        END IF;

        -- Acumulamos el total de la orden sumando el subtotal de cada línea
        v_total_orden := v_total_orden + v_subtotal_linea;
    END LOOP;


    -- --------------------------------------------
-- Bloqueos para generación de IDs por MAX+1
-- --------------------------------------------
-- Realizamos bloqueos en las tablas involucradas para evitar que otros procesos accedan o modifiquen
-- las filas mientras estamos generando nuevas órdenes, facturas, pagos y detalles, asegurando la
-- coherencia de los datos.
LOCK TABLE tab_Orden           IN EXCLUSIVE MODE;       -- Bloqueo exclusivo en la tabla de órdenes
LOCK TABLE tab_Detalle_Orden   IN EXCLUSIVE MODE;       -- Bloqueo exclusivo en la tabla de detalles de la orden
LOCK TABLE tab_Facturas        IN EXCLUSIVE MODE;       -- Bloqueo exclusivo en la tabla de facturas
LOCK TABLE tab_Detalle_Factura IN EXCLUSIVE MODE;       -- Bloqueo exclusivo en la tabla de detalles de la factura
LOCK TABLE tab_Pagos           IN EXCLUSIVE MODE;       -- Bloqueo exclusivo en la tabla de pagos

-- 1) Cabecera de la ORDEN
-- Obtenemos el siguiente ID disponible para la nueva orden (MAX(id_orden) + 1)
SELECT COALESCE(MAX(id_orden), 0) + 1
  INTO v_id_orden
  FROM tab_Orden;

-- Insertamos la nueva orden en la tabla de órdenes con el ID generado y el ID del usuario
INSERT INTO tab_Orden (id_orden, id_usuario, fecha_orden, total_orden)
VALUES (v_id_orden, p_id_usuario, CURRENT_TIMESTAMP, 0);

-- 2) Detalle de la ORDEN + descuento de stock
-- Iteramos sobre los productos proporcionados para insertar los detalles de la orden y actualizar el stock
FOR i IN 1 .. array_length(p_arr_productos, 1) LOOP
    -- Obtenemos el precio y el stock actual del producto. Se utiliza FOR UPDATE para bloquear la fila mientras se modifica el stock
    SELECT precio, stock
      INTO v_precio_unitario, v_stock_actual
      FROM tab_Productos
     WHERE id_producto = p_arr_productos[i]
     FOR UPDATE;

    -- Calculamos el subtotal de la línea para este producto (precio * cantidad)
    v_subtotal_linea := v_precio_unitario * p_arr_cantidades[i];

    -- Si se aplica descuento, este es el lugar para implementarlo, aunque por ahora está como un placeholder
    IF p_aplicar_descuento THEN
        v_subtotal_linea := v_subtotal_linea;  -- Placeholder para aplicar descuento si es necesario
    END IF;

    -- Insertamos el detalle de la orden en la tabla de detalles de la orden
    -- Usamos el siguiente ID disponible para los detalles (MAX(id_detalle_orden) + 1)
    INSERT INTO tab_Detalle_Orden (
        id_detalle_orden, id_orden, id_producto, cantidad, precio_unitario
    )
    VALUES (
        (SELECT COALESCE(MAX(id_detalle_orden), 0) + 1 FROM tab_Detalle_Orden),
        v_id_orden,
        p_arr_productos[i],
        p_arr_cantidades[i],
        v_precio_unitario
    );

    -- Actualizamos el stock del producto, restando la cantidad solicitada
    UPDATE tab_Productos
       SET stock = stock - p_arr_cantidades[i]
     WHERE id_producto = p_arr_productos[i];
END LOOP;

-- Actualizamos el total de la orden con el valor calculado previamente
UPDATE tab_Orden
   SET total_orden = v_total_orden
 WHERE id_orden = v_id_orden;

-- 3) Cabecera de la FACTURA
-- Obtenemos el siguiente ID disponible para la nueva factura (MAX(id_factura) + 1)
SELECT COALESCE(MAX(id_factura), 0) + 1
  INTO v_id_factura
  FROM tab_Facturas;

-- Insertamos la nueva factura en la tabla de facturas
INSERT INTO tab_Facturas (
    id_factura, id_orden, id_usuario, fecha_factura, total_factura, estado_factura
)
VALUES (
    v_id_factura, v_id_orden, p_id_usuario, CURRENT_TIMESTAMP, v_total_orden, 'Emitida'
);

-- 4) Detalle de la FACTURA
-- Iteramos sobre los productos para insertar los detalles de la factura
FOR i IN 1 .. array_length(p_arr_productos, 1) LOOP
    -- Obtenemos el precio unitario del producto
    SELECT precio
      INTO v_precio_unitario
      FROM tab_Productos
     WHERE id_producto = p_arr_productos[i];

    -- Calculamos el subtotal de la línea para este producto
    v_subtotal_linea := v_precio_unitario * p_arr_cantidades[i];

    -- Si se aplica descuento, este es el lugar para implementarlo, aunque por ahora está como un placeholder
    IF p_aplicar_descuento THEN
        v_subtotal_linea := v_subtotal_linea;  -- Placeholder para aplicar descuento si es necesario
    END IF;

    -- Insertamos el detalle de la factura en la tabla de detalles de la factura
    -- Usamos el siguiente ID disponible para los detalles (MAX(id_detalle_factura) + 1)
    INSERT INTO tab_Detalle_Factura (
        id_detalle_factura, id_factura, id_producto, cantidad, precio_unitario, subtotal_linea
    )
    VALUES (
        (SELECT COALESCE(MAX(id_detalle_factura), 0) + 1 FROM tab_Detalle_Factura),
        v_id_factura,
        p_arr_productos[i],
        p_arr_cantidades[i],
        v_precio_unitario,
        v_subtotal_linea
    );
END LOOP;

-- 5) Pago
-- Registramos el pago en la tabla de pagos
INSERT INTO tab_Pagos (
    id_pago, id_orden, monto, id_metodo_pago, estado_pago, fecha_pago
)
VALUES (
    (SELECT COALESCE(MAX(id_pago), 0) + 1 FROM tab_Pagos),  -- Generamos el siguiente ID de pago
    v_id_orden,  -- Relacionamos el pago con la orden
    v_total_orden,  -- El monto del pago es el total de la orden
    p_id_metodo_pago,  -- Método de pago
    'completado',  -- El estado del pago
    CURRENT_TIMESTAMP  -- Fecha y hora del pago
);

-- Retornamos el ID de la factura generada
RETURN v_id_factura;

-- Manejo de excepciones
EXCEPTION
    WHEN OTHERS THEN
        -- Si ocurre un error (por ejemplo, violación de claves foráneas o restricciones),
        -- propagamos el error para que sea manejado en otro lugar.
        RAISE;
END;
$$;
