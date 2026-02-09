<?php
/**
 * API DE ADMINISTRACIÓN: GESTIÓN DE CLIENTES
 * ---------------------------------------------------------
 * Propósito: Facilita al administrador la visualización y auditoría de la base 
 * de usuarios con rol de 'cliente'. Permite ver datos de contacto y estado de cuenta.
 * 
 * Lógica:
 * - Filtra por rol 'cliente' para garantizar privacidad de cuentas administrativas.
 * - Ordena por ID descendente para mostrar los registros más recientes primero.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de integridad de la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de conexión: El motor de base de datos no está disponible']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    /**
     * ==========================================
     * 🔍 LISTAR CLIENTES (GET)
     * ==========================================
     */
    if ($method === 'GET') {
        /**
         * Lógica de consulta: 
         * Solo recuperamos columnas seguras (evitamos passwords o datos sensibles).
         */
        $sql = "SELECT id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, activo, fecha_registro 
                FROM tab_Usuarios 
                WHERE rol = 'cliente' 
                ORDER BY id_usuario DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'count' => count($clientes),
            'clientes' => $clientes
        ]);
    } else {
        /**
         * Manejo de otros verbos HTTP:
         * Reservado para futuras implementaciones de edición masiva o bloqueo de clientes.
         */
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método HTTP denegado para la gestión de clientes']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Falla crítica de base de datos en clientes: ' . $e->getMessage()]);
}
