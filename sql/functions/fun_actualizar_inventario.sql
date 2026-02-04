-- ===============================================================
-- Función para actualizar el inventario de un producto
-- La función ajusta el stock de un producto, permitiendo incrementarlo o decrementarlo.
-- Si el ajuste es negativo, se valida que haya suficiente stock.
-- ===============================================================

CREATE OR REPLACE FUNCTION fun_actualizar_inventario_rdwatch(
    p_id_producto  tab_Productos.id_producto%TYPE,   -- ID del producto que se va a actualizar
    p_delta_stock  tab_Productos.stock%TYPE   -- Ajuste al stock (puede ser negativo o positivo)
)
RETURNS tab_Productos.stock%TYPE  -- La función retorna el nuevo stock después de la actualización
LANGUAGE plpgsql
AS $$
DECLARE
    v_stock_actual tab_Productos.stock%TYPE;  -- Variable para almacenar el stock actual del producto
    v_stock_nuevo  tab_Productos.stock%TYPE;  -- Variable para almacenar el nuevo stock después del ajuste
BEGIN
    -- Bloqueamos la fila del producto para evitar condiciones de carrera
    -- El FOR UPDATE asegura que la fila del producto se bloquee mientras se realiza el ajuste de stock
    SELECT stock
      INTO v_stock_actual
      FROM tab_Productos
     WHERE id_producto = p_id_producto
     FOR UPDATE;  -- Bloqueo exclusivo de la fila del producto para evitar cambios concurrentes

    -- Verificamos si el producto existe
    IF NOT FOUND THEN
        -- Si no se encuentra el producto, lanzamos una excepción
        RAISE EXCEPTION 'Error: El producto con ID % no existe', p_id_producto;
    END IF;

    -- Si el ajuste al stock es cero, no realizamos ningún cambio
    IF p_delta_stock = 0 THEN
        -- Si no hay cambio, simplemente retornamos el stock actual
        RETURN v_stock_actual;
    END IF;

    -- Calculamos el nuevo stock sumando el ajuste al stock actual
    v_stock_nuevo := v_stock_actual + p_delta_stock;

    -- Validamos que el stock no sea negativo
    -- Si el stock resultante es menor que cero, lanzamos una excepción
    IF v_stock_nuevo < 0 THEN
        RAISE EXCEPTION
            'Error: Stock insuficiente para el producto %. Disponible: %, Ajuste solicitado: %',
            p_id_producto, v_stock_actual, p_delta_stock;
    END IF;

    -- Actualizamos el stock en la tabla de productos
    UPDATE tab_Productos
       SET stock = v_stock_nuevo
     WHERE id_producto = p_id_producto;

    -- Los triggers de auditoría BEFORE UPDATE (fun_audit_rdwatch) completan usr_update y fec_update.
    -- Esto asegura que los campos de auditoría sean actualizados automáticamente durante la actualización.

    -- Retornamos el nuevo stock después de la actualización
    RETURN v_stock_nuevo;
END;
$$;
