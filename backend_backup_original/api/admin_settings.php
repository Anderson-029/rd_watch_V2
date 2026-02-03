<?php
// backend/api/admin_settings.php
require_once('../security_headers.php');
header('Content-Type: application/json');
include_once('../config.php');

// Validar sesión admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Ruta del archivo JSON para configuración de la tienda
$configFile = __DIR__ . '/../data/store_config.json';
// Asegurar que directorio existe
if (!is_dir(dirname($configFile))) {
    mkdir(dirname($configFile), 0755, true);
}

// Valores por defecto
$defaultStoreConfig = ['nombre' => 'RD-Watch', 'moneda' => 'USD'];

try {
    if ($method === 'GET') {
        // Leer config de archivo
        $storeConfig = $defaultStoreConfig;
        if (file_exists($configFile)) {
            $json = json_decode(file_get_contents($configFile), true);
            if (is_array($json)) {
                $storeConfig = array_merge($defaultStoreConfig, $json);
            }
        }

        // Obtener datos del admin actual desde la BD
        $stmt = $pdo->prepare("SELECT nom_usuario FROM tab_Usuarios WHERE id_usuario = :uid");
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'store' => $storeConfig,
            'admin' => [
                'usuario' => $user['nom_usuario'] ?? 'admin'
            ]
        ]);
    } 
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if ($action === 'update_store') {
            // Guardar configuración en archivo JSON
            $newConfig = [
                'nombre' => trim($data['nombre'] ?? 'RD-Watch'),
                'moneda' => trim($data['moneda'] ?? 'USD')
            ];
            file_put_contents($configFile, json_encode($newConfig));
            echo json_encode(['ok' => true, 'msg' => 'Configuración de tienda guardada']);
        } 
        elseif ($action === 'update_admin') {
            $currentPass = $data['current_pass'] ?? '';
            $newUser     = trim($data['usuario'] ?? '');
            $newPass     = $data['new_pass'] ?? '';

            if (empty($currentPass) || empty($newUser)) {
                echo json_encode(['ok' => false, 'msg' => 'Faltan datos requeridos (contraseña actual o usuario)']);
                exit;
            }

            // 1. Verificar contraseña actual en la BD
            // Usamos la función de login existente o una consulta directa si la contraseña está hasheada
            // Asumiendo que fun_login_usuario verifica hash, pero aquí ya estamos logueados.
            // Lo ideal es verificar el hash directamente.
            
            // Buscar hash actual
            $stmt = $pdo->prepare("SELECT contrasena FROM tab_Usuarios WHERE id_usuario = :uid");
            $stmt->execute([':uid' => $_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($currentPass, $row['contrasena'])) {
                echo json_encode(['ok' => false, 'msg' => 'La contraseña actual es incorrecta']);
                exit;
            }

            // 2. Preparar actualización
            // Si hay newPass, hashearla. Si no, dejar la misma.
            $sql = "UPDATE tab_Usuarios SET nom_usuario = :nom";
            $params = [':nom' => $newUser, ':uid' => $_SESSION['user_id']];
            
            if (!empty($newPass)) {
                $sql .= ", contrasena = :pwd";
                $params[':pwd'] = password_hash($newPass, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id_usuario = :uid";
            
            $updateStmt = $pdo->prepare($sql);
            if ($updateStmt->execute($params)) {
                // Actualizar sesión si cambió el nombre
                $_SESSION['user_name'] = $newUser;
                echo json_encode(['ok' => true, 'msg' => 'Datos de administrador actualizados']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar base de datos']);
            }
        }
    }
} catch (Exception $e) {
    error_log('Error en admin_settings: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor']);
}
