DROP TABLE IF EXISTS tab_Detalle_Factura;
DROP TABLE IF EXISTS tab_Facturas;
DROP TABLE IF EXISTS tab_Pagos;
DROP TABLE IF EXISTS tab_Detalle_Orden;
DROP TABLE IF EXISTS tab_Orden_Servicios;
DROP TABLE IF EXISTS tab_Carrito_Detalle;
DROP TABLE IF EXISTS tab_Recepciones_Proveedor;
DROP TABLE IF EXISTS tab_Opiniones;
DROP TABLE IF EXISTS tab_Envios;
DROP TABLE IF EXISTS tab_Orden;
DROP TABLE IF EXISTS tab_Carrito;
DROP TABLE IF EXISTS tab_Productos_Promociones;
DROP TABLE IF EXISTS tab_Productos;
DROP TABLE IF EXISTS tab_Direcciones_Envio;
DROP TABLE IF EXISTS tab_Ciudades;
DROP TABLE IF EXISTS tab_Departamentos;
DROP TABLE IF EXISTS tab_Reservas;
DROP TABLE IF EXISTS tab_Contacto;
DROP TABLE IF EXISTS tab_Usuario_Metodo_Pago;
DROP TABLE IF EXISTS tab_Metodos_Pago;
DROP TABLE IF EXISTS tab_Servicios;
DROP TABLE IF EXISTS tab_Promociones;
DROP TABLE IF EXISTS tab_Proveedor;
DROP TABLE IF EXISTS tab_Marcas;
DROP TABLE IF EXISTS tab_Subcategorias;
DROP TABLE IF EXISTS tab_Categorias;
DROP TABLE IF EXISTS tab_Usuarios;
DROP TABLE IF EXISTS tab_Empleados;
DROP TABLE IF EXISTS tab_Eventos;


-- =====================================================
-- Tabla: tab_Usuarios
-- Almacena la información de los usuarios del sistema.
-- =====================================================

CREATE TABLE IF NOT EXISTS tab_Usuarios
(
    id_usuario              BIGINT NOT NULL,              -- Identificador único del usuario
    nom_usuario             VARCHAR(100) NOT NULL,        -- Nombre completo del usuario
    correo_usuario          VARCHAR(100) NOT NULL,        -- Correo electrónico del usuario
    num_telefono_usuario    BIGINT NOT NULL,              -- Número de teléfono del usuario
    direccion_principal     VARCHAR(255),                 -- Dirección del usuario
    fecha_registro          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo                  BOOLEAN DEFAULT TRUE,
    contra                  VARCHAR(255) NOT NULL,         -- Hash de contraseña (Bcrypt)
    rol                     VARCHAR(20) DEFAULT 'cliente',-- Rol del usuario
    
    salt                    VARCHAR(255),
    intentos_fallidos       SMALLINT DEFAULT 0,
    bloqueado               BOOLEAN DEFAULT FALSE,
    fecha_bloqueo           TIMESTAMP,
    ultimo_acceso           TIMESTAMP,
    token_recuperacion      TEXT,
    token_expiracion        TIMESTAMP,

    -- Auditoría
    usr_insert              VARCHAR(100),
    fec_insert              TIMESTAMP,
    usr_update              VARCHAR(100),
    fec_update              TIMESTAMP,
    usr_delete              VARCHAR(100),
    fec_delete              TIMESTAMP,

    PRIMARY KEY (id_usuario),
    UNIQUE (correo_usuario)
);

-- Índices recomendados
CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_correo            ON tab_Usuarios (correo_usuario);
CREATE INDEX IF NOT EXISTS idx_usuarios_correo_lower            ON tab_Usuarios (LOWER(correo_usuario));
CREATE INDEX IF NOT EXISTS idx_usuarios_activo                  ON tab_Usuarios (activo);
CREATE INDEX IF NOT EXISTS idx_usuarios_bloqueado               ON tab_Usuarios (bloqueado);
CREATE INDEX IF NOT EXISTS idx_usuarios_ultimo_acceso           ON tab_Usuarios (ultimo_acceso);
CREATE INDEX IF NOT EXISTS idx_usuarios_token_recuperacion      ON tab_Usuarios (token_recuperacion);
CREATE INDEX IF NOT EXISTS idx_usuarios_fecha_registro          ON tab_Usuarios (fecha_registro);
CREATE INDEX IF NOT EXISTS idx_usuarios_rol                     ON tab_Usuarios (rol);


-- Tabla: tab_Categorias
-- Almacena las categorías de los productos (ej. "Relojes de Pulsera", "Relojes de Bolsillo").
CREATE TABLE IF NOT EXISTS tab_Categorias
(
    id_categoria            INT NOT NULL, -- Identificador único de la categoría
    nom_categoria           VARCHAR(100) NOT NULL, -- Nombre de la categoría
    descripcion_categoria   TEXT NOT NULL, -- Descripción de la categoría
    estado                  BOOLEAN NOT NULL DEFAULT TRUE, -- Indica si la categoría está activa o inactiva

       -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_categoria),
    UNIQUE (nom_categoria)

);

-- Tabla: tab_Subcategorias
-- Almacena las subcategorías de los productos, vinculadas a una categoría principal.
CREATE TABLE IF NOT EXISTS tab_Subcategorias
(
    id_categoria            INT NOT NULL, -- Clave foránea a tab_Categorias
    id_subcategoria         INT NOT NULL, -- Identificador único de la subcategoría
    nom_subcategoria        VARCHAR(100) NOT NULL, -- Nombre de la subcategoría
    estado                  BOOLEAN NOT NULL DEFAULT TRUE, -- Indica si la subcategoría está activa o inactiva

       -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_categoria, id_subcategoria),
    UNIQUE (id_categoria, nom_subcategoria), -- Asegura que no haya subcategorías con el mismo nombre dentro de la misma categoría
    FOREIGN KEY (id_categoria) REFERENCES tab_Categorias(id_categoria) -- Relación con la tabla de categorías


);

-- Tabla: tab_Marcas
-- Almacena las marcas de los productos.
CREATE TABLE IF NOT EXISTS tab_Marcas
(
    id_marca                BIGINT NOT NULL, -- Identificador único de la marca
    nom_marca               VARCHAR(100) NOT NULL, -- Nombre de la marca
    estado_marca            BOOLEAN DEFAULT TRUE, -- Indica si la marca está activa o inactiva

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,
    PRIMARY KEY (id_marca),
    UNIQUE (nom_marca)
);

-- Tabla: tab_Proveedor
-- Almacena la información de los proveedores de productos.
CREATE TABLE IF NOT EXISTS tab_Proveedor
(
    id_proveedor            BIGINT NOT NULL, -- Identificador único del proveedor
    nom_proveedor           VARCHAR(100) NOT NULL, -- Nombre del proveedor
    tipo_documento          BIGINT NOT NULL , -- Tipo de documento del proveedor (ej. NIT, CC, RUC)
    num_documento           BIGINT NOT NULL, -- Número de documento del proveedor
    num_telefono            BIGINT NOT NULL, -- Número de contacto del proveedor
    correo                  VARCHAR(100) NOT NULL, -- Correo electrónico del proveedor
    direccion               VARCHAR(255), -- Dirección del proveedor (se especificó la longitud)
    fecha_creacion          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de creación del registro del proveedor
    estado                  BOOLEAN NOT NULL DEFAULT TRUE, -- Indica si el proveedor está activo o inactivo

       -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,
    PRIMARY KEY (id_proveedor),
    UNIQUE (num_documento),
    UNIQUE (correo)
);

-- Tabla: tab_Metodos_Pago
-- Almacena los métodos de pago disponibles en el sistema.
CREATE TABLE IF NOT EXISTS tab_Metodos_Pago
(
    id_metodo_pago          SMALLINT NOT NULL, -- Identificador único del método de pago
    nombre_metodo           VARCHAR(50) NOT NULL, -- Nombre del método de pago (ej: Tarjeta de crédito, PayPal)
    descripcion             TEXT NOT NULL, -- Descripción del método de pago

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_metodo_pago),
    UNIQUE (nombre_metodo)
);

CREATE TABLE IF NOT EXISTS tab_Usuario_Metodo_Pago
(
    id_usuario_metodo_pago  SMALLINT NOT NULL, -- Identificador único de la relación
    id_usuario              BIGINT NOT NULL, -- Clave foránea a tab_Usuarios
    id_metodo_pago          SMALLINT NOT NULL, -- Clave foránea a tab_Metodos_Pago
    num_tarjeta             VARCHAR(20), --  Últimos 4 dígitos o token
    fecha_vencimiento       DATE,        --  Fecha de vencimiento
    es_favorito             BOOLEAN DEFAULT FALSE, -- Indica si es el método de pago favorito

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_usuario_metodo_pago),
    UNIQUE (id_usuario, id_metodo_pago), 
    FOREIGN KEY (id_usuario) REFERENCES tab_Usuarios(id_usuario),
    FOREIGN KEY (id_metodo_pago) REFERENCES tab_Metodos_Pago(id_metodo_pago)
);

-- Tabla: tab_Promociones
-- Almacena las promociones disponibles.
CREATE TABLE IF NOT EXISTS tab_Promociones
(
    id_promocion            INT NOT NULL, -- Identificador único de la promoción
    descripcion             TEXT NOT NULL, -- Descripción detallada de la promoción
    descuento               NUMERIC(5, 2) NOT NULL, -- Porcentaje de descuento (0-100)
    fecha_inicio            TIMESTAMP NOT NULL, -- Fecha y hora de inicio de la promoción
    fecha_fin               TIMESTAMP NOT NULL, -- Fecha y hora de fin de la promoción

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,
    PRIMARY KEY (id_promocion),
    CHECK (descuento BETWEEN 0 AND 100), -- Porcentaje de descuento (0-100)
    CHECK (fecha_fin >= fecha_inicio) -- Asegura que la fecha de fin no sea anterior a la de inicio
);


-- Tabla: tab_Servicios
-- Almacena los servicios ofrecidos (ej. "Mantenimiento", "Reparación").
CREATE TABLE IF NOT EXISTS tab_Servicios
(
    id_servicio             BIGINT NOT NULL, -- Identificador único del servicio
    nom_servicio            VARCHAR(100) NOT NULL, -- Nombre del servicio
    descripcion             TEXT NOT NULL, -- Descripción del servicio
    precio_servicio         DECIMAL(10, 2) NOT NULL, -- Costo del servicio, no puede ser negativo
    duracion_estimada       VARCHAR(50) NOT NULL, -- Duración estimada del servicio (ej. "1 hora", "2-3 días")

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_servicio),
    UNIQUE (nom_servicio),
    CHECK (precio_servicio >= 0) -- Costo del servicio, no puede ser negativo
);

-- Tabla: tab_Contacto
-- Almacena los mensajes de contacto enviados por los usuarios.
CREATE TABLE IF NOT EXISTS tab_Contacto
(
    id_contacto             BIGINT NOT NULL, -- Identificador único del mensaje de contacto
    nombre_remitente        VARCHAR(100) NOT NULL, -- Nombre de la persona que envía el mensaje
    correo_remitente        VARCHAR(100) NOT NULL, -- Correo electrónico de la persona que envía el mensaje
    telefono_remitente      BIGINT NOT NULL, -- Número de teléfono de la persona que envía el mensaje
    asunto                  VARCHAR(200) NOT NULL, -- Asunto del mensaje
    mensaje                 TEXT NOT NULL, -- Contenido del mensaje
    fecha_envio             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de envío del mensaje
    estado                  VARCHAR(10) NOT NULL DEFAULT 'pendiente', -- Estado del mensaje

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_contacto),
    CHECK (estado IN ('pendiente', 'en proceso', 'resuelto', 'cerrado')) -- Estado del mensaje
);




-- Tabla: tab_Departamentos
-- Almacena la información de los departamentos/estados/provincias.
CREATE TABLE IF NOT EXISTS tab_Departamentos (
    id_departamento         INTEGER NOT NULL, -- Identificador único del departamento (PK)
    nombre_departamento     VARCHAR(100) NOT NULL, -- Nombre del departamento/estado/provincia
    codigo_iso              VARCHAR(10), -- Código ISO (opcional)

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_departamento),
    UNIQUE (nombre_departamento)
);

-- Tabla: tab_Ciudades (Modificada)
-- Almacena la información de ciudades.
CREATE TABLE IF NOT EXISTS tab_Ciudades (
    id_ciudad              INTEGER NOT NULL, -- Identificador único de la ciudad
    id_departamento        INTEGER NOT NULL, -- Clave foránea a tab_Departamentos
    nombre_ciudad          VARCHAR(100) NOT NULL, -- Nombre de la ciudad
    codigo_postal          VARCHAR(10), -- Código postal de la ciudad

       -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_ciudad),
    FOREIGN KEY (id_departamento) REFERENCES tab_Departamentos(id_departamento) -- La clave foránea apunta a la PK de tab_Departamentos
);

-- Tabla: tab_Direcciones_Envio
-- Almacena múltiples direcciones de envío para cada usuario.
CREATE TABLE IF NOT EXISTS tab_Direcciones_Envio
(
    id_direccion            BIGINT, -- Identificador único de la dirección de envío
    id_usuario              BIGINT NOT NULL, -- Identificador del usuario al que pertenece la dirección
    direccion_completa      VARCHAR(255) NOT NULL, -- Dirección postal completa
    id_ciudad               SMALLINT NOT NULL, -- Ciudad
    codigo_postal           VARCHAR(10) NOT NULL, -- Código postal
    es_predeterminada       BOOLEAN DEFAULT FALSE, -- Indica si es la dirección predeterminada del usuario

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_direccion),
    FOREIGN KEY (id_usuario) REFERENCES tab_Usuarios(id_usuario),
    FOREIGN KEY (id_ciudad) REFERENCES tab_Ciudades(id_ciudad)
);

-- Tabla: tab_Productos
-- Almacena la información de los productos (relojes, accesorios, etc.).
CREATE TABLE IF NOT EXISTS tab_Productos
(
    id_producto             BIGINT NOT NULL, -- Identificador único del producto
    id_marca               BIGINT NOT NULL, -- Clave foránea a tab_Marcas
    nom_producto            VARCHAR(255) NOT NULL, -- Nombre del producto
    descripcion             TEXT NOT NULL, -- Descripción detallada del producto
    precio                  DECIMAL(15,2) NOT NULL, -- Precio del producto
    id_categoria            INT NOT NULL, -- Clave foránea a tab_Categorias
    id_subcategoria         INT NOT NULL, -- Clave foránea a tab_Subcategorias
    stock                   SMALLINT NOT NULL, -- Cantidad de stock disponible
    url_imagen              VARCHAR(255), -- URL de la imagen del producto
    fecha_creacion          TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de creación del registro del producto
    estado                  BOOLEAN DEFAULT TRUE, -- Indica si el producto está disponible

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_producto),
    FOREIGN KEY (id_marca) REFERENCES tab_Marcas(id_marca),
    FOREIGN KEY (id_categoria, id_subcategoria) REFERENCES tab_Subcategorias (id_categoria, id_subcategoria),
    CHECK (precio >= 0), -- Precio del producto
    CHECK (stock >= 0) -- Cantidad de stock disponible
);
CREATE INDEX idx_producto_nombre ON tab_Productos (nom_producto); -- Índice para acelerar búsquedas de productos por nombre

-- Tabla: tab_Carrito
-- Almacena la cabecera de los carritos de compra de los usuarios.
CREATE TABLE IF NOT EXISTS tab_Carrito
(
    id_carrito              INT NOT NULL, -- Identificador único del carrito
    id_usuario              BIGINT NOT NULL, -- Clave foránea a tab_Usuarios, un usuario tiene un único carrito activo
    fecha_creacion          TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de creación del carrito
    fecha_ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de la última modificación del carrito
    estado_carrito          VARCHAR(50) DEFAULT 'activo', -- Estado del carrito

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_carrito),
    FOREIGN KEY (id_usuario) REFERENCES tab_Usuarios(id_usuario),
    CHECK (estado_carrito IN ('activo', 'abandonado', 'convertido_a_orden')) -- Estado del carrito
);

-- Tabla: tab_Carrito_Detalle
-- Almacena los productos individuales dentro de cada carrito de compra.
CREATE TABLE IF NOT EXISTS tab_Carrito_Detalle
(
    id_carrito_detalle      INT NOT NULL, -- Identificador único del detalle del carrito
    id_carrito              INT NOT NULL, -- Clave foránea a tab_Carrito
    id_producto             BIGINT NOT NULL, -- Clave foránea a tab_Productos
    cantidad                INT NOT NULL, -- Cantidad del producto en esta línea del carrito

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_carrito_detalle),
    UNIQUE (id_carrito, id_producto), -- Un producto solo puede aparecer una vez por carrito
    FOREIGN KEY (id_carrito) REFERENCES tab_Carrito(id_carrito),
    FOREIGN KEY (id_producto) REFERENCES tab_Productos(id_producto),
    CHECK (cantidad > 0) -- Cantidad del producto en esta línea del carrito
);

-- Tabla: tab_Orden
-- Almacena la cabecera de las órdenes de compra.
CREATE TABLE IF NOT EXISTS tab_Orden
(
    id_orden                INT NOT NULL, -- Identificador único de la orden
    id_usuario              BIGINT NOT NULL, -- Clave foránea a tab_Usuarios
    fecha_orden             TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora en que se realizó la orden
    estado_orden            VARCHAR(50) NOT NULL, -- Estado actual de la orden
    concepto                VARCHAR(100), -- Descripción o concepto general de la orden
    total_orden             DECIMAL(10, 2) NOT NULL, -- Costo total de la orden (se recomienda calcular a partir de Detalle_Orden y Orden_Servicios)

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_orden),
    FOREIGN KEY (id_usuario) REFERENCES tab_Usuarios (id_usuario),
    CHECK (estado_orden IN ('pendiente', 'en proceso', 'cancelado', 'completado')), -- Estado actual de la orden
    CHECK (total_orden >= 0) -- Costo total de la orden (se recomienda calcular a partir de Detalle_Orden y Orden_Servicios)
);
CREATE INDEX idx_orden_usuario ON tab_Orden (id_usuario); -- Índice para acelerar búsquedas de órdenes por usuario

-- Tabla: tab_Detalle_Orden
-- Almacena los productos individuales dentro de cada orden.
CREATE TABLE IF NOT EXISTS tab_Detalle_Orden
(
    id_detalle_orden        INT NOT NULL, -- Identificador único del detalle de la orden
    id_orden                INT NOT NULL, -- Clave foránea a tab_Orden
    id_producto             BIGINT NOT NULL, -- Clave foránea a tab_Productos
    cantidad                INT NOT NULL, -- Cantidad del producto en esta línea de la orden
    precio_unitario         DECIMAL(10, 2) NOT NULL, -- Precio del producto al momento de la compra
    id_promocion_aplicada   SMALLINT, -- Clave foránea a tab_Promociones (puede ser nulo si no hay promoción directa)

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_detalle_orden),
    FOREIGN KEY (id_orden) REFERENCES tab_Orden(id_orden),
    FOREIGN KEY (id_producto) REFERENCES tab_Productos(id_producto),
    FOREIGN KEY (id_promocion_aplicada) REFERENCES tab_Promociones(id_promocion),
    CHECK (cantidad > 0), -- Cantidad del producto en esta línea de la orden
    CHECK (precio_unitario >= 0) -- Precio del producto al momento de la compra
);

-- Tabla: tab_Orden_Servicios
-- Almacena los servicios individuales comprados como parte de una orden.
CREATE TABLE IF NOT EXISTS tab_Orden_Servicios
(
    id_orden_servicio       INT NOT NULL, -- Identificador único del detalle de servicio en la orden
    id_orden                INT NOT NULL, -- Clave foránea a tab_Orden
    id_servicio             BIGINT NOT NULL, -- Clave foránea a tab_Servicios
    cantidad                INT NOT NULL, -- Cantidad de veces que se aplica el servicio
    precio_servicio_aplicado DECIMAL(10, 2) NOT NULL, -- Precio del servicio al momento de la orden

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_orden_servicio),
    FOREIGN KEY (id_orden) REFERENCES tab_Orden(id_orden),
    FOREIGN KEY (id_servicio) REFERENCES tab_Servicios(id_servicio),
    CHECK (cantidad > 0), -- Cantidad de veces que se aplica el servicio
    CHECK (precio_servicio_aplicado >= 0) -- Precio del servicio al momento de la orden
);

-- Tabla: tab_Facturas
-- Almacena la información principal de cada factura generada por una orden.
CREATE TABLE IF NOT EXISTS tab_Facturas (
    id_factura      BIGINT NOT NULL, -- Clave primaria de la factura
    id_orden        INT NOT NULL,     -- ID de la orden asociada a la factura
    id_usuario      BIGINT NOT NULL,     -- ID del usuario (cliente) asociado a la factura
    fecha_emision   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de emisión de la factura
    total_factura   DECIMAL(10, 2) NOT NULL, -- Total de la factura
    estado_factura  VARCHAR(50) NOT NULL DEFAULT 'Emitida', -- Estado de la factura

       -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,
    PRIMARY KEY (id_factura),
    FOREIGN KEY (id_orden) REFERENCES tab_Orden(id_orden), -- Relación con la tabla de Órdenes
    FOREIGN KEY (id_usuario) REFERENCES tab_Usuarios(id_usuario) -- Relación con la tabla de Usuarios
);
CREATE INDEX idx_factura_orden ON tab_Facturas (id_orden);
CREATE INDEX idx_factura_usuario ON tab_Facturas (id_usuario);


-- Tabla: tab_Detalle_Factura
-- Contiene los ítems individuales de cada factura.
CREATE TABLE IF NOT EXISTS tab_Detalle_Factura (
    id_detalle_factura    BIGINT NOT NULL, -- Clave primaria del detalle de factura
    id_factura            BIGINT NOT NULL,              -- ID de la factura a la que pertenece este detalle
    id_producto           BIGINT NOT NULL,             -- ID del producto incluido en este detalle
    cantidad              SMALLINT NOT NULL,                -- Cantidad del producto
    precio_unitario       DECIMAL(10, 2) NOT NULL, -- Precio del producto al momento de la facturación
    subtotal_linea        DECIMAL(10, 2) NOT NULL, -- Subtotal para esta línea

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_detalle_factura),
    UNIQUE (id_factura, id_producto), -- Un producto solo puede aparecer una vez por factura
    FOREIGN KEY (id_factura) REFERENCES tab_Facturas(id_factura), -- Relación con la tabla de Facturas
    FOREIGN KEY (id_producto) REFERENCES tab_Productos(id_producto), -- Relación con la tabla de Productos
    CHECK (cantidad > 0) -- Cantidad del producto
);
CREATE INDEX idx_detalle_factura_factura ON tab_Detalle_Factura (id_factura);
CREATE INDEX idx_detalle_factura_producto ON tab_Detalle_Factura (id_producto);


-- Tabla: tab_Envios
-- Almacena la información de los envíos asociados a las órdenes.
CREATE TABLE IF NOT EXISTS tab_Envios
(
    id_envio                BIGINT NOT NULL, -- Identificador único del envío
    id_orden                INT NOT NULL, -- Clave foránea a tab_Orden, una orden tiene un único envío
    id_direccion_envio      BIGINT NOT NULL, -- Clave foránea a tab_Direcciones_Envio
    metodo_envio            VARCHAR(100) NOT NULL, -- Método de envío utilizado
    estado_envio            VARCHAR(50) NOT NULL, -- Estado actual del envío
    fecha_envio             TIMESTAMP NOT NULL, -- Fecha y hora en que se realizó el envío
    fecha_entrega_estimada  TIMESTAMP NOT NULL, -- Fecha y hora estimada de entrega
    costo_envio             DECIMAL(10,2) NOT NULL, -- Costo del envío

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_envio),
    UNIQUE (id_orden), -- Una orden tiene un único envío
    FOREIGN KEY (id_orden) REFERENCES tab_Orden (id_orden),
    FOREIGN KEY (id_direccion_envio) REFERENCES tab_Direcciones_Envio(id_direccion),
    CHECK (fecha_entrega_estimada >= fecha_envio), -- Asegura que la fecha estimada no sea anterior a la de envío
    CHECK (costo_envio >= 0), -- Costo del envío
    CHECK (estado_envio IN ('pendiente', 'en tránsito', 'entregado', 'cancelado')) -- Estado actual del envío
);

-- Tabla: tab_Opiniones
-- Almacena las calificaciones y reseñas de los usuarios sobre los productos.
CREATE TABLE IF NOT EXISTS tab_Opiniones
(
    id_opinion              INT NOT NULL, -- Identificador único de la opinión
    id_usuario              BIGINT NOT NULL, -- Identificador del usuario que realizó la opinión
    id_producto             BIGINT  NULL, -- Identificador del producto sobre el que se realizó la opinión
    calificacion            SMALLINT NOT NULL, -- Calificación del producto (1 a 5 estrellas)
    comentario              TEXT NOT NULL, -- Comentario adicional sobre el producto
    fecha_opinion           TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora en que se realizó la opinión

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_opinion),
    FOREIGN KEY (id_usuario) REFERENCES tab_Usuarios (id_usuario),
    FOREIGN KEY (id_producto) REFERENCES tab_Productos (id_producto),
    CHECK (calificacion BETWEEN 1 AND 5) -- Calificación del producto (1 a 5 estrellas)
);

-- Tabla: tab_Recepciones_Proveedor
-- Registra las entradas de productos desde los proveedores (movimientos de inventario).
CREATE TABLE IF NOT EXISTS tab_Recepciones_Proveedor
(
    id_recepcion            BIGINT NOT NULL, -- Identificador único de la recepción
    id_producto             BIGINT NOT NULL, -- Clave foránea a tab_Productos
    id_proveedor            BIGINT NOT NULL, -- Clave foránea a tab_Proveedor
    cantidad_recibida       BIGINT NOT NULL, -- Cantidad de productos recibidos
    fecha_recepcion         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de la recepción

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_recepcion),
    FOREIGN KEY (id_producto) REFERENCES tab_Productos(id_producto),
    FOREIGN KEY (id_proveedor) REFERENCES tab_Proveedor(id_proveedor),
    CHECK (cantidad_recibida > 0) -- Cantidad de productos recibidos
);

-- Tabla: tab_Pagos
-- Almacena los registros de pagos de las órdenes.
CREATE TABLE IF NOT EXISTS tab_Pagos
(
    id_pago                 INT NOT NULL, -- Identificador único del pago
    id_orden                INT NOT NULL, -- Clave foránea a tab_Orden, una orden tiene un único pago
    monto                   DECIMAL(10, 2) NOT NULL, -- Monto del pago
    id_metodo_pago          SMALLINT NOT NULL, -- Clave foránea a tab_Metodos_Pago
    estado_pago             VARCHAR(50) NOT NULL, -- Estado actual del pago
    fecha_pago              TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora en que se realizó el pago

       -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_pago),
    FOREIGN KEY (id_orden) REFERENCES tab_Orden(id_orden),
    FOREIGN KEY (id_metodo_pago) REFERENCES tab_Metodos_Pago(id_metodo_pago),
    CHECK (monto >= 0), -- Monto del pago
    CHECK (estado_pago IN ('pendiente', 'completado', 'fallido', 'reembolsado')) -- Estado actual del pago
);

-- Tabla: tab_Productos_Promociones
-- Tabla de relación muchos a muchos entre productos y promociones.
CREATE TABLE IF NOT EXISTS tab_Productos_Promociones
(
    id_producto_promocion   BIGINT NOT NULL, -- Identificador único de la relación producto-promoción
    id_producto             BIGINT NOT NULL, -- Identificador del producto asociado a la promoción
    id_promocion            INT NOT NULL, -- Identificador de la promoción aplicada al producto

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_producto_promocion),
    UNIQUE (id_producto, id_promocion), -- Asegura que un producto no tenga la misma promoción aplicada varias veces
    FOREIGN KEY (id_producto) REFERENCES tab_Productos(id_producto),
    FOREIGN KEY (id_promocion) REFERENCES tab_Promociones(id_promocion)
);

-- Tabla: tab_Reservas
-- Almacena las reservas de servicios realizadas por los usuarios.
CREATE TABLE IF NOT EXISTS tab_Reservas
(
    id_reserva              BIGINT NOT NULL, -- Identificador único de la reserva
    id_usuario              BIGINT NOT NULL, -- Identificador del usuario que realizó la reserva
    id_servicio             BIGINT NOT NULL, -- Identificador del servicio reservado
    fecha_reserva           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Fecha real de creación
    fecha_preferida         DATE, -- (NUEVO) Fecha en la que el cliente desea el servicio
    notas_cliente           TEXT, -- (NUEVO) Notas adicionales del cliente
    prioridad               VARCHAR(20) DEFAULT 'normal', -- (NUEVO) Prioridad (normal, alta)
    estado_reserva          VARCHAR(50) DEFAULT 'pendiente', -- Estado (pendiente, confirmada, etc.)

    -- Columnas de auditoría
    usr_insert VARCHAR(100),
    fec_insert TIMESTAMP,
    usr_update VARCHAR(100),
    fec_update TIMESTAMP,
    usr_delete VARCHAR(100),
    fec_delete TIMESTAMP,

    PRIMARY KEY (id_reserva),
    FOREIGN KEY (id_usuario) REFERENCES tab_Usuarios(id_usuario),
    FOREIGN KEY (id_servicio) REFERENCES tab_Servicios(id_servicio),
    CHECK (estado_reserva IN ('pendiente', 'confirmada', 'cancelada', 'completada'))
);

-- ==========================================
-- Tabla: tab_Empleados
-- Almacena la información de los empleados de la empresa.
-- ==========================================
CREATE TABLE IF NOT EXISTS tab_Empleados (
    id_empleado          BIGINT NOT NULL,
    num_documento        VARCHAR(20),
    nom_empleado         VARCHAR(100) NOT NULL,
    apellido_empleado    VARCHAR(100) NOT NULL,
    correo               VARCHAR(100),
    telefono             VARCHAR(20),
    puesto               VARCHAR(100),
    fecha_contratacion   DATE DEFAULT CURRENT_DATE,
    
    -- Columnas de auditoría
    usr_insert           VARCHAR(100),
    fec_insert           TIMESTAMP,
    usr_update           VARCHAR(100),
    fec_update           TIMESTAMP,
    usr_delete           VARCHAR(100),
    fec_delete           TIMESTAMP,
    
    PRIMARY KEY (id_empleado),
    UNIQUE (num_documento),
    UNIQUE (correo)
);

-- ==========================================
-- Tabla: tab_Eventos
-- Almacena eventos, promociones especiales o hitos del negocio.
-- ==========================================
CREATE TABLE IF NOT EXISTS tab_Eventos (
    id_evento            BIGINT NOT NULL,
    titulo               VARCHAR(200) NOT NULL,
    descripcion          TEXT,
    fecha_inicio         TIMESTAMP NOT NULL,
    fecha_fin            TIMESTAMP NOT NULL,
    
    -- Columnas de auditoría
    usr_insert           VARCHAR(100),
    fec_insert           TIMESTAMP,
    usr_update           VARCHAR(100),
    fec_update           TIMESTAMP,
    usr_delete           VARCHAR(100),
    fec_delete           TIMESTAMP,
    
    PRIMARY KEY (id_evento),
    CHECK (fecha_fin >= fecha_inicio)
);