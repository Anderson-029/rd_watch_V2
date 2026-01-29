<?php
// backend/api/clientes.php
require_once('../security_headers.php');

header('Content-Type: application/json');

include_once('../config.php');

// Verificar que quien pide los datos sea ADMIN
if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado']);
    exit;
}

try {
    // Seleccionamos todos los usuarios que NO sean admin (o que tengan rol cliente/null)
    $sql = "SELECT 
                id_usuario, 
                nom_usuario, 
                correo_usuario, 
                num_telefono_usuario, 
                direccion_principal,
                activo,
                to_char(fecha_registro, 'DD/MM/YYYY') as fecha_registro
            FROM tab_Usuarios 
            WHERE rol IS DISTINCT FROM 'admin' 
            ORDER BY id_usuario DESC";

    $stmt = $pdo->query($sql);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sanitize output
    foreach ($clientes as &$c) {
        $c['nom_usuario'] = htmlspecialchars($c['nom_usuario']);
        $c['correo_usuario'] = htmlspecialchars($c['correo_usuario']);
        $c['direccion_principal'] = htmlspecialchars($c['direccion_principal'] ?? '');
    }

    echo json_encode([
        'ok' => true,
        'clientes' => $clientes,
        'total' => count($clientes)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'msg' => 'Error al obtener clientes'
    ]);
}
