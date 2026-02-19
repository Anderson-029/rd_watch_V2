-- =============================================
-- SCRIPT DE OPTIMIZACIÓN SQL MAESTRO: RD-WATCH V2 (POSTGRESQL)
-- =============================================

-- 1. Estructura de Métricas
CREATE TABLE IF NOT EXISTS tab_sistema_metricas (
    metric_key VARCHAR(50) PRIMARY KEY,
    metric_value BIGINT DEFAULT 0,
    last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Función de Actualización de Timestamp
CREATE OR REPLACE FUNCTION update_last_sync_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.last_sync = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

DROP TRIGGER IF EXISTS trg_update_sync_timestamp ON tab_sistema_metricas;
CREATE TRIGGER trg_update_sync_timestamp
BEFORE UPDATE ON tab_sistema_metricas
FOR EACH ROW EXECUTE FUNCTION update_last_sync_column();

-- 3. Función de Sincronización Avanzada
CREATE OR REPLACE FUNCTION sync_metrics_counter_maestro()
RETURNS TRIGGER AS $$
DECLARE
    increment INT;
BEGIN
    increment := CASE WHEN TG_OP = 'INSERT' THEN 1 ELSE -1 END;

    CASE LOWER(TG_TABLE_NAME)
        WHEN 'tab_productos' THEN 
            UPDATE tab_sistema_metricas SET metric_value = metric_value + increment WHERE metric_key = 'total_productos';
        
        WHEN 'tab_orden' THEN 
            UPDATE tab_sistema_metricas SET metric_value = metric_value + increment WHERE metric_key = 'total_pedidos';
            -- Conteo por estado para el dashboard
            IF (TG_OP = 'INSERT') THEN
                UPDATE tab_sistema_metricas SET metric_value = metric_value + 1 WHERE metric_key = 'pedidos_' || NEW.estado_orden;
            ELSIF (TG_OP = 'DELETE') THEN
                UPDATE tab_sistema_metricas SET metric_value = metric_value - 1 WHERE metric_key = 'pedidos_' || OLD.estado_orden;
            END IF;

        WHEN 'tab_servicios' THEN 
            UPDATE tab_sistema_metricas SET metric_value = metric_value + increment WHERE metric_key = 'total_servicios';
        
        WHEN 'tab_reservas' THEN
            UPDATE tab_sistema_metricas SET metric_value = metric_value + increment WHERE metric_key = 'total_reservas';
            -- Si una reserva se inserta, asumimos que es un servicio potencial
            IF (TG_OP = 'INSERT' AND NEW.estado_reserva = 'completada') THEN
                UPDATE tab_sistema_metricas SET metric_value = metric_value + 1 WHERE metric_key = 'total_servicios_realizados';
            ELSIF (TG_OP = 'DELETE' AND OLD.estado_reserva = 'completada') THEN
                UPDATE tab_sistema_metricas SET metric_value = metric_value - 1 WHERE metric_key = 'total_servicios_realizados';
            END IF;

        WHEN 'tab_usuarios' THEN 
            IF ((TG_OP = 'INSERT' AND NEW.rol = 'cliente') OR (TG_OP = 'DELETE' AND OLD.rol = 'cliente')) THEN
                UPDATE tab_sistema_metricas SET metric_value = metric_value + increment WHERE metric_key = 'total_clientes';
            END IF;

        WHEN 'tab_opiniones' THEN
            UPDATE tab_sistema_metricas SET metric_value = metric_value + increment WHERE metric_key = 'total_opiniones';
            IF (TG_OP = 'INSERT' AND NEW.calificacion >= 3) THEN
                UPDATE tab_sistema_metricas SET metric_value = metric_value + 1 WHERE metric_key = 'total_opiniones_satisfechas';
            ELSIF (TG_OP = 'DELETE' AND OLD.calificacion >= 3) THEN
                UPDATE tab_sistema_metricas SET metric_value = metric_value - 1 WHERE metric_key = 'total_opiniones_satisfechas';
            END IF;
    END CASE;

    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$ LANGUAGE plpgsql;

-- 4. Triggers (Asegurar minúsculas internas de Postgres o nombres reales)
DROP TRIGGER IF EXISTS trg_maestro_prod ON tab_Productos;
CREATE TRIGGER trg_maestro_prod AFTER INSERT OR DELETE ON tab_Productos FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter_maestro();

DROP TRIGGER IF EXISTS trg_maestro_orden ON tab_Orden;
CREATE TRIGGER trg_maestro_orden AFTER INSERT OR DELETE ON tab_Orden FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter_maestro();

DROP TRIGGER IF EXISTS trg_maestro_serv ON tab_Servicios;
CREATE TRIGGER trg_maestro_serv AFTER INSERT OR DELETE ON tab_Servicios FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter_maestro();

DROP TRIGGER IF EXISTS trg_maestro_res ON tab_Reservas;
CREATE TRIGGER trg_maestro_res AFTER INSERT OR DELETE ON tab_Reservas FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter_maestro();

DROP TRIGGER IF EXISTS trg_maestro_user ON tab_Usuarios;
CREATE TRIGGER trg_maestro_user AFTER INSERT OR DELETE ON tab_Usuarios FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter_maestro();

-- 5. Inicialización Total
DELETE FROM tab_sistema_metricas;
INSERT INTO tab_sistema_metricas (metric_key, metric_value) VALUES 
('total_productos', (SELECT COUNT(*) FROM tab_Productos)),
('total_pedidos', (SELECT COUNT(*) FROM tab_Orden)),
('total_clientes', (SELECT COUNT(*) FROM tab_Usuarios WHERE rol = 'cliente')),
('total_servicios', (SELECT COUNT(*) FROM tab_Servicios)),
('total_reservas', (SELECT COUNT(*) FROM tab_Reservas)),
('total_servicios_realizados', (SELECT COUNT(*) FROM tab_Reservas WHERE estado_reserva = 'completada')),
('pedidos_pendiente', (SELECT COUNT(*) FROM tab_Orden WHERE estado_orden = 'pendiente')),
('pedidos_confirmado', (SELECT COUNT(*) FROM tab_Orden WHERE estado_orden = 'confirmado')),
('pedidos_enviado', (SELECT COUNT(*) FROM tab_Orden WHERE estado_orden = 'enviado')),
('pedidos_entregado', (SELECT COUNT(*) FROM tab_Orden WHERE estado_orden = 'entregado')),
('pedidos_cancelado', (SELECT COUNT(*) FROM tab_Orden WHERE estado_orden = 'cancelado')),
('total_opiniones', (SELECT COUNT(*) FROM tab_Opiniones)),
('total_opiniones_satisfechas', (SELECT COUNT(*) FROM tab_Opiniones WHERE calificacion >= 3));

-- 6. Optimización de Índices para Consultas Filtradas (O(log N))
CREATE INDEX IF NOT EXISTS idx_productos_cat_sub ON tab_Productos (id_categoria, id_subcategoria);
CREATE INDEX IF NOT EXISTS idx_orden_user_status ON tab_Orden (id_usuario, estado_orden);
CREATE INDEX IF NOT EXISTS idx_reservas_user_status ON tab_Reservas (id_usuario, estado_reserva);
CREATE INDEX IF NOT EXISTS idx_rate_limit_perf ON tab_Rate_Limits (identificador, nom_accion, fec_intento DESC);
