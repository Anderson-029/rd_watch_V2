<?php
header('Content-Type: application/json');
require_once '../config.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$tipo = $_GET['tipo'] ?? '';

try {
    switch ($tipo) {
        case 'marcas':
            // Solo lista simple para dropdowns
            $stmt = $pdo->prepare("SELECT id_marca, nom_marca FROM tab_Marcas WHERE estado_marca = true ORDER BY nom_marca ASC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'marcas' => $data]);
            break;

        case 'categorias':
            $stmt = $pdo->prepare("SELECT id_categoria, nom_categoria FROM tab_Categorias WHERE estado = true ORDER BY nom_categoria ASC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'categorias' => $data]);
            break;

        case 'subcategorias':
            $idCat = isset($_GET['id_categoria']) ? $_GET['id_categoria'] : null;
            if (!$idCat) {
                echo json_encode(['ok' => false, 'msg' => 'ID categoría requerido', 'subcategorias' => []]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id_subcategoria, nom_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND estado = true ORDER BY nom_subcategoria ASC");
            $stmt->execute([$idCat]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'subcategorias' => $data]);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Tipo de catálogo no válido o no especificado']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
