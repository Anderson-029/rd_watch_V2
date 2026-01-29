<?php
/**
 * ErrorHandler
 * Clase estática para estandarizar las respuestas de error y éxito en la API.
 */

class ErrorHandler
{

    /**
     * Envía una respuesta de error JSON y termina la ejecución.
     * @param string $message Mensaje amigable para el usuario
     * @param int $httpCode Código de estado HTTP (default 400)
     * @param string|null $internalError Detalle del error para logs (opcional)
     */
    public static function stopError(string $message, int $httpCode = 400, ?string $internalError = null): void
    {
        $logMsg = "($httpCode): $message";
        if ($internalError) {
            $logMsg .= " | Internal: $internalError";
        }
        Logger::error($logMsg);

        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'msg' => $message
        ]);
        exit;
    }

    /**
     * Envía una respuesta de éxito JSON y termina la ejecución.
     * @param string $message Mensaje de éxito
     * @param array $data Datos adicionales para retornar
     * @param int $httpCode Código de estado HTTP (default 200)
     */
    public static function sendSuccess(string $message, array $data = [], int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'ok' => true,
            'msg' => $message
        ], $data));
        exit;
    }

    /**
     * Capturador global de excepciones para la API.
     * Convierte cualquier excepción no capturada en una respuesta JSON segura.
     */
    public static function handleException(Throwable $e): void
    {
        $msg = "Error interno del servidor";
        $code = 500;

        if ($e instanceof InvalidArgumentException) {
            $msg = $e->getMessage();
            $code = 400;
        }

        self::stopError($msg, $code, $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
}
