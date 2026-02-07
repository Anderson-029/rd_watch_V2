<?php
/**
 * API: DIVISIÓN TERRITORIAL (Departamentos y Ciudades)
 * ---------------------------------------------------------
 * Este archivo provee los datos de ubicación necesarios para 
 * las direcciones de envío de los clientes.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

// Acción solicitada: 'departamentos' o 'ciudades'
$action = $_GET['action'] ?? '';

try {
    if ($action === 'departamentos') {
        /**
         * OBTENER DEPARTAMENTOS
         * Devuelve la lista completa de departamentos disponibles.
         */
        $stmt = $pdo->query("SELECT id_departamento, nombre_departamento FROM tab_Departamentos ORDER BY nombre_departamento");
        $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'departamentos' => $departamentos]);
    } elseif ($action === 'ciudades') {
        /**
         * OBTENER CIUDADES
         * Recibe el ID de un departamento y filtra las ciudades asociadas.
         */
        $id_depto = $_GET['id_departamento'] ?? null;
        if (!$id_depto) {
            echo json_encode(['ok' => false, 'msg' => 'ID de departamento requerido']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id_ciudad, nombre_ciudad, codigo_postal FROM tab_Ciudades WHERE id_departamento = ? ORDER BY nombre_ciudad");
        $stmt->execute([$id_depto]);
        $ciudades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'ciudades' => $ciudades]);
    } else {
        // En caso de que no se envíe una acción válida
        echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}
