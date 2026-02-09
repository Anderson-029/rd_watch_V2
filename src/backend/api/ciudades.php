<?php
/**
 * API: DIVISIÓN TERRITORIAL (UBICACIONES)
 * ---------------------------------------------------------
 * Propósito: Proveer el catálogo geográfico (Departamentos y Ciudades) 
 * necesario para la recolección de direcciones de envío precisas durante 
 * el registro de usuario y el proceso de checkout.
 * 
 * Lógica:
 * - Sin 'action': No realiza operación.
 * - action=departamentos: Lista todas las regiones administrativas.
 * - action=ciudades: Lista los municipios vinculados a un departamento específico.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de integridad de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: El servicio de datos geográficos no está disponible']);
    exit;
}

// Discriminador de acción
$action = $_GET['action'] ?? '';

try {
    if ($action === 'departamentos') {
        /**
         * ==========================================
         * 🌐 OBTENER DEPARTAMENTOS
         * ==========================================
         */
        $stmt = $pdo->query("SELECT id_departamento, nombre_departamento FROM tab_Departamentos ORDER BY nombre_departamento ASC");
        $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'count' => count($departamentos),
            'departamentos' => $departamentos
        ]);
    } elseif ($action === 'ciudades') {
        /**
         * ==========================================
         * 🏙️ OBTENER CIUDADES
         * ==========================================
         * Seguridad: Requiere un id_departamento válido para evitar volcados masivos innecesarios.
         */
        $id_depto = $_GET['id_departamento'] ?? null;
        if (!$id_depto) {
            echo json_encode(['ok' => false, 'msg' => 'Entrada inválida: Se requiere el identificador del departamento']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id_ciudad, nombre_ciudad, codigo_postal FROM tab_Ciudades WHERE id_departamento = ? ORDER BY nombre_ciudad ASC");
        $stmt->execute([$id_depto]);
        $ciudades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'count' => count($ciudades),
            'ciudades' => $ciudades
        ]);
    } else {
        /**
         * Manejo de rutas inválidas.
         */
        echo json_encode(['ok' => false, 'msg' => 'Solicitud malformada: Acción geográfica no reconocida']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico en consulta geográfica: ' . $e->getMessage()]);
}
