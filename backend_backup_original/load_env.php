<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Cargador centralizado de variables de entorno (.env)
 * 
 * Este script lee el archivo .env y carga las variables en $_ENV y putenv(),
 * permitiendo que la configuración sea portable y segura.
 */

// Ruta absoluta al archivo .env
$envFile = __DIR__ . '/.env';

/**
 * Carga las variables de entorno si el archivo existe.
 */
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios y líneas vacías
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Procesar solo líneas con el formato CLAVE=VALOR
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Eliminar comillas envolventes si existen
            $value = trim($value, '"\'');

            // Asignar a las variables globales de entorno
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}