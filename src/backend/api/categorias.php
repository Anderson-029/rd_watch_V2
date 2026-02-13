<?php
/**
 * API: GESTIÓN DE CATEGORÍAS Y SUBCATEGORÍAS
 * ---------------------------------------------------------
 * Propósito: Administra la estructura jerárquica del catálogo de la tienda.
 * 
 * Seguridad:
 * - Protección Perimetral: requireRole('admin') y validateCsrfToken().
 * - Integridad Referencial: Bloqueo de borrado si existen dependencias.
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

// Verificación de infraestructura
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de Infraestructura: Motor de datos no disponible']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($action === 'subcategoria') {
        /**
         * ==========================================
         * 📂 MÓDULO: SUBCATEGORÍAS
         * ==========================================
         */
        switch ($method) {
            case 'GET':
                $sql = "SELECT s.id_categoria, s.id_subcategoria, s.nom_subcategoria, s.estado, c.nom_categoria 
                        FROM tab_Subcategorias s
                        JOIN tab_Categorias c ON s.id_categoria = c.id_categoria
                        ORDER BY s.id_categoria, s.id_subcategoria ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($subs as &$sub) {
                    $sub['estado'] = $sub['estado'] ? true : false;
                }

                echo json_encode(['ok' => true, 'subcategorias' => $subs]);
                break;

            case 'POST':
                requireRole('admin');
                validateCsrfToken(null, true);

                $data = getCachedJsonInput();
                Validation::validateOrReject($data, [
                    'id_categoria' => 'id',
                    'id_subcategoria' => 'id',
                    'nom_subcategoria' => 'name'
                ]);

                $check = $pdo->prepare("SELECT id_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                $check->execute([$data['id_categoria'], $data['id_subcategoria']]);
                if ($check->fetch()) {
                    echo json_encode(['ok' => false, 'msg' => 'Conflicto: El ID de subcategoría ya está en uso para esta categoría.']);
                    exit;
                }

                $sql = "INSERT INTO tab_Subcategorias (id_categoria, id_subcategoria, nom_subcategoria, estado, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, true, NOW(), 'admin_cat')";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$data['id_categoria'], $data['id_subcategoria'], $data['nom_subcategoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Subcategoría creada con éxito']);
                }
                else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al registrar la subcategoría']);
                }
                break;

            case 'PUT':
                requireRole('admin');
                validateCsrfToken(null, true);

                $data = getCachedJsonInput();
                $sql = "UPDATE tab_Subcategorias SET nom_subcategoria = ?, fec_update = NOW(), usr_update = 'admin_editor'
                        WHERE id_categoria = ? AND id_subcategoria = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$data['nom_subcategoria'], $data['id_categoria'], $data['id_subcategoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Modificación guardada']);
                }
                else {
                    echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar la subcategoría']);
                }
                break;

            case 'DELETE':
                /**
                 * 🗑️ ELIMINACIÓN SEGURA DE SUBCATEGORÍA
                 * Impide el borrado si existen productos vinculados.
                 */
                requireRole('admin');
                validateCsrfToken();

                $data = getCachedJsonInput();
                $idCat = $data['id_categoria'] ?? null;
                $idSub = $data['id_subcategoria'] ?? null;

                if ($idCat === null || $idSub === null) {
                    echo json_encode(['ok' => false, 'msg' => 'Faltan IDs de referencia para el borrado']);
                    exit;
                }

                // 🛡️ BARRERA DE INTEGRIDAD: Validar productos vinculados
                $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_categoria = ? AND id_subcategoria = ?");
                $check->execute([$idCat, $idSub]);
                $count = $check->fetchColumn();

                if ($count > 0) {
                    echo json_encode([
                        'ok' => false,
                        'msg' => "ACCIÓN BLOQUEADA: Existen $count productos vinculados a esta subcategoría. Debe eliminar o reasignar los productos antes de borrar la familia."
                    ]);
                    exit;
                }

                // Ejecutar borrado físico
                $stmt = $pdo->prepare("DELETE FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                if ($stmt->execute([$idCat, $idSub])) {
                    echo json_encode(['ok' => true, 'msg' => 'Subcategoría eliminada del catálogo']);
                }
                else {
                    $error = $stmt->errorInfo();
                    echo json_encode(['ok' => false, 'msg' => 'Error crítico de base de datos: ' . ($error[2] ?? 'Fallo de integridad')]);
                }
                break;
        }
    }
    else {
        /**
         * ==========================================
         * 🏷️ MÓDULO: CATEGORÍAS PRINCIPALES
         * ==========================================
         */
        switch ($method) {
            case 'GET':
                $stmt = $pdo->prepare("SELECT id_categoria, nom_categoria, descripcion_categoria, estado FROM tab_Categorias ORDER BY id_categoria ASC");
                $stmt->execute();
                $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($cats as &$c) {
                    $c['estado'] = $c['estado'] ? true : false;
                }

                echo json_encode(['ok' => true, 'categorias' => $cats]);
                break;

            case 'POST':
                requireRole('admin');
                validateCsrfToken(null, true);

                $data = getCachedJsonInput();
                Validation::validateOrReject($data, [
                    'id_categoria' => 'id',
                    'nom_categoria' => 'name'
                ]);

                $sql = "INSERT INTO tab_Categorias (id_categoria, nom_categoria, descripcion_categoria, estado, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, ?, NOW(), 'admin_root')";

                $estado = isset($data['estado']) ? ($data['estado'] ? 'true' : 'false') : 'true';
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$data['id_categoria'], $data['nom_categoria'], $data['descripcion_categoria'] ?? '', $estado])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría principal creada']);
                }
                else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al guardar la categoría']);
                }
                break;

            case 'PUT':
                requireRole('admin');
                validateCsrfToken(null, true);

                $data = getCachedJsonInput();
                $sql = "UPDATE tab_Categorias SET nom_categoria = ?, descripcion_categoria = ?, estado = ?, fec_update = NOW(), usr_update = 'admin_editor'
                        WHERE id_categoria = ?";
                $stmt = $pdo->prepare($sql);
                $estado = isset($data['estado']) ? ($data['estado'] ? 'true' : 'false') : 'true';
                if ($stmt->execute([$data['nom_categoria'], $data['descripcion_categoria'] ?? '', $estado, $data['id_categoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría actualizada']);
                }
                else {
                    echo json_encode(['ok' => false, 'msg' => 'Fallo al actualizar registro']);
                }
                break;

            case 'DELETE':
                requireRole('admin');
                validateCsrfToken();

                $data = getCachedJsonInput();
                $idCat = $data['id_categoria'] ?? null;

                if ($idCat === null) {
                    echo json_encode(['ok' => false, 'msg' => 'ID de categoría faltante']);
                    exit;
                }

                // Verificación de Subcategorías
                $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Subcategorias WHERE id_categoria = ?");
                $check->execute([$idCat]);
                $countSubs = $check->fetchColumn();
                if ($countSubs > 0) {
                    echo json_encode(['ok' => false, 'msg' => "BLOQUEO: Esta categoría aún tiene $countSubs subcategorías activas."]);
                    exit;
                }

                // Verificación de Productos
                $checkProd = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_categoria = ?");
                $checkProd->execute([$idCat]);
                $countProds = $checkProd->fetchColumn();
                if ($countProds > 0) {
                    echo json_encode(['ok' => false, 'msg' => "BLOQUEO: Existen $countProds productos vinculados a esta categoría."]);
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM tab_Categorias WHERE id_categoria = ?");
                if ($stmt->execute([$idCat])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría eliminada permanentemente']);
                }
                else {
                    echo json_encode(['ok' => false, 'msg' => 'Error de integridad al borrar']);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['ok' => false, 'msg' => 'Método no soportado']);
                break;
        }
    }
}
catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error de seguridad o datos: ' . $e->getMessage()
    ]);
}