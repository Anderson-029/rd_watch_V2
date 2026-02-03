<?php
// backend/api/ciudades.php

// Aplicar cabeceras de seguridad
require_once('../security_headers.php');

header('Content-Type: application/json');

include_once('../config.php');

$action = $_GET['action'] ?? 'ciudades';

try {
    if ($action === 'departamentos') {
        // Obtener todos los departamentos
        $stmt = $pdo->query("
            SELECT id_departamento, nombre_departamento, codigo_iso 
            FROM tab_Departamentos 
            ORDER BY nombre_departamento ASC
        ");
        $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'departamentos' => $departamentos]);
        
    } elseif ($action === 'ciudades') {
        // Obtener ciudades (opcionalmente filtradas por departamento)
        $id_depto = $_GET['id_departamento'] ?? null;
        
        if ($id_depto) {
            // Ciudades de un departamento específico
            $stmt = $pdo->prepare("
                SELECT id_ciudad, nombre_ciudad, codigo_postal, id_departamento
                FROM tab_Ciudades 
                WHERE id_departamento = :id_depto
                ORDER BY nombre_ciudad ASC
            ");
            $stmt->execute([':id_depto' => (int)$id_depto]);
        } else {
            // Todas las ciudades con información del departamento
            $stmt = $pdo->query("
                SELECT c.id_ciudad, c.nombre_ciudad, c.codigo_postal,
                       c.id_departamento, d.nombre_departamento
                FROM tab_Ciudades c
                JOIN tab_Departamentos d ON c.id_departamento = d.id_departamento
                ORDER BY c.nombre_ciudad ASC
            ");
        }
        
        $ciudades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'ciudades' => $ciudades]);
        
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
    }
    
} catch (PDOException $e) {
    error_log("Error BD en ciudades.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error BD']);
} catch (Exception $e) {
    error_log("Error en ciudades.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor']);
}

