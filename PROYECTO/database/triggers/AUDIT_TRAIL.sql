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

CREATE TRIGGER tri_audit_marcas 
    BEFORE INSERT OR UPDATE ON tab_Marcas
    FOR EACH ROW 
    EXECUTE FUNCTION fun_audit_rdwatch();

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