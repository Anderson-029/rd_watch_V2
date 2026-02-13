<?php
/**
 * API: ASISTENTE DE CARGA PARA CATÁLOGOS (DROPDOWNS)
 * ---------------------------------------------------------
 * Propósito: Optimiza la carga de la interfaz de usuario proveyendo únicamente 
 * los pares (ID, Nombre) necesarios para poblar selectores, menús desplegables 
 * y filtros de búsqueda.
 * 
 * Funcionalidades:
 * - tipo=marcas: Recupera marcas en estado activo.
 * - tipo=categorias: Recupera categorías principales activas.
 * - tipo=subcategorias: Recupera subcategorías filtradas por un padre específico.
 * 
 * Lógica:
 * - Siempre filtra por 'estado = true' para asegurar que el usuario no seleccione 
 *   entidades deshabilitadas o en mantenimiento.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once '../config.php';

// Verificación de integridad de la comunicación con BD
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de conexión: El catálogo maestro no está disponible']);
    exit;
}

// Discriminador de catálogo solicitado
$tipo = $_GET['tipo'] ?? '';

try {
    switch ($tipo) {
        case 'marcas':
            /**
             * ==========================================
             * 🏷️ LISTADO DE MARCAS (DROPDOWN)
             * ==========================================
             */
            $stmt = $pdo->prepare("SELECT id_marca, nom_marca FROM tab_Marcas WHERE estado_marca = true ORDER BY nom_marca ASC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'marcas' => $data]);
            break;

        case 'categorias':
            /**
             * ==========================================
             * 📁 LISTADO DE CATEGORÍAS (DROPDOWN)
             * ==========================================
             */
            $stmt = $pdo->prepare("SELECT id_categoria, nom_categoria FROM tab_Categorias WHERE estado = true ORDER BY nom_categoria ASC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'categorias' => $data]);
            break;

        case 'subcategorias':
            /**
             * ==========================================
             * 📂 LISTADO DE SUBCATEGORÍAS (DEPENDIENTE)
             * ==========================================
             * Lógica: Requiere el ID del padre (id_categoria) para realizar el filtrado relacional.
             */
            $idCat = isset($_GET['id_categoria']) ? $_GET['id_categoria'] : null;
            if (!$idCat) {
                echo json_encode(['ok' => false, 'msg' => 'Entrada inválida: El ID de la categoría padre es requerido', 'subcategorias' => []]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id_subcategoria, nom_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND estado = true ORDER BY nom_subcategoria ASC");
            $stmt->execute([$idCat]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'subcategorias' => $data]);
            break;

        default:
            /**
             * Manejo de peticiones fuera de rango.
             */
            echo json_encode(['ok' => false, 'msg' => 'Descriptor de catálogo no válido o no definido']);
            break;
    }

}
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Falla técnica al recuperar datos del catálogo: ' . $e->getMessage()]);
}