-- Function to insert a subcategory
CREATE OR REPLACE FUNCTION fun_insert_subcategorias(
    wid_categoria integer,
    wid_subcategoria integer,
    wnom_subcategoria character varying
) RETURNS TEXT AS
$BODY$
DECLARE
    wexiste integer;
BEGIN
    -- Verificar si ya existe con ese ID en esa categoria
    SELECT count(*) INTO wexiste FROM tab_Subcategorias
    WHERE id_categoria = wid_categoria AND id_subcategoria = wid_subcategoria;

    IF wexiste > 0 THEN
        RETURN 'ERROR: Subcategoría ya existe con ese ID en esta categoría';
    END IF;

    -- Verificar nombre duplicado en la misma categoria
    SELECT count(*) INTO wexiste FROM tab_Subcategorias
    WHERE id_categoria = wid_categoria AND nom_subcategoria = wnom_subcategoria;

    IF wexiste > 0 THEN
        RETURN 'ERROR: Ya existe una subcategoría con ese nombre en esta categoría';
    END IF;

    -- USAR 'estado' en lugar de 'activo'
    INSERT INTO tab_Subcategorias (id_categoria, id_subcategoria, nom_subcategoria, estado)
    VALUES (wid_categoria, wid_subcategoria, TRIM(wnom_subcategoria), TRUE);

    RETURN 'SUCCESS: Subcategoría insertada correctamente';
EXCEPTION
    WHEN OTHERS THEN
        RETURN 'ERROR: ' || SQLERRM;
END;
$BODY$
LANGUAGE PLPGSQL;

-- Function to update a subcategory
CREATE OR REPLACE FUNCTION fun_update_subcategorias(
    wid_categoria integer,
    wid_subcategoria integer,
    wnom_subcategoria character varying,
    westado boolean DEFAULT TRUE
) RETURNS TEXT AS
$BODY$
DECLARE
    wexiste integer;
BEGIN
    -- Verificar nombre duplicado en la misma categoria (excluyendo la actual)
    SELECT count(*) INTO wexiste FROM tab_Subcategorias
    WHERE id_categoria = wid_categoria 
      AND nom_subcategoria = wnom_subcategoria 
      AND id_subcategoria != wid_subcategoria;

    IF wexiste > 0 THEN
        RETURN 'ERROR: Ya existe otra subcategoría con ese nombre en esta categoría';
    END IF;

    -- USAR 'estado' en lugar de 'activo'
    UPDATE tab_Subcategorias SET
        nom_subcategoria = TRIM(wnom_subcategoria),
        estado = westado
    WHERE id_categoria = wid_categoria AND id_subcategoria = wid_subcategoria;

    IF FOUND THEN
        RETURN 'SUCCESS: Subcategoría actualizada correctamente';
    ELSE
        RETURN 'ERROR: No se encontró la subcategoría';
    END IF;
EXCEPTION
    WHEN OTHERS THEN
        RETURN 'ERROR: ' || SQLERRM;
END;
$BODY$
LANGUAGE PLPGSQL;

-- Function to delete (soft delete) a subcategory
CREATE OR REPLACE FUNCTION fun_delete_subcategorias(
    wid_categoria integer,
    wid_subcategoria integer
) RETURNS TEXT AS
$BODY$
BEGIN
    -- USAR 'estado' en lugar de 'activo'
    UPDATE tab_Subcategorias SET estado = FALSE
    WHERE id_categoria = wid_categoria AND id_subcategoria = wid_subcategoria;

    IF FOUND THEN
        RETURN 'SUCCESS: Subcategoría desactivada correctamente';
    ELSE
        RETURN 'ERROR: No se encontró la subcategoría';
    END IF;
EXCEPTION
    WHEN OTHERS THEN
        RETURN 'ERROR: ' || SQLERRM;
END;
$BODY$
LANGUAGE PLPGSQL;
