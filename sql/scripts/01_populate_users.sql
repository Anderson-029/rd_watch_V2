-- POBLACIÓN DE USUARIOS PERSONALIZADOS - RD WATCH V2
-- Patrón de correo: cliente[N]@email.com
-- Patrón de contraseña: Cliente123![N]

-- 1. Limpieza y Administrador
DELETE FROM tab_Usuarios;

INSERT INTO tab_Usuarios (id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, contra, rol, direccion_principal, activo, fec_insert, usr_insert)
VALUES (1, 'Administrador RD Watch', 'admin@rdwatch.com', 3115460069, '$2y$10$/UAmqdamNyQ7KUJY0pxI1u5R8Od/nA8c72hHd.eWnMfBJlsxiQv/q', 'admin', 'Calle 34 #18-40 local 107', TRUE, NOW(), 'system');

-- 2. Clientes con datos únicos
INSERT INTO tab_Usuarios (id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, contra, rol, direccion_principal, activo, fec_insert, usr_insert) VALUES
(2, 'Cliente 2', 'cliente2@email.com', 3001234502, '$2y$10$5cEjwPpXJyDoPvguJN968OIPorz.uSQf/8UHrcYG9dl65O5lEJawq', 'cliente', 'Dirección Cliente 2, Bogotá', TRUE, NOW(), 'system'),
(3, 'Cliente 3', 'cliente3@email.com', 3001234503, '$2y$10$yTN6P7EgBDRdqxxL0crxzOVS7kofAmRhBfNgkztjBVS.7mFhYLLVi', 'cliente', 'Dirección Cliente 3, Bogotá', TRUE, NOW(), 'system'),
(4, 'Cliente 4', 'cliente4@email.com', 3001234504, '$2y$10$QThxWaSGAGgcDmQjXSXstuoXojPL0dRF.uABhjml2ZS7XXqdS24Xu', 'cliente', 'Dirección Cliente 4, Bogotá', TRUE, NOW(), 'system'),
(5, 'Cliente 5', 'cliente5@email.com', 3001234505, '$2y$10$NF6sjJIkNjgnZz98k24npOQgF6uAYW5.yDh2D7Mn/hE/Xa0XPdQ0.', 'cliente', 'Dirección Cliente 5, Bogotá', TRUE, NOW(), 'system'),
(6, 'Cliente 6', 'cliente6@email.com', 3001234506, '$2y$10$/LdC8qQmo03mbaw2rIHQTe2MZWMxpKLLceKwIE4IUQu0iiU2YyFwO', 'cliente', 'Dirección Cliente 6, Bogotá', TRUE, NOW(), 'system'),
(7, 'Cliente 7', 'cliente7@email.com', 3001234507, '$2y$10$zl78hRlqQo0.J.TOM7GQOeQPQMdlMj5KgmsDI178YIeiGEjE1kKVq', 'cliente', 'Dirección Cliente 7, Bogotá', TRUE, NOW(), 'system'),
(8, 'Cliente 8', 'cliente8@email.com', 3001234508, '$2y$10$iImIauklqEaQpCGqHVGd4ek4sds7PSvUmfx0zpcpmrFjmf01peEte', 'cliente', 'Dirección Cliente 8, Bogotá', TRUE, NOW(), 'system'),
(9, 'Cliente 9', 'cliente9@email.com', 3001234509, '$2y$10$g4DVn0fL.GpCnaZEIKWIheXXDTjJbMOW9agmPmtdkCqTpiyfZbABm', 'cliente', 'Dirección Cliente 9, Bogotá', TRUE, NOW(), 'system'),
(10, 'Cliente 10', 'cliente10@email.com', 3001234510, '$2y$10$44GuMg.MqoNK8T5fYS/PO./qontWoflhxx/R7l2uIR0FSgFuCP6km', 'cliente', 'Dirección Cliente 10, Bogotá', TRUE, NOW(), 'system'),
(11, 'Cliente 11', 'cliente11@email.com', 3001234511, '$2y$10$XElRJdljWChg6nFdW/SYUeUrmJTz.HLo83WnWHXS6D4RxHTVFLIQu', 'cliente', 'Dirección Cliente 11, Bogotá', TRUE, NOW(), 'system'),
(12, 'Cliente 12', 'cliente12@email.com', 3001234512, '$2y$10$E4EZ7VWzsxuRa4p08ynGgO/jRjsSjDYB.87VkhSG1/xd5pFZrfZHm', 'cliente', 'Dirección Cliente 12, Bogotá', TRUE, NOW(), 'system'),
(13, 'Cliente 13', 'cliente13@email.com', 3001234513, '$2y$10$5qE/OAQ/78k6sN9zZwV2DuISbwwDt4oNJ9fDMSP3xSLgeWtTXlabK', 'cliente', 'Dirección Cliente 13, Bogotá', TRUE, NOW(), 'system'),
(14, 'Cliente 14', 'cliente14@email.com', 3001234514, '$2y$10$EqsRgGmUuUW3SXWALiggy.fPruKB2IK8Ecf76dUJWvzPEVd6J9M6K', 'cliente', 'Dirección Cliente 14, Bogotá', TRUE, NOW(), 'system'),
(15, 'Cliente 15', 'cliente15@email.com', 3001234515, '$2y$10$p183vqdeqiQoPux.BHCwAOkOthOfMDpw3hWpwyu..tX7eosNu91ya', 'cliente', 'Dirección Cliente 15, Bogotá', TRUE, NOW(), 'system'),
(16, 'Cliente 16', 'cliente16@email.com', 3001234516, '$2y$10$bomjam2fx.UYfJz4Yig1KeaM6w4BoqK9vUPpyRCz5h2jmXRB2pA0y', 'cliente', 'Dirección Cliente 16, Bogotá', TRUE, NOW(), 'system'),
(17, 'Cliente 17', 'cliente17@email.com', 3001234517, '$2y$10$kREHNmPg3EhU1FGbpTzY/OHKcvAKKWuMtbEzIw7KAYyvCr0cppY6G', 'cliente', 'Dirección Cliente 17, Bogotá', TRUE, NOW(), 'system'),
(18, 'Cliente 18', 'cliente18@email.com', 3001234518, '$2y$10$AAtbwcYKVj/vLH6MKuPyXuNtNUnJCxVJ0X2Y23zWxSWF.lAdzcrnu', 'cliente', 'Dirección Cliente 18, Bogotá', TRUE, NOW(), 'system'),
(19, 'Cliente 19', 'cliente19@email.com', 3001234519, '$2y$10$ANUeKn4pdJItjd071EW3xOFUoqWIzgU2wXnRMrAGaLdvQ4niEbYe.', 'cliente', 'Dirección Cliente 19, Bogotá', TRUE, NOW(), 'system'),
(20, 'Cliente 20', 'cliente20@email.com', 3001234520, '$2y$10$1unDVvqKc2c6YhpSJyAK6uttB/R/DI/eP1WWLBtXYox9zn1FGXsXG', 'cliente', 'Dirección Cliente 20, Bogotá', TRUE, NOW(), 'system'),
(21, 'Cliente 21', 'cliente21@email.com', 3001234521, '$2y$10$oPjcrtYBVV8B3vMoPlHoseh0CSSGZkbwgALCuHco8chwZB7OWODRy', 'cliente', 'Dirección Cliente 21, Bogotá', TRUE, NOW(), 'system'),
(22, 'Cliente 22', 'cliente22@email.com', 3001234522, '$2y$10$X/JyG9bf82FsLArbFXflkeO8/ZtwqAfdNeQX9CotQzplmIL9GAJzq', 'cliente', 'Dirección Cliente 22, Bogotá', TRUE, NOW(), 'system'),
(23, 'Cliente 23', 'cliente23@email.com', 3001234523, '$2y$10$KwMI1Ml7NRrVNu4o6/7eneP0CIePncg4Agc3gnDy6r5LRJZHoMZpK', 'cliente', 'Dirección Cliente 23, Bogotá', TRUE, NOW(), 'system'),
(24, 'Cliente 24', 'cliente24@email.com', 3001234524, '$2y$10$NeDog75QIUQUQHJqjhrKqu/WQ9I5F.TsnMPA9wNEtPHCyQdvasUwK', 'cliente', 'Dirección Cliente 24, Bogotá', TRUE, NOW(), 'system'),
(25, 'Cliente 25', 'cliente25@email.com', 3001234525, '$2y$10$GcKsxEk8hn3fJJpR1O2OOuhPgEr9NG3g0rqj5FhGlvzfMOABwF/0K', 'cliente', 'Dirección Cliente 25, Bogotá', TRUE, NOW(), 'system'),
(26, 'Cliente 26', 'cliente26@email.com', 3001234526, '$2y$10$VjyBA.mdTbe9vmESY5n5euTvA0BOeV27xULzztN2lfuxBCHvDC.Lm', 'cliente', 'Dirección Cliente 26, Bogotá', TRUE, NOW(), 'system'),
(27, 'Cliente 27', 'cliente27@email.com', 3001234527, '$2y$10$snhtPvmgDA1G51Nmzfx/gOTGEuaqC8kWrIlBdmJ7iWBCcBQq8ZLE2', 'cliente', 'Dirección Cliente 27, Bogotá', TRUE, NOW(), 'system'),
(28, 'Cliente 28', 'cliente28@email.com', 3001234528, '$2y$10$u86DJFnsGH511Jdfhl09YuWY3Y3387e2UfsqEQQQqn9nCyg17AJ32', 'cliente', 'Dirección Cliente 28, Bogotá', TRUE, NOW(), 'system'),
(29, 'Cliente 29', 'cliente29@email.com', 3001234529, '$2y$10$ywtZDVqQDkmp8b7U7yRXHOAb5cXb/5BXbjOT61ONEBx6bKI3gAdQy', 'cliente', 'Dirección Cliente 29, Bogotá', TRUE, NOW(), 'system'),
(30, 'Cliente 30', 'cliente30@email.com', 3001234530, '$2y$10$ziRG8BOwzZbJX49jSKyleeb/aQGAIOZX7vO51SJZiHA2GXPzQg5b6', 'cliente', 'Dirección Cliente 30, Bogotá', TRUE, NOW(), 'system');
