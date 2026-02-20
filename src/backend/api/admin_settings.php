<?php
/**
 * ============================================================
 * API: AJUSTES GLOBALES DEL SITIO (admin_settings.php)
 * ============================================================
 * ENDPOINTS:
 *   GET  /api/admin_settings.php → Obtener configuración
 *   POST /api/admin_settings.php → Actualizar configuración
 *   PUT  /api/admin_settings.php → Actualizar configuración
 *
 * PROPÓSITO:
 * Centraliza los parámetros generales de la plataforma:
 * nombre de marca, moneda, datos del admin.
 *
 * ESTADO DE IMPLEMENTACIÓN:
 * Actualmente las funciones retornan datos estáticos (mock)
 * ya que no existe tab_Configuracion. Cuando se cree,
 * solo se modificará la función SQL, PHP no cambiará.
 *
 * ACCESO: SOLO ADMIN (requireRole('admin'))
 *
 * FUNCIONES POSTGRESQL QUE USA:
 * - fn_admin_get_settings()           → JSON {store, admin}
 * - fn_admin_get_stats()              → JSON {totales}
 * - fn_admin_update_settings(data)    → JSON {ok, msg}
 * ============================================================
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';

// 🛡️ BARRERA DE ACCESO ADMINISTRATIVO
requireRole('admin');

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: La conexión a la base de datos no es estable']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // ══════════════════════════════════════
            // 🔍 OBTENER CONFIGURACIÓN + STATS
            // ══════════════════════════════════════
            $stmtSettings = $pdo->prepare("SELECT fn_admin_get_settings()");
            $stmtSettings->execute();
            $settings = json_decode($stmtSettings->fetchColumn(), true);

            $stmtStats = $pdo->prepare("SELECT fn_admin_get_stats()");
            $stmtStats->execute();
            $stats = json_decode($stmtStats->fetchColumn(), true);

            echo json_encode([
                'ok' => true,
                'store' => $settings['store'],
                'admin' => $settings['admin'],
                'stats' => $stats
            ]);
            break;

        case 'POST':
        case 'PUT':
            // ══════════════════════════════════════
            // 🔄 ACTUALIZAR CONFIGURACIÓN
            // ══════════════════════════════════════
            validateCsrfToken(null, true);
            $data = getJsonInput();

            $stmt = $pdo->prepare("SELECT fn_admin_update_settings(?::json)");
            $stmt->execute([json_encode($data)]);
            echo json_encode(json_decode($stmt->fetchColumn(), true));
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no soportado para ajustes globales']);
            break;
    }
}
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico de comunicación: ' . $e->getMessage()]);
}