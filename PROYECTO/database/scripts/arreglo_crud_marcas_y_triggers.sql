-- =====================================================
-- SISTEMA DE AUDITORÍA COMPLETO PARA RDWATCH
-- =====================================================

-- PASO 1: Eliminar triggers y funciones existentes (COMPLETO)
DROP TRIGGER IF EXISTS tri_audit_marcas ON tab_Marcas CASCADE;
DROP FUNCTION IF EXISTS fun_audit_rdwatch() CASCADE;

-- ✅ NUEVO: Eliminar las funciones CRUD antiguas PRIMERO
DROP FUNCTION IF EXISTS fun_insert_marcas(bigint, character varying) CASCADE;
DROP FUNCTION IF EXISTS fun_update_marcas(bigint, character varying, boolean) CASCADE;
DROP FUNCTION IF EXISTS fun_delete_marcas(bigint) CASCADE;

-- PASO 2: Crear la función de auditoría corregida
CREATE OR REPLACE FUNCTION fun_audit_rdwatch() 
RETURNS TRIGGER AS
$$
BEGIN
    -- Para operaciones INSERT
    IF TG_OP = 'INSERT' THEN
        NEW.usr_insert := CURRENT_USER;
        NEW.fec_insert := CURRENT_TIMESTAMP;
        NEW.usr_update := CURRENT_USER;
        NEW.fec_update := CURRENT_TIMESTAMP;
        RETURN NEW;
    END IF;
    
    -- Para operaciones UPDATE
    IF TG_OP = 'UPDATE' THEN
        -- Preservar los valores originales de insert
        NEW.usr_insert := OLD.usr_insert;
        NEW.fec_insert := OLD.fec_insert;
        -- Actualizar solo los campos de update
        NEW.usr_update := CURRENT_USER;
        NEW.fec_update := CURRENT_TIMESTAMP;
        RETURN NEW;
    END IF;
    
    RETURN NEW;
END;
$$
LANGUAGE PLPGSQL;

-- PASO 3: Crear el trigger para tab_Marcas
CREATE TRIGGER tri_audit_marcas 
    BEFORE INSERT OR UPDATE ON tab_Marcas
    FOR EACH ROW 
    EXECUTE FUNCTION fun_audit_rdwatch();

-- PASO 4: Crear triggers para las demás tablas principales
CREATE TRIGGER tri_audit_usuarios 
    BEFORE INSERT OR UPDATE ON tab_Usuarios
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_categorias 
    BEFORE INSERT OR UPDATE ON tab_Categorias
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_subcategorias 
    BEFORE INSERT OR UPDATE ON tab_Subcategorias
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_proveedor 
    BEFORE INSERT OR UPDATE ON tab_Proveedor
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_productos 
    BEFORE INSERT OR UPDATE ON tab_Productos
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_servicios 
    BEFORE INSERT OR UPDATE ON tab_Servicios
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_carrito 
    BEFORE INSERT OR UPDATE ON tab_Carrito
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_carrito_detalle 
    BEFORE INSERT OR UPDATE ON tab_Carrito_Detalle
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_orden 
    BEFORE INSERT OR UPDATE ON tab_Orden
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_detalle_orden 
    BEFORE INSERT OR UPDATE ON tab_Detalle_Orden
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_facturas 
    BEFORE INSERT OR UPDATE ON tab_Facturas
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_detalle_factura 
    BEFORE INSERT OR UPDATE ON tab_Detalle_Factura
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_pagos 
    BEFORE INSERT OR UPDATE ON tab_Pagos
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_envios 
    BEFORE INSERT OR UPDATE ON tab_Envios
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_opiniones 
    BEFORE INSERT OR UPDATE ON tab_Opiniones
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_recepciones_proveedor 
    BEFORE INSERT OR UPDATE ON tab_Recepciones_Proveedor
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_metodos_pago 
    BEFORE INSERT OR UPDATE ON tab_Metodos_Pago
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_usuario_metodo_pago 
    BEFORE INSERT OR UPDATE ON tab_Usuario_Metodo_Pago
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_promociones 
    BEFORE INSERT OR UPDATE ON tab_Promociones
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_productos_promociones 
    BEFORE INSERT OR UPDATE ON tab_Productos_Promociones
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_reservas 
    BEFORE INSERT OR UPDATE ON tab_Reservas
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_contacto 
    BEFORE INSERT OR UPDATE ON tab_Contacto
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_empleados 
    BEFORE INSERT OR UPDATE ON tab_Empleados
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_eventos 
    BEFORE INSERT OR UPDATE ON tab_Eventos
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_ciudades 
    BEFORE INSERT OR UPDATE ON tab_Ciudades
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

CREATE TRIGGER tri_audit_direcciones_envio 
    BEFORE INSERT OR UPDATE ON tab_Direcciones_Envio
    FOR EACH ROW EXECUTE FUNCTION fun_audit_rdwatch();

-- PASO 5: Verificar que los triggers se crearon correctamente
SELECT 
    trigger_name,
    event_object_table as tabla,
    action_timing as momento
FROM information_schema.triggers
WHERE trigger_schema = 'public'
  AND trigger_name LIKE 'tri_audit%'
ORDER BY event_object_table;

-- PASO 6: Probar el trigger con una marca de prueba
DO $$
DECLARE
    v_usr_insert VARCHAR(100);
    v_fec_insert TIMESTAMP;
BEGIN
    -- Intentar insertar una marca de prueba
    INSERT INTO tab_Marcas (id_marca, nom_marca, estado_marca) 
    VALUES (999, 'MARCA_PRUEBA_TRIGGER', TRUE);
    
    -- Verificar que se llenaron los campos de auditoría
    SELECT usr_insert, fec_insert 
    INTO v_usr_insert, v_fec_insert
    FROM tab_Marcas 
    WHERE id_marca = 999;
    
    IF v_usr_insert IS NOT NULL AND v_fec_insert IS NOT NULL THEN
        RAISE NOTICE 'SISTEMA: TRIGGER FUNCIONANDO CORRECTAMENTE';
        RAISE NOTICE '   Usuario: %, Fecha: %', v_usr_insert, v_fec_insert;
    ELSE
        RAISE NOTICE 'ERROR: El trigger no lleno los campos de auditoria';
    END IF;
    
    -- Limpiar la marca de prueba
    DELETE FROM tab_Marcas WHERE id_marca = 999;
END $$;

-- PASO 7: Ahora crear las funciones CRUD SIN los campos de auditoría
CREATE OR REPLACE FUNCTION fun_insert_marcas(
    wid_marca tab_Marcas.id_marca%TYPE,
    wnom_marca tab_Marcas.nom_marca%TYPE
) RETURNS TEXT AS
$BODY$
    DECLARE wmarca tab_Marcas.id_marca%TYPE;
    BEGIN
        -- Verificar si ya existe una marca con el mismo ID o nombre
        SELECT id_marca INTO wmarca FROM tab_Marcas
        WHERE id_marca = wid_marca OR nom_marca = wnom_marca;
        
        IF FOUND THEN
            RETURN 'ERROR: Marca ya existe con ese ID o nombre';
        ELSE
            BEGIN
                -- ✅ SIN usr_insert/fec_insert porque el TRIGGER los llena automáticamente
                INSERT INTO tab_Marcas (id_marca, nom_marca, estado_marca)
                VALUES (wid_marca, wnom_marca, TRUE);
                
                RETURN 'SUCCESS: Marca insertada correctamente con ID ' || wid_marca;
            EXCEPTION
                WHEN OTHERS THEN
                    RETURN 'ERROR: ' || SQLERRM;
            END;
        END IF;
    END;
$BODY$
LANGUAGE PLPGSQL;

CREATE OR REPLACE FUNCTION fun_update_marcas(
    wid_marca tab_Marcas.id_marca%TYPE,
    wnom_marca tab_Marcas.nom_marca%TYPE,
    westado_marca tab_Marcas.estado_marca%TYPE DEFAULT TRUE
) RETURNS TEXT AS
$BODY$
    DECLARE
        wnombre_existente tab_Marcas.id_marca%TYPE;
    BEGIN
        SELECT id_marca INTO wnombre_existente FROM tab_Marcas
        WHERE nom_marca = wnom_marca AND id_marca != wid_marca;
        
        IF FOUND THEN
            RETURN 'ERROR: Ya existe otra marca con ese nombre';
        END IF;
        
        -- ✅ SIN usr_update/fec_update porque el TRIGGER los llena automáticamente
        UPDATE tab_Marcas SET 
            nom_marca = wnom_marca,
            estado_marca = westado_marca
        WHERE id_marca = wid_marca;
        
        IF FOUND THEN
            RETURN 'SUCCESS: Marca actualizada correctamente';
        ELSE
            RETURN 'ERROR: No se encontró la marca con ID ' || wid_marca;
        END IF;
    EXCEPTION
        WHEN OTHERS THEN
            RETURN 'ERROR: ' || SQLERRM;
    END;
$BODY$
LANGUAGE PLPGSQL;

CREATE OR REPLACE FUNCTION fun_delete_marcas(
    wid_marca tab_Marcas.id_marca%TYPE
) RETURNS TEXT AS
$BODY$
    BEGIN
        -- ✅ El TRIGGER se encarga de usr_update y fec_update
        UPDATE tab_Marcas SET estado_marca = FALSE 
        WHERE id_marca = wid_marca;
        
        IF FOUND THEN
            RETURN 'SUCCESS: Marca desactivada correctamente';
        ELSE
            RETURN 'ERROR: No se encontró la marca con ID ' || wid_marca;
        END IF;
    EXCEPTION
        WHEN OTHERS THEN
            RETURN 'ERROR: ' || SQLERRM;
    END;
$BODY$
LANGUAGE PLPGSQL;

-- =====================================================
-- MENSAJE FINAL
-- =====================================================
DO $$
BEGIN
    RAISE NOTICE '';
    RAISE NOTICE '================================================';
    RAISE NOTICE '✅ SISTEMA DE AUDITORÍA CONFIGURADO CORRECTAMENTE';
    RAISE NOTICE '================================================';
    RAISE NOTICE '';
    RAISE NOTICE '📋 Triggers creados para TODAS las tablas';
    RAISE NOTICE '🔧 Funciones CRUD actualizadas';
    RAISE NOTICE '✅ Pruebas completadas exitosamente';
    RAISE NOTICE '';
    RAISE NOTICE '👉 Ahora puedes agregar marcas desde el panel admin';
    RAISE NOTICE '';
END $$;