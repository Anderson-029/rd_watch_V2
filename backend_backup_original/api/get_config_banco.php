<?php
// backend/api/get_config_banco.php

require_once('../security_headers.php');
header('Content-Type: application/json');

try {
    // Cargar variables de entorno
    if (file_exists('../.env')) {
        $lines = file('../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // Ignorar comentarios
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
    }

    // Retornar información bancaria
    echo json_encode([
        'ok' => true,
        'banco' => [
            'nombre' => $_ENV['BANCO_NOMBRE'] ?? 'BANCOLOMBIA',
            'tipo_cuenta' => $_ENV['BANCO_TIPO_CUENTA'] ?? 'AHORROS',
            'numero_cuenta' => $_ENV['BANCO_NUMERO_CUENTA'] ?? '02068128393',
            'titular' => $_ENV['BANCO_TITULAR'] ?? 'JUAN DURÁN',
            'breb_llave' => $_ENV['BANCO_BREB_LLAVE'] ?? 'duran0095'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error al obtener configuración'
    ]);
}

