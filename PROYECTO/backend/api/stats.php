<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoint para obtener estadísticas del negocio
 */

require_once('../security_headers.php');
require_once('../config.php');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'GET') {
        ErrorHandler::stopError("Método no permitido", 405);
    }

    // 1. Años de Experiencia (Basado en fundación en 1972)
    $foundedYear = 1972;
    $currentYear = (int)date('Y');
    $yearsExperience = $currentYear - $foundedYear;
    
    // 2. Relojes Reparados (Conteo de Órdenes)
    $stmtOrders = $pdo->query("SELECT COUNT(*) as total FROM tab_Orden");
    $ordersCount = (int)$stmtOrders->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Valor base de prestigio si el conteo es bajo o nulo para efectos de marketing
    $repairedWatches = ($ordersCount > 1500) ? $ordersCount : 1500 + $ordersCount; 

    // 3. % Clientes Satisfechos (Opiniones > 3 estrellas)
    $stmtReviews = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN calificacion > 3 THEN 1 ELSE 0 END) as positive FROM tab_Opiniones");
    $reviewData = $stmtReviews->fetch(PDO::FETCH_ASSOC);
    
    $totalReviews = (int)$reviewData['total'];
    $positiveReviews = (int)$reviewData['positive'];
    
    $satisfactionPercentage = ($totalReviews > 0) ? round(($positiveReviews / $totalReviews) * 100) : 98;

    ErrorHandler::sendSuccess("Estadísticas obtenidas", [
        'stats' => [
            'years' => $yearsExperience,
            'repaired' => $repairedWatches,
            'satisfaction' => $satisfactionPercentage
        ]
    ]);

} catch (PDOException $e) {
    ErrorHandler::handleException($e);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}
