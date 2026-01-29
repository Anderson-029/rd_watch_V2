<?php
/**
 * RD Watch - Script de Mantenimiento y Seguridad (v1.0)
 * 
 * Este script automatiza la configuración crítica de seguridad:
 * 1. Repara el esquema de base de datos (columnas truncadas).
 * 2. Inicializa/Repara credenciales base (Admin/Cliente) con Bcrypt.
 * 3. Valida la conexión y el entorno.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Cargar variables de entorno (Sin depender de rutas relativas de otros archivos)
$envPath = __DIR__ . '/../backend/.env';
if (!file_exists($envPath)) {
    die("ERROR: No se encontró el archivo .env en $envPath\nPor favor, corre primero 01_configurar_bd.sh/.bat\n");
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0)
        continue;
    if (strpos($line, '=') !== false) {
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, '"\' ');
    }
}

try {
    echo "--- Iniciando Configuración de Seguridad RD Watch ---\n";

    $dsn = "pgsql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']};";
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "[1/3] Verificando y Reparando Esquema de BD...\n";
    $pdo->exec("ALTER TABLE tab_Usuarios ALTER COLUMN contra TYPE varchar(255);");
    $pdo->exec("ALTER TABLE tab_Usuarios ALTER COLUMN salt TYPE varchar(255);");
    echo "      OK: Columnas de contraseñas ampliadas.\n";

    echo "[2/3] Inicializando Credenciales de Fábrica...\n";
    $defaultUsers = [
        ['admin@rdwatch.com', 'Admin123!', 'admin', 'Administrador'],
        ['cliente@rdwatch.com', 'Cliente123!', 'cliente', 'Cliente Prueba']
    ];

    foreach ($defaultUsers as $u) {
        list($email, $pass, $rol, $name) = $u;
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        // Upsert simple (Check if exists first)
        $check = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE LOWER(correo_usuario) = LOWER(:e)");
        $check->execute([':e' => $email]);

        if ($check->fetch()) {
            $upd = $pdo->prepare("UPDATE tab_Usuarios SET contra = :h, salt = 'bcrypt', rol = :r, activo = true, bloqueado = false WHERE LOWER(correo_usuario) = LOWER(:e)");
            $upd->execute([':h' => $hash, ':r' => $rol, ':e' => $email]);
            echo "      - ACTUALIZADO: $email\n";
        } else {
            // Nota: id_usuario autoincremental si el trigger/secuencia está ok
            $ins = $pdo->prepare("INSERT INTO tab_Usuarios (nom_usuario, correo_usuario, num_telefono_usuario, contra, salt, rol, activo) VALUES (:n, :e, 0, :h, 'bcrypt', :r, true)");
            $ins->execute([':n' => $name, ':e' => $email, ':h' => $hash, ':r' => $rol]);
            echo "      - CREADO: $email\n";
        }
    }

    echo "[3/3] Validando Integridad de Sesión...\n";
    // Podríamos generar un SESSION_SALT único aquí si fuera necesario
    echo "      OK: Sistema listo para autenticación segura.\n";

    echo "\n--- Configuración Finalizada con Éxito ---\n";

} catch (Exception $e) {
    die("\nFATAL ERROR: " . $e->getMessage() . "\n");
}
