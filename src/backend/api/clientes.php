<?php
/**
 * API DE ADMINISTRACIÓN: GESTIÓN DE CLIENTES
 * ---------------------------------------------------------
 * Este archivo permite a los administradores visualizar la 
 * lista completa de clientes registrados en la plataforma.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        /**
         * LISTAR CLIENTES
         * Filtra únicamente los usuarios cuyo rol es 'cliente', 
         * omitiendo a otros administradores.
         */
        $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, activo, fecha_registro FROM tab_Usuarios WHERE rol = 'cliente' ORDER BY id_usuario DESC");
        $stmt->execute();
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'clientes' => $clientes]);
    } else {
        // Otros métodos (POST, PUT, DELETE) para clientes podrían implementarse aquí.
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}
