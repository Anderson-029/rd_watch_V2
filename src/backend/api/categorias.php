<?php
/**
 * API: GESTIÓN DE CATEGORÍAS Y SUBCATEGORÍAS
 * ---------------------------------------------------------
 * Propósito: Administra la estructura jerárquica del catálogo de la tienda.
 * Permite organizar los relojes en Grupos (Categorías) y Familias (Subcategorías).
 * 
 * Lógica Dual:
 * - Sin parámetro 'action': Gestiona la tabla maestra tab_Categorias.
 * - Con ?action=subcategoria: Gestiona la tabla tab_Subcategorias (hijas).
 * 
 * Métodos:
 * - GET: Listado con normalización de estados para JS.
 * - POST: Registro con validación de ID único y jerarquía.
 * - PUT: Actualización de nombres y estados.
 * - DELETE: Borrado seguro (Valida que no existan hijos o productos vinculados).
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';

// Verificación de integridad de la conexión
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: Conexión a BD no disponible']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/**
 * Captura datos de entrada JSON.
 */
function getJsonInput()
{
    // Usamos la versión cacheada en security_utils.php para evitar conflictos con validateCsrfToken
    if (function_exists('getCachedJsonInput')) {
        return getCachedJsonInput();
    }
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

try {
    if ($action === 'subcategoria') {
        /**
         * ==========================================
         * 📂 MÓDULO: SUBCATEGORÍAS
         * ==========================================
         * Se identifica por la clave compuesta (id_categoria, id_subcategoria).
         */
        switch ($method) {
            case 'GET':
                /**
                 * 🔍 Listar Subcategorías con JOIN a su padre para obtener el nombre de la categoría principal.
                 */
                $sql = "SELECT s.id_categoria, s.id_subcategoria, s.nom_subcategoria, s.estado, c.nom_categoria 
                        FROM tab_Subcategorias s
                        JOIN tab_Categorias c ON s.id_categoria = c.id_categoria
                        ORDER BY s.id_categoria, s.id_subcategoria ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Normalización: Aseguramos que el estado regrese como booleano puro para el frontend.
                foreach ($subs as &$sub) {
                    $sub['estado'] = $sub['estado'] ? true : false;
                }

                echo json_encode(['ok' => true, 'subcategorias' => $subs]);
                break;

            case 'POST':
                /**
                 * ➕ Crear Subcategoría: Valida que no exista el par Identificador-Padre.
                 * Seguridad: Solo Admin y CSRF Protegido.
                 */
                requireRole('admin');
                validateCsrfToken();

                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['id_subcategoria'], $data['nom_subcategoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Faltan datos obligatorios (ID Categoría, ID Sub o Nombre)']);
                    exit;
                }

                $check = $pdo->prepare("SELECT id_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                $check->execute([$data['id_categoria'], $data['id_subcategoria']]);
                if ($check->fetch()) {
                    echo json_encode(['ok' => false, 'msg' => 'Ya existe un registro con ese ID de subcategoría en esta categoría']);
                    exit;
                }

                $sql = "INSERT INTO tab_Subcategorias (id_categoria, id_subcategoria, nom_subcategoria, estado, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, true, NOW(), 'admin_cat')";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$data['id_categoria'], $data['id_subcategoria'], $data['nom_subcategoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Subcategoría registrada']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Fallo al insertar subcategoría']);
                }
                break;

            case 'PUT':
                /**
                 * 🔄 Actualizar Subcategoría: Solo permite cambiar el nombre.
                 * Seguridad: Solo Admin y CSRF Protegido.
                 */
                requireRole('admin');
                validateCsrfToken();

                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['id_subcategoria'], $data['nom_subcategoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Datos insuficientes para la actualización']);
                    exit;
                }

                $sql = "UPDATE tab_Subcategorias 
                        SET nom_subcategoria = ?, fec_update = NOW(), usr_update = 'admin_editor'
                        WHERE id_categoria = ? AND id_subcategoria = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$data['nom_subcategoria'], $data['id_categoria'], $data['id_subcategoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Nombre de subcategoría actualizado']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error técnico al actualizar']);
                }
                break;

            case 'DELETE':
                /**
                 * 🗑️ Eliminar Subcategoría: Bloqueo si hay relojes asociados.
                 * Seguridad: Solo Admin y CSRF Protegido.
                 */
                requireRole('admin');
                validateCsrfToken();

                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['id_subcategoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'ID de categoría y subcategoría requeridos']);
                    exit;
                }

                $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_categoria = ? AND id_subcategoria = ?");
                $check->execute([$data['id_categoria'], $data['id_subcategoria']]);
                if ($check->fetchColumn() > 0) {
                    echo json_encode(['ok' => false, 'msg' => 'No es posible borrar: Existen productos cargados en esta subcategoría']);
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                if ($stmt->execute([$data['id_categoria'], $data['id_subcategoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Subcategoría eliminada exitosamente']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Falla al procesar la eliminación']);
                }
                break;
        }
    } else {
        /**
         * ==========================================
         * 🏷️ MÓDULO: CATEGORÍAS PRINCIPALES
         * ==========================================
         */
        switch ($method) {
            case 'GET':
                /**
                 * 🔍 Listar Categorías Maetras.
                 */
                $stmt = $pdo->prepare("SELECT id_categoria, nom_categoria, descripcion_categoria, estado FROM tab_Categorias ORDER BY id_categoria ASC");
                $stmt->execute();
                $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($cats as &$c) {
                    $c['estado'] = $c['estado'] ? true : false;
                }

                echo json_encode(['ok' => true, 'categorias' => $cats]);
                break;

            case 'POST':
                /**
                 * ➕ Crear Categoría: Valida unicidad de ID.
                 * Seguridad: Solo Admin y CSRF Protegido.
                 */
                requireRole('admin');
                validateCsrfToken();

                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['nom_categoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'ID y Nombre de categoría son obligatorios']);
                    exit;
                }

                $check = $pdo->prepare("SELECT id_categoria FROM tab_Categorias WHERE id_categoria = ?");
                $check->execute([$data['id_categoria']]);
                if ($check->fetch()) {
                    echo json_encode(['ok' => false, 'msg' => 'Ya existe una categoría principal con ese código de ID']);
                    exit;
                }

                $sql = "INSERT INTO tab_Categorias (id_categoria, nom_categoria, descripcion_categoria, estado, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, ?, NOW(), 'admin_root')";

                $estado = isset($data['estado']) ? ($data['estado'] ? 'true' : 'false') : 'true';
                $desc = $data['descripcion_categoria'] ?? '';

                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$data['id_categoria'], $data['nom_categoria'], $desc, $estado])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría principal creada correctamente']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al intentar guardar la categoría']);
                }
                break;

            case 'PUT':
                /**
                 * 🔄 Actualizar Categoría: Permite editar nombre, descripción y estado.
                 * Seguridad: Solo Admin y CSRF Protegido.
                 */
                requireRole('admin');
                validateCsrfToken();

                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['nom_categoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Datos insuficientes para la modificación']);
                    exit;
                }

                $sql = "UPDATE tab_Categorias 
                        SET nom_categoria = ?, descripcion_categoria = ?, estado = ?, fec_update = NOW(), usr_update = 'admin_editor'
                        WHERE id_categoria = ?";
                $stmt = $pdo->prepare($sql);

                $estado = isset($data['estado']) ? ($data['estado'] ? 'true' : 'false') : 'true';
                $desc = $data['descripcion_categoria'] ?? '';

                if ($stmt->execute([$data['nom_categoria'], $desc, $estado, $data['id_categoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría actualizada con éxito']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Fallo técnico al actualizar registro']);
                }
                break;

            case 'DELETE':
                /**
                 * 🗑️ Eliminar Categoría: Veracidad de cascada manual.
                 * Seguridad: Solo Admin y CSRF Protegido.
                 */
                requireRole('admin');
                validateCsrfToken();

                $data = getJsonInput();
                if (!isset($data['id_categoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Se requiere el ID de la categoría a eliminar']);
                    exit;
                }

                // 1. Verificación de Dependencias (Subcategorías)
                $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Subcategorias WHERE id_categoria = ?");
                $check->execute([$data['id_categoria']]);
                if ($check->fetchColumn() > 0) {
                    echo json_encode(['ok' => false, 'msg' => 'Error: No puede borrar una categoría que aún tiene subcategorías activas']);
                    exit;
                }

                // 2. Verificación de Dependencias (Productos)
                $checkProd = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_categoria = ?");
                $checkProd->execute([$data['id_categoria']]);
                if ($checkProd->fetchColumn() > 0) {
                    echo json_encode(['ok' => false, 'msg' => 'Error: Esta categoría posee productos vinculados en el catálogo']);
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM tab_Categorias WHERE id_categoria = ?");
                if ($stmt->execute([$data['id_categoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría eliminada permanentemente']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al ejecutar el borrado físico']);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['ok' => false, 'msg' => 'Método no permitido para este endpoint']);
                break;
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Falla crítica de base de datos: ' . $e->getMessage()]);
}
