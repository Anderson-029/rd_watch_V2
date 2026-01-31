--función de insertar productos 
CREATE OR REPLACE FUNCTION public.fun_insert_productos(
    wid_producto bigint,
    wid_marca bigint,
    wnom_producto character varying,
    wdescripcion text,
    wprecio numeric,
    wid_categoria integer,
    wid_subcategoria integer,
    wstock smallint,
    wurl_imagen character varying DEFAULT NULL::character varying)
    RETURNS text
    LANGUAGE 'plpgsql'
AS $BODY$
DECLARE 
    wproducto tab_Productos.id_producto%TYPE;
    wmarca_existe BOOLEAN := FALSE;
    wcategoria_existe BOOLEAN := FALSE;
    wsubcategoria_existe BOOLEAN := FALSE;
BEGIN
    -- 1. Verificar si ya existe el ID de producto
    SELECT id_producto INTO wproducto FROM tab_Productos WHERE id_producto = wid_producto;
    IF FOUND THEN
        RETURN 'ERROR: El producto con ID ' || wid_producto || ' ya existe';
    END IF;

    -- 2. Verificar Marca (Validación Robustecida)
    -- Buscamos el ID. El estado debe ser TRUE (o NULL, que se asume activo si es el caso, pero aquí forzamos TRUE)
    SELECT EXISTS(
        SELECT 1 
        FROM tab_Marcas 
        WHERE id_marca = wid_marca 
        AND (estado_marca = TRUE OR estado_marca IS TRUE)
    ) INTO wmarca_existe;

    IF NOT wmarca_existe THEN
        -- Debug: Retornar mensaje específico si falla
        RETURN 'ERROR: La marca con ID ' || wid_marca || ' no existe o está inactiva (Verifique tab_Marcas)';
    END IF;

    -- 3. Verificar Categoría
    SELECT EXISTS(
        SELECT 1 FROM tab_Categorias 
        WHERE id_categoria = wid_categoria AND estado = TRUE
    ) INTO wcategoria_existe;
    
    IF NOT wcategoria_existe THEN
        RETURN 'ERROR: La categoría con ID ' || wid_categoria || ' no existe o está inactiva';
    END IF;

    -- 4. Verificar Subcategoría
    SELECT EXISTS(
        SELECT 1 FROM tab_Subcategorias 
        WHERE id_categoria = wid_categoria 
          AND id_subcategoria = wid_subcategoria 
          AND estado = TRUE
    ) INTO wsubcategoria_existe;
    
    IF NOT wsubcategoria_existe THEN
        RETURN 'ERROR: La subcategoría ' || wid_subcategoria || ' no existe o no pertenece a la categoría ' || wid_categoria;
    END IF;

    -- 5. Insertar
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
        RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$;

DROP FUNCTION fun_insert_productos(bigint,bigint,character varying,text,numeric,integer,integer,smallint,character varying);