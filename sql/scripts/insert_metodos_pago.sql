-- Semilla de datos para Métodos de Pago
-- Relojería Durán V2

INSERT INTO tab_Metodos_Pago (id_metodo_pago, nombre_metodo, descripcion)
VALUES (1, 'Consignación / Transferencia', 'Instrucciones de pago por Bancolombia, Nequi o Daviplata mostradas en el checkout.')
ON CONFLICT (id_metodo_pago) DO NOTHING;
