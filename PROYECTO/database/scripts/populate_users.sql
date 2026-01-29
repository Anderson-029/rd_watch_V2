-- Create Admin User
SELECT fun_registrar_usuario(
    'Administrador',
    'admin@rdwatch.com',
    3115460069,
    'Admin123!',
    'Calle 34 #18-40 local 107'
);

-- Change role to admin
UPDATE tab_Usuarios 
SET rol = 'admin'
WHERE correo_usuario = 'admin@rdwatch.com';

-- Create Client User
SELECT fun_registrar_usuario(
    'Cliente Prueba',
    'cliente@rdwatch.com',
    3001234567,
    'Cliente123!',
    'Carrera 10 #20-30'
);
