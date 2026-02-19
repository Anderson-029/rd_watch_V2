-- =============================================
-- SCRIPT DE OPTIMIZACIÓN SQL: RD-WATCH V2 (POSTGRESQL VERSION)
-- Propósito: Implementar Contadores Persistentes e Índices en PostgreSQL
-- =============================================

-- 1. Creación de la Tabla de Métricas (Postgres lo trata como minúsculas por defecto)
CREATE TABLE IF NOT EXISTS tab_sistema_metricas (
    metric_key VARCHAR(50) PRIMARY KEY,
    metric_value BIGINT DEFAULT 0,
    last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Función para autogestionar el timestamp de actualización
CREATE OR REPLACE FUNCTION update_last_sync_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.last_sync = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Trigger para el timestamp
DROP TRIGGER IF EXISTS trg_update_sync_timestamp ON tab_sistema_metricas;
CREATE TRIGGER trg_update_sync_timestamp
BEFORE UPDATE ON tab_sistema_metricas
FOR EACH ROW EXECUTE FUNCTION update_last_sync_column();

-- 3. Función genérica para actualizar contadores (Sincronización O(1))
CREATE OR REPLACE FUNCTION sync_metrics_counter()
RETURNS TRIGGER AS $$
DECLARE
    target_key VARCHAR(50);
    increment INT;
BEGIN
    IF (TG_OP = 'INSERT') THEN
        increment := 1;
    ELSIF (TG_OP = 'DELETE') THEN
        increment := -1;
    END IF;

    -- Mapeo según el nombre de la tabla (Postgres guarda nombres en minúsculas si se crearon sin comillas)
    CASE LOWER(TG_TABLE_NAME)
        WHEN 'tab_productos' THEN target_key := 'total_productos';
        WHEN 'tab_orden'     THEN target_key := 'total_pedidos';
        WHEN 'tab_servicios' THEN target_key := 'total_servicios';
        WHEN 'tab_usuarios'  THEN 
            IF (TG_OP = 'INSERT' AND NEW.rol = 'cliente') THEN
                target_key := 'total_clientes';
            ELSIF (TG_OP = 'DELETE' AND OLD.rol = 'cliente') THEN
                target_key := 'total_clientes';
            ELSE
                RETURN NULL;
            END IF;
    END CASE;

    IF target_key IS NOT NULL THEN
        UPDATE tab_sistema_metricas 
        SET metric_value = metric_value + increment 
        WHERE metric_key = target_key;
    END IF;

    IF (TG_OP = 'DELETE') THEN RETURN OLD; ELSE RETURN NEW; END IF;
END;
$$ LANGUAGE plpgsql;

-- 4. Creación de Triggers (Asociados a las tablas existentes)

-- PRODUCTOS
DROP TRIGGER IF EXISTS trg_prod_sync ON tab_Productos;
CREATE TRIGGER trg_prod_sync
AFTER INSERT OR DELETE ON tab_Productos
FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter();

-- PEDIDOS
DROP TRIGGER IF EXISTS trg_orden_sync ON tab_Orden;
CREATE TRIGGER trg_orden_sync
AFTER INSERT OR DELETE ON tab_Orden
FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter();

-- USUARIOS
DROP TRIGGER IF EXISTS trg_user_sync ON tab_Usuarios;
CREATE TRIGGER trg_user_sync
AFTER INSERT OR DELETE ON tab_Usuarios
FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter();

-- SERVICIOS
DROP TRIGGER IF EXISTS trg_serv_sync ON tab_Servicios;
CREATE TRIGGER trg_serv_sync
AFTER INSERT OR DELETE ON tab_Servicios
FOR EACH ROW EXECUTE FUNCTION sync_metrics_counter();

-- 5. Inicialización y Optimización de Índices
INSERT INTO tab_sistema_metricas (metric_key, metric_value) VALUES 
('total_productos', (SELECT COUNT(*) FROM tab_Productos)),
('total_pedidos', (SELECT COUNT(*) FROM tab_Orden)),
('total_clientes', (SELECT COUNT(*) FROM tab_Usuarios WHERE rol = 'cliente')),
('total_servicios', (SELECT COUNT(*) FROM tab_Servicios))
ON CONFLICT (metric_key) DO UPDATE SET metric_value = EXCLUDED.metric_value;

CREATE INDEX IF NOT EXISTS idx_rate_limit_perf ON tab_Rate_Limits (identificador, nom_accion, fec_intento DESC);
