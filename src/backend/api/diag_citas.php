<?php
/**
 * API: HERRAMIENTA DE DIAGNÓSTICO PARA RESERVAS
 * ---------------------------------------------------------
 * Propósito: Facilita la depuración técnica del sistema de citas/reservas. 
 * Permite a los desarrolladores verificar el estado de la sesión, los roles 
 * asignados y la integridad de los datos en la tabla de Reservas.
 * 
 * ADVERTENCIA DE SEGURIDAD: 
 * Este archivo expone datos de sesión sensibles. Se recomienda su protección 
 * mediante IP whitelist o su eliminación en entornos de producción final.
 */

require_once '../config.php';
require_once '../utils/security_utils.php';
header('Content-Type: application/json');

// 🛡️ BARRERA DE DIAGNÓSTICO: Solo accesible por administradores autorizados
requireRole('admin');


/**
 * ==========================================
 * 🛠️ CONSTRUCCIÓN DEL REPORTE TÉCNICO
 * ==========================================
 */
$diag = [];

// 1. Estado de Sesión: Crucial para verificar persistencia de login y roles
$diag['inspeccion_sesion'] = [
    'raw_session' => $_SESSION,
    'user_role' => $_SESSION['user_role'] ?? 'INVITADO/DESCONOCIDO',
    'user_id' => $_SESSION['user_id'] ?? 'NULL'
];

try {
    /**
     * 2. ANALÍTICA DE VOLUMEN (BD)
     * Verifica que la tabla tab_Reservas sea accesible y retorna el conteo total.
     */
    // 🔍 OPTIMIZACIÓN: Lectura de métrica persistente
    $stmtMetric = $pdo->query("SELECT metric_value FROM tab_sistema_metricas WHERE metric_key = 'total_reservas'");
    $resp = $stmtMetric->fetch(PDO::FETCH_ASSOC);
    $totalReservas = $resp['metric_value'] ?? 0;

    $diag['estadisticas_reservas'] = [
        'total_registros' => (int)$totalReservas
    ];

    /**
     * 3. MUESTREO DE AUDITORÍA
     * Trae las últimas 5 reservas con un JOIN para validar la relación usuario-reserva.
     */
    $sql = "SELECT r.id_reserva, r.id_usuario, u.nom_usuario, r.estado_reserva 
            FROM tab_Reservas r 
            LEFT JOIN tab_Usuarios u ON r.id_usuario = u.id_usuario 
            ORDER BY r.id_reserva DESC 
            LIMIT 5";

    $stmt = $pdo->query($sql);
    $diag['auditoria_muestreo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Verificación de lógica de privilegios
    $diag['permisos_calculados'] = [
        'es_administrador_global' => (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')
    ];

}
catch (PDOException $e) {
    /**
     * Captura de fallas en capa de datos.
     */
    $diag['db_error_trace'] = $e->getMessage();
}

/**
 * SALIDA: Formateada para legibilidad humana (JSON_PRETTY_PRINT)
 */
echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>