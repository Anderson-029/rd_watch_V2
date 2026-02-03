<?php
/**
 * RD Watch - Logger Profesional
 * Maneja el registro de eventos, errores y seguridad con soporte para rotado básico.
 */

class Logger
{
    private static $logPath = __DIR__ . '/logs/';
    private static $maxSize = 5242880; // 5MB

    /**
     * Registra un mensaje en un archivo específico.
     * @param string $message Mensaje a registrar
     * @param string $level Nivel (INFO, ERROR, SECURITY)
     * @param string $filename Nombre del archivo de log
     */
    public static function log(string $message, string $level = 'INFO', string $filename = 'app.log'): void
    {
        if (!is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0755, true);
        }

        $filePath = self::$logPath . $filename;

        // Rotado básico: si supera el tamaño máximo, renombrar el actual
        if (file_exists($filePath) && filesize($filePath) > self::$maxSize) {
            @rename($filePath, $filePath . '.' . date('Ymd_His') . '.bak');
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent';

        // Formato Enriquecido para SOC (Pipe separated para fácil parsing)
        $logEntry = "[$timestamp] [$level] [IP:$ip] [URI:$uri] [UA:$agent] $message" . PHP_EOL;

        @file_put_contents($filePath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message): void
    {
        self::log($message, 'ERROR', 'errors.log');
    }

    public static function security(string $message): void
    {
        self::log($message, 'SECURITY', 'security.log');
    }

    public static function info(string $message): void
    {
        self::log($message, 'INFO', 'app.log');
    }
}
