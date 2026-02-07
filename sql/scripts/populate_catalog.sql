-- POBLACIÓN DE CATÁLOGO MAESTRO - RELOJERÍA DURÁN V2
-- Propósito: Dar vida al comercio con 25 productos premium, marcas y categorías detalladas.

-- 2. MARCAS
INSERT INTO tab_Marcas (id_marca, nom_marca, usr_insert, fec_insert) VALUES
(1, 'Rolex', 'system', NOW()),
(2, 'Omega', 'system', NOW()),
(3, 'CASSIO', 'system', NOW()),
(4, 'Tissot', 'system', NOW()),
(5, 'Citizen', 'system', NOW()),
(6, 'Seiko', 'system', NOW()),
(7, 'Bergeon', 'system', NOW())
ON CONFLICT (id_marca) DO NOTHING;

-- 3. CATEGORÍAS
INSERT INTO tab_Categorias (id_categoria, nom_categoria, descripcion_categoria, usr_insert, fec_insert) VALUES
(1, 'Relojes de Lujo', 'Cronometros de alta gama y prestigio internacional.', 'system', NOW()),
(2, 'Relojes Deportivos', 'Resistencia y funcionalidad para actividades extremas.', 'system', NOW()),
(3, 'Herramientas Profesionales', 'Instrumental técnico para maestros relojeros.', 'system', NOW()),
(4, 'Repuestos Originales', 'Componentes genuinos para mantenimiento y restauración.', 'system', NOW())
ON CONFLICT (id_categoria) DO UPDATE SET descripcion_categoria = EXCLUDED.descripcion_categoria;

-- 4. SUBCATEGORÍAS
INSERT INTO tab_Subcategorias (id_categoria, id_subcategoria, nom_subcategoria, usr_insert, fec_insert) VALUES
(1, 1, 'Automáticos', 'system', NOW()),
(1, 2, 'Cronógrafos', 'system', NOW()),
(2, 1, 'Resistentes al Agua', 'system', NOW()),
(2, 2, 'Smartwatches', 'system', NOW()),
(3, 1, 'Instrumentos de Precisión', 'system', NOW()),
(3, 2, 'Kits de Limpieza', 'system', NOW()),
(4, 1, 'Correas y Brazaletes', 'system', NOW()),
(4, 2, 'Cristales y Biseles', 'system', NOW())
ON CONFLICT (id_categoria, id_subcategoria) DO NOTHING;

-- 5. PRODUCTOS (25 ÍTEMS)
INSERT INTO tab_Productos (id_producto, id_marca, nom_producto, descripcion, precio, id_categoria, id_subcategoria, stock, url_imagen, usr_insert, fec_insert) VALUES
-- Relojes de Lujo (Automáticos)
(1, 1, 'Rolex Submariner Date', 'El reloj de buceo de referencia. Acero Oystersteel con bisel Cerachrom negro.', 68500000, 1, 1, 5, 'https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(2, 1, 'Rolex Daytona Cosmograph', 'Diseñado para los pilotos de carreras de resistencia. Oro de 18 quilates.', 145000000, 1, 2, 2, 'https://images.unsplash.com/photo-1614164185128-e4ec99c436d7?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(3, 2, 'Omega Speedmaster Moonwatch', 'El primer reloj usado en la luna. Calibre 3861 manual.', 34200000, 1, 2, 8, 'https://images.unsplash.com/photo-1612817159949-195b6eb9e31a?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(4, 2, 'Omega Seamaster Diver 300M', 'Elegancia y rendimiento bajo el agua. Esfera de cerámica azul.', 28900000, 1, 1, 10, 'https://images.unsplash.com/photo-1523170335258-f5ed11844a49?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(5, 4, 'Tissot Le Locle Powermatic 80', 'Clásico sofisticado con reserva de marcha de 80 horas.', 3450000, 1, 1, 15, 'https://images.unsplash.com/photo-1619134177114-f5da772156be?auto=format&fit=crop&q=80&w=800', 'system', NOW()),

-- Relojes Deportivos
(6, 3, 'CASSIO G-Shock Mudmaster', 'Resistencia extrema al lodo y vibraciones. Triple sensor.', 1850000, 2, 1, 20, 'https://images.unsplash.com/photo-1547996160-81dfa63595aa?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(7, 3, 'CASSIO Retro Gold Edition', 'El clásico digital en acabado dorado. Un icono de los 80.', 320000, 2, 1, 50, 'https://images.unsplash.com/photo-1622434641406-a158123450f9?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(8, 6, 'Seiko 5 Sports Orange', 'Reloj automático robusto y dinámico para el día a día.', 1650000, 2, 1, 12, 'https://images.unsplash.com/photo-1612502169027-5a3d92c7fac7?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(9, 6, 'Seiko Prospex "Turtle"', 'Reloj de buceo profesional con forma de caja icónica.', 2450000, 2, 1, 9, 'https://images.unsplash.com/photo-1614242233320-7f28ed98801d?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(10, 5, 'Citizen Eco-Drive Promaster', 'Carga solar infinita para misiones submarinas.', 1980000, 2, 1, 14, 'https://images.unsplash.com/photo-1623932230865-ec1ba0884d59?auto=format&fit=crop&q=80&w=800', 'system', NOW()),

-- Herramientas
(11, 7, 'Set Destornilladores Bergeon 30081', 'Kit profesional de 10 piezas de alta precisión.', 1150000, 3, 1, 5, 'https://images.unsplash.com/photo-1530124560676-4ce69299d261?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(12, 7, 'Prensa para Cerrar Cajas Bergeon', 'Sistema de presión controlada para fondos a presión.', 2300000, 3, 1, 3, 'https://images.unsplash.com/photo-1581092921461-7d2a9390779d?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(13, 7, 'Kit de Limpieza Ultrasonido', 'Mantén tus brazaletes y cajas relucientes.', 450000, 3, 2, 10, 'https://images.unsplash.com/photo-1533038590840-1cde6e668a91?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(14, 7, 'Extractor de Pasadores Pro', 'Herramienta esencial para cambiar correas sin rayones.', 180000, 3, 1, 25, 'https://images.unsplash.com/photo-1579446565308-427218a244fe?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(15, 7, 'Lupa de Relojero 10x', 'Visión detallada de calibres y grabados.', 85000, 3, 1, 40, 'https://images.unsplash.com/photo-1618035222100-81f3ad437341?auto=format&fit=crop&q=80&w=800', 'system', NOW()),

-- Repuestos
(16, 1, 'Brazalete Oyster Rolex Acero', 'Repuesto original para modelos Submariner y Datejust.', 12500000, 4, 1, 2, 'https://images.unsplash.com/photo-1549448530-50d4f1073177?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(17, 2, 'Correa de Caucho Omega Azul', 'Para modelos Seamaster. Incluye hebilla grabada.', 1850000, 4, 1, 6, 'https://images.unsplash.com/photo-1612817159676-e1e550c609e2?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(18, 1, 'Cristal de Zafiro para Rolex GMT', 'Resistencia total a rayones con lupa Cyclops.', 3450000, 4, 2, 4, 'https://images.unsplash.com/photo-1618035222044-67ad68b8e053?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(19, 6, 'Maquinaria Seiko NH35A', 'Movimiento automático fiable para reparaciones o modding.', 320000, 4, 2, 30, 'https://images.unsplash.com/photo-1618035222100-2dca84310e52?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(20, 3, 'Correa Resina G-Shock Negra', 'Original para series 5600 y 6900.', 120000, 4, 1, 100, 'https://images.unsplash.com/photo-1542496658-e33a6d0d50f6?auto=format&fit=crop&q=80&w=800', 'system', NOW()),

-- Relojes Variados (Para completar 25)
(21, 5, 'Citizen Promaster Skyhawk', 'Sincronización atómica por radio control.', 3250000, 2, 1, 7, 'https://images.unsplash.com/photo-1612502169027-4c407f240902?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(22, 4, 'Tissot PRX Powermatic 80', 'El renacimiento del icono de los 70 en azul eléctrico.', 3850000, 1, 1, 12, 'https://images.unsplash.com/photo-1619134177114-f5da772156be?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(23, 2, 'Omega Constellation Pie Pan', 'Reloj vintage restaurado en nuestra joyería. Un clásico.', 8500000, 1, 1, 1, 'https://images.unsplash.com/photo-1612817159949-195b6eb9e31a?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(24, 6, 'Seiko Presage Cocktail Time', 'Esfera con texturas inspiradas en la alta coctelería.', 2150000, 1, 1, 8, 'https://images.unsplash.com/photo-1612502169027-5a3d92c7fac7?auto=format&fit=crop&q=80&w=800', 'system', NOW()),
(25, 3, 'CASSIO Edifice Chronograph', 'Velocidad e inteligencia con conexión Bluetooth.', 850000, 2, 1, 20, 'https://images.unsplash.com/photo-1614242233320-7f28ed98801d?auto=format&fit=crop&q=80&w=800', 'system', NOW())
ON CONFLICT (id_producto) DO UPDATE SET
    nom_producto = EXCLUDED.nom_producto,
    descripcion = EXCLUDED.descripcion,
    precio = EXCLUDED.precio,
    stock = EXCLUDED.stock,
    url_imagen = EXCLUDED.url_imagen;
