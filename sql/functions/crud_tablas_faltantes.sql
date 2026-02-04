-- ==========================================
-- CRUDs PARA LAS TABLAS FALTANTES
-- ==========================================

-- ==========================================
-- CRUD TABLA: tab_Subcategorias
-- ==========================================

-- INSERT
CREATE OR REPLACE FUNCTION fun_insert_subcategorias(
    wid_categoria tab_Subcategorias.id_categoria%TYPE,
    wid_subcategoria tab_Subcategorias.id_subcategoria%TYPE,
    wnom_subcategoria tab_Subcategorias.nom_subcategoria%TYPE
) RETURNS TEXT AS
$BODY$
DECLARE
    wcategoria_existe BOOLEAN;
BEGIN
    -- Verificar que la categoría existe
    SELECT EXISTS(SELECT 1 FROM tab_Categorias WHERE id_categoria = wid_categoria AND estado = TRUE) INTO wcategoria_existe;
    IF NOT wcategoria_existe THEN
        RETURN 'ERROR: La categoría con ID ' || wid_categoria || ' no existe o está inactiva';
    END IF;
    
    -- Verificar duplicado
    IF EXISTS(SELECT 1 FROM tab_Subcategorias WHERE id_categoria = wid_categoria AND id_subcategoria = wid_subcategoria) THEN
        RETURN 'ERROR: La subcategoría ya existe';
    END IF;
    
    INSERT INTO tab_Subcategorias (id_categoria, id_subcategoria, nom_subcategoria, estado)
    VALUES (wid_categoria, wid_subcategoria, wnom_subcategoria, TRUE);
    
    RETURN 'SUCCESS: Subcategoría insertada correctamente';
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- UPDATE
CREATE OR REPLACE FUNCTION fun_update_subcategorias(
    wid_categoria tab_Subcategorias.id_categoria%TYPE,
    wid_subcategoria tab_Subcategorias.id_subcategoria%TYPE,
    wnom_subcategoria tab_Subcategorias.nom_subcategoria%TYPE,
    westado tab_Subcategorias.estado%TYPE DEFAULT TRUE
) RETURNS TEXT AS
$BODY$
BEGIN
    UPDATE tab_Subcategorias 
    SET nom_subcategoria = wnom_subcategoria, estado = westado
    WHERE id_categoria = wid_categoria AND id_subcategoria = wid_subcategoria;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Subcategoría actualizada correctamente';
    ELSE
        RETURN 'ERROR: No se encontró la subcategoría';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- DELETE (Soft)
CREATE OR REPLACE FUNCTION fun_delete_subcategorias(
    wid_categoria tab_Subcategorias.id_categoria%TYPE,
    wid_subcategoria tab_Subcategorias.id_subcategoria%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    UPDATE tab_Subcategorias SET estado = FALSE
    WHERE id_categoria = wid_categoria AND id_subcategoria = wid_subcategoria;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Subcategoría desactivada correctamente';
    ELSE
        RETURN 'ERROR: No se encontró la subcategoría';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- ==========================================
-- CRUD TABLA: tab_Metodos_Pago
-- ==========================================

-- INSERT
CREATE OR REPLACE FUNCTION fun_insert_metodos_pago(
    wid_metodo_pago tab_Metodos_Pago.id_metodo_pago%TYPE,
    wnombre_metodo tab_Metodos_Pago.nombre_metodo%TYPE,
    wdescripcion tab_Metodos_Pago.descripcion%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    IF EXISTS(SELECT 1 FROM tab_Metodos_Pago WHERE id_metodo_pago = wid_metodo_pago OR nombre_metodo = wnombre_metodo) THEN
        RETURN 'ERROR: El método de pago ya existe';
    END IF;
    
    INSERT INTO tab_Metodos_Pago (id_metodo_pago, nombre_metodo, descripcion)
    VALUES (wid_metodo_pago, wnombre_metodo, wdescripcion);
    
    RETURN 'SUCCESS: Método de pago insertado correctamente';
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- UPDATE
CREATE OR REPLACE FUNCTION fun_update_metodos_pago(
    wid_metodo_pago tab_Metodos_Pago.id_metodo_pago%TYPE,
    wnombre_metodo tab_Metodos_Pago.nombre_metodo%TYPE,
    wdescripcion tab_Metodos_Pago.descripcion%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    UPDATE tab_Metodos_Pago 
    SET nombre_metodo = wnombre_metodo, descripcion = wdescripcion
    WHERE id_metodo_pago = wid_metodo_pago;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Método de pago actualizado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el método de pago';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- DELETE (Hard)
CREATE OR REPLACE FUNCTION fun_delete_metodos_pago(
    wid_metodo_pago tab_Metodos_Pago.id_metodo_pago%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    DELETE FROM tab_Metodos_Pago WHERE id_metodo_pago = wid_metodo_pago;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Método de pago eliminado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el método de pago';
    END IF;
EXCEPTION 
    WHEN FOREIGN_KEY_VIOLATION THEN
        RETURN 'ERROR: No se puede eliminar porque hay usuarios o pagos asociados';
    WHEN OTHERS THEN
        RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- ==========================================
-- CRUD TABLA: tab_Promociones
-- ==========================================

-- INSERT
CREATE OR REPLACE FUNCTION fun_insert_promociones(
    wid_promocion tab_Promociones.id_promocion%TYPE,
    wdescripcion tab_Promociones.descripcion%TYPE,
    wdescuento tab_Promociones.descuento%TYPE,
    wfecha_inicio tab_Promociones.fecha_inicio%TYPE,
    wfecha_fin tab_Promociones.fecha_fin%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    IF wfecha_fin < wfecha_inicio THEN
        RETURN 'ERROR: La fecha de fin debe ser posterior a la fecha de inicio';
    END IF;
    
    IF EXISTS(SELECT 1 FROM tab_Promociones WHERE id_promocion = wid_promocion) THEN
        RETURN 'ERROR: La promoción ya existe';
    END IF;
    
    INSERT INTO tab_Promociones (id_promocion, descripcion, descuento, fecha_inicio, fecha_fin)
    VALUES (wid_promocion, wdescripcion, wdescuento, wfecha_inicio, wfecha_fin);
    
    RETURN 'SUCCESS: Promoción insertada correctamente';
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- UPDATE
CREATE OR REPLACE FUNCTION fun_update_promociones(
    wid_promocion tab_Promociones.id_promocion%TYPE,
    wdescripcion tab_Promociones.descripcion%TYPE,
    wdescuento tab_Promociones.descuento%TYPE,
    wfecha_inicio tab_Promociones.fecha_inicio%TYPE,
    wfecha_fin tab_Promociones.fecha_fin%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    IF wfecha_fin < wfecha_inicio THEN
        RETURN 'ERROR: La fecha de fin debe ser posterior a la fecha de inicio';
    END IF;
    
    UPDATE tab_Promociones 
    SET descripcion = wdescripcion, descuento = wdescuento, 
        fecha_inicio = wfecha_inicio, fecha_fin = wfecha_fin
    WHERE id_promocion = wid_promocion;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Promoción actualizada correctamente';
    ELSE
        RETURN 'ERROR: No se encontró la promoción';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- DELETE (Hard)
CREATE OR REPLACE FUNCTION fun_delete_promociones(
    wid_promocion tab_Promociones.id_promocion%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    DELETE FROM tab_Promociones WHERE id_promocion = wid_promocion;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Promoción eliminada correctamente';
    ELSE
        RETURN 'ERROR: No se encontró la promoción';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- ==========================================
-- CRUD TABLA: tab_Contacto
-- ==========================================

-- INSERT
CREATE OR REPLACE FUNCTION fun_insert_contacto(
    wid_contacto tab_Contacto.id_contacto%TYPE,
    wnombre_remitente tab_Contacto.nombre_remitente%TYPE,
    wcorreo_remitente tab_Contacto.correo_remitente%TYPE,
    wtelefono_remitente tab_Contacto.telefono_remitente%TYPE,
    wasunto tab_Contacto.asunto%TYPE,
    wmensaje tab_Contacto.mensaje%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    INSERT INTO tab_Contacto (id_contacto, nombre_remitente, correo_remitente, telefono_remitente, asunto, mensaje, estado)
    VALUES (wid_contacto, wnombre_remitente, wcorreo_remitente, wtelefono_remitente, wasunto, wmensaje, 'pendiente');
    
    RETURN 'SUCCESS: Mensaje de contacto registrado correctamente';
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- UPDATE
CREATE OR REPLACE FUNCTION fun_update_contacto(
    wid_contacto tab_Contacto.id_contacto%TYPE,
    westado tab_Contacto.estado%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    UPDATE tab_Contacto SET estado = westado WHERE id_contacto = wid_contacto;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Estado del contacto actualizado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el mensaje de contacto';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- DELETE (Hard)
CREATE OR REPLACE FUNCTION fun_delete_contacto(
    wid_contacto tab_Contacto.id_contacto%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    DELETE FROM tab_Contacto WHERE id_contacto = wid_contacto;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Mensaje de contacto eliminado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el mensaje de contacto';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- ==========================================
-- CRUD TABLA: tab_Empleados
-- ==========================================

-- INSERT
CREATE OR REPLACE FUNCTION fun_insert_empleados(
    wid_empleado tab_Empleados.id_empleado%TYPE,
    wnom_empleado tab_Empleados.nom_empleado%TYPE,
    wapellido_empleado tab_Empleados.apellido_empleado%TYPE,
    wcorreo tab_Empleados.correo%TYPE,
    wtelefono tab_Empleados.telefono%TYPE,
    wpuesto tab_Empleados.puesto%TYPE,
    wfecha_contratacion tab_Empleados.fecha_contratacion%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    IF EXISTS(SELECT 1 FROM tab_Empleados WHERE id_empleado = wid_empleado) THEN
        RETURN 'ERROR: El empleado ya existe';
    END IF;
    
    INSERT INTO tab_Empleados (id_empleado, nom_empleado, apellido_empleado, correo, telefono, puesto, fecha_contratacion)
    VALUES (wid_empleado, wnom_empleado, wapellido_empleado, wcorreo, wtelefono, wpuesto, wfecha_contratacion);
    
    RETURN 'SUCCESS: Empleado insertado correctamente';
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- UPDATE
CREATE OR REPLACE FUNCTION fun_update_empleados(
    wid_empleado tab_Empleados.id_empleado%TYPE,
    wnom_empleado tab_Empleados.nom_empleado%TYPE,
    wapellido_empleado tab_Empleados.apellido_empleado%TYPE,
    wcorreo tab_Empleados.correo%TYPE,
    wtelefono tab_Empleados.telefono%TYPE,
    wpuesto tab_Empleados.puesto%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    UPDATE tab_Empleados 
    SET nom_empleado = wnom_empleado, apellido_empleado = wapellido_empleado,
        correo = wcorreo, telefono = wtelefono, puesto = wpuesto
    WHERE id_empleado = wid_empleado;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Empleado actualizado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el empleado';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- DELETE (Hard)
CREATE OR REPLACE FUNCTION fun_delete_empleados(
    wid_empleado tab_Empleados.id_empleado%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    DELETE FROM tab_Empleados WHERE id_empleado = wid_empleado;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Empleado eliminado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el empleado';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- ==========================================
-- CRUD TABLA: tab_Eventos
-- ==========================================

-- INSERT
CREATE OR REPLACE FUNCTION fun_insert_eventos(
    wid_evento tab_Eventos.id_evento%TYPE,
    wtitulo tab_Eventos.titulo%TYPE,
    wdescripcion tab_Eventos.descripcion%TYPE,
    wfecha_inicio tab_Eventos.fecha_inicio%TYPE,
    wfecha_fin tab_Eventos.fecha_fin%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    IF EXISTS(SELECT 1 FROM tab_Eventos WHERE id_evento = wid_evento) THEN
        RETURN 'ERROR: El evento ya existe';
    END IF;
    
    INSERT INTO tab_Eventos (id_evento, titulo, descripcion, fecha_inicio, fecha_fin)
    VALUES (wid_evento, wtitulo, wdescripcion, wfecha_inicio, wfecha_fin);
    
    RETURN 'SUCCESS: Evento insertado correctamente';
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- UPDATE
CREATE OR REPLACE FUNCTION fun_update_eventos(
    wid_evento tab_Eventos.id_evento%TYPE,
    wtitulo tab_Eventos.titulo%TYPE,
    wdescripcion tab_Eventos.descripcion%TYPE,
    wfecha_inicio tab_Eventos.fecha_inicio%TYPE,
    wfecha_fin tab_Eventos.fecha_fin%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    UPDATE tab_Eventos 
    SET titulo = wtitulo, descripcion = wdescripcion,
        fecha_inicio = wfecha_inicio, fecha_fin = wfecha_fin
    WHERE id_evento = wid_evento;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Evento actualizado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el evento';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- DELETE (Hard)
CREATE OR REPLACE FUNCTION fun_delete_eventos(
    wid_evento tab_Eventos.id_evento%TYPE
) RETURNS TEXT AS
$BODY$
BEGIN
    DELETE FROM tab_Eventos WHERE id_evento = wid_evento;
    
    IF FOUND THEN
        RETURN 'SUCCESS: Evento eliminado correctamente';
    ELSE
        RETURN 'ERROR: No se encontró el evento';
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN 'ERROR SQL: ' || SQLERRM;
END;
$BODY$ LANGUAGE PLPGSQL;

-- ==========================================
-- NOTA: Las demás tablas (Carrito, Facturas, Envios, etc.)
-- son tablas transaccionales que generalmente NO necesitan
-- CRUDs tradicionales, sino funciones de negocio específicas.
-- Si las necesitas, avísame y las creo.
-- ==========================================
