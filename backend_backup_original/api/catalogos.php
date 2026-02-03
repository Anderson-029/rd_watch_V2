<?php
// backend/api/catalogos.php
require_once('../security_headers.php');

header('Content-Type: application/json');

include_once('../config.php');

$tipo = $_GET['tipo'] ?? '';

try {
    switch($tipo) {
        case 'marcas':
            $stmt = $pdo->query("
                SELECT id_marca, nom_marca 
                FROM tab_Marcas 
                WHERE estado_marca = TRUE 
                ORDER BY nom_marca
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'marcas' => $data]);
            break;

        case 'categorias':
            $stmt = $pdo->query("
                SELECT id_categoria, nom_categoria, descripcion_categoria 
                FROM tab_Categorias 
                WHERE estado = TRUE 
                ORDER BY nom_categoria
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'categorias' => $data]);
            break;

        case 'subcategorias':
            $id_cat = $_GET['id_categoria'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT id_subcategoria, nom_subcategoria 
                FROM tab_Subcategorias 
                WHERE id_categoria = :cat AND estado = TRUE 
                ORDER BY nom_subcategoria
            ");
            $stmt->execute([':cat' => $id_cat]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'subcategorias' => $data]);
            break;

        case 'metodos_pago':
            $stmt = $pdo->query("
                 SELECT id_metodo_pago, nombre_metodo, descripcion 
                 FROM tab_Metodos_Pago 
                 ORDER BY nombre_metodo
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'metodos_pago' => $data]);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Tipo no válido']);
            break;
    }
} catch (Exception $e) {
    error_log("Error en catalogos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor']);
}
