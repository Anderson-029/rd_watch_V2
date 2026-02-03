<?php
// backend/api/carrito.php

// Iniciar buffer de salida
ob_start();

include_once('../config.php');

// Limpiar buffer antes de enviar cabeceras
ob_clean(); 

// Aplicar cabeceras de seguridad
require_once('../security_headers.php');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['ok' => true, 'items' => []]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    cd.id_producto,
                    cd.cantidad,
                    p.nom_producto,
                    p.precio,
                    p.stock,
                    p.url_imagen,
                    m.nom_marca
                FROM tab_Carrito c
                INNER JOIN tab_Carrito_Detalle cd ON c.id_carrito = cd.id_carrito
                INNER JOIN tab_Productos p ON cd.id_producto = p.id_producto
                LEFT JOIN tab_Marcas m ON p.id_marca = m.id_marca
                WHERE c.id_usuario = :user_id 
                AND c.estado_carrito = 'activo'
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$item) {
                $item['nom_producto'] = htmlspecialchars($item['nom_producto']);
                $item['url_imagen'] = htmlspecialchars($item['url_imagen']);
                $item['nom_marca'] = htmlspecialchars($item['nom_marca']);
            }
            
            echo json_encode(['ok' => true, 'items' => $items]);
            break;

        case 'POST':
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión']);
                exit;
            }
            
            $data = json_decode(file_get_contents("php://input"), true);
            $id_producto = intval($data['id_producto'] ?? 0);
            $cantidad = intval($data['cantidad'] ?? 1);
            
            if ($id_producto <= 0 || $cantidad <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT fun_agregar_carrito(:user_id, :prod_id, :qty)");
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':prod_id' => $id_producto,
                ':qty' => $cantidad
            ]);
            $result = $stmt->fetchColumn();
            
            // Verificación flexible del resultado
            if (strpos($result, 'SUCCESS') !== false) {
                echo json_encode(['ok' => true, 'msg' => 'Producto agregado al carrito']);
            } else {
                // A veces SQL devuelve un error, pero el HTTP es 200.
                // Enviamos ok: false para que JS lo maneje.
                echo json_encode(['ok' => false, 'msg' => $result]);
            }
            break;

        case 'PUT':
            if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
            $data = json_decode(file_get_contents("php://input"), true);
            
            $stmt = $pdo->prepare("
                UPDATE tab_Carrito_Detalle cd
                SET cantidad = :qty
                FROM tab_Carrito c
                WHERE cd.id_carrito = c.id_carrito
                AND c.id_usuario = :user_id
                AND cd.id_producto = :prod_id
                AND c.estado_carrito = 'activo'
            ");
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':prod_id' => intval($data['id_producto']),
                ':qty' => intval($data['cantidad'])
            ]);
            
            echo json_encode(['ok' => true, 'msg' => 'Cantidad actualizada']);
            break;

        case 'DELETE':
            if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
            $data = json_decode(file_get_contents("php://input"), true);
            
            $stmt = $pdo->prepare("
                DELETE FROM tab_Carrito_Detalle cd
                USING tab_Carrito c
                WHERE cd.id_carrito = c.id_carrito
                AND c.id_usuario = :user_id
                AND cd.id_producto = :prod_id
                AND c.estado_carrito = 'activo'
            ");
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':prod_id' => intval($data['id_producto'])
            ]);
            
            echo json_encode(['ok' => true, 'msg' => 'Eliminado']);
            break;
    }
} catch (Exception $e) {
    error_log("Error en carrito.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor']);
}

// 3. Finalizar el buffer (asegura que no se envíe nada más)
ob_end_flush();
