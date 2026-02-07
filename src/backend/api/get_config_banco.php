<?php
/**
 * API: CONFIGURACIÓN BANCARIA
 * ---------------------------------------------------------
 * Retorna los datos de la cuenta bancaria para pagos
 * por transferencia.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Por ahora devolvemos datos estáticos (pueden venir de una tabla de configuración en el futuro)
echo json_encode([
    'ok' => true,
    'banco' => [
        'nombre' => 'Bancolombia',
        'tipo_cuenta' => 'Ahorros',
        'numero_cuenta' => '518-000123-45',
        'titular' => 'Relojería Durán SAS',
        'breb_llave' => 'relojeria.duran@negocio'
    ]
]);
