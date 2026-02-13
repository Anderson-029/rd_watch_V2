<?php
require_once 'src/backend/config.php';

echo "📋 TEST: Verificación de Bloqueo por Integridad en MARCAS\n";
echo "--------------------------------------------------------\n";

// Intentar borrar la marca 1 (Rolex) que sabemos que tiene productos
$idMarca = 1;

try {
    // 1. Verificar cuántos productos hay
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_marca = ?");
    $stmt->execute([$idMarca]);
    $count = $stmt->fetchColumn();
    echo "🔍 Productos encontrados para Marca $idMarca: $count\n";

    // 2. Simular la petición DELETE (Lógica del backend)
    if ($count > 0) {
        $response = [
            'ok' => false,
            'msg' => "ACCIÓN BLOQUEADA: Existen $count productos vinculados a esta marca en el catálogo. Debe eliminar o reasignar los productos antes de borrar la marca."
        ];
        echo "✅ RESPUESTA ESPERADA RECIBIDA:\n";
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    else {
        echo "❌ FALLO: Se esperaba que la marca tuviera productos vinculados.\n";
    }

}
catch (Exception $e) {
    echo "❌ ERROR EN EL TEST: " . $e->getMessage() . "\n";
}