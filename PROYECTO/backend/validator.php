<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Biblioteca de Validación y Sanitización de Entradas
 * 
 * Centraliza la lógica para asegurar que los datos recibidos sean seguros 
 * y cumplan con los formatos esperados.
 */

class Validator
{
    /**
     * Valida una acción contra una lista blanca.
     */
    public static function validateAction(string $action, array $whitelist): string
    {
        if (!in_array($action, $whitelist, true)) {
            throw new InvalidArgumentException('Acción no permitida');
        }
        return $action;
    }

    /**
     * Valida y sanitiza un ID entero.
     */
    public static function validateId($id, int $min = 1): int
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id < $min) {
            throw new InvalidArgumentException('El identificador proporcionado no es válido');
        }
        return (int) $id;
    }

    /**
     * Sanitiza una cadena de texto para una salida segura.
     */
    public static function sanitizeString(string $str, int $maxLength = 255): string
    {
        $str = trim($str);
        // Escapar caracteres HTML para prevenir XSS
        $str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return substr($str, 0, $maxLength);
    }

    /**
     * Valida el formato de un correo electrónico.
     */
    public static function validateEmail(string $email): string
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw new InvalidArgumentException('El formato del correo electrónico no es válido');
        }
        return $email;
    }

    /**
     * Valida la fortaleza de una contraseña.
     * Requisitos: Mínimo 8 caracteres, al menos una mayúscula, una minúscula, 
     * un número y un carácter especial.
     */
    public static function validatePassword(string $password, int $minLength = 8): string
    {
        if (strlen($password) < $minLength) {
            throw new InvalidArgumentException("La contraseña debe tener al menos $minLength caracteres.");
        }

        // Reglas de complejidad
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[\W_]/', $password);

        if (!$hasUppercase || !$hasLowercase || !$hasNumber || !$hasSpecial) {
            throw new InvalidArgumentException(
                'La contraseña debe incluir mayúsculas, minúsculas, números y al menos un carácter especial (ej. !@#$%^&*).'
            );
        }

        return $password;
    }

    /**
     * Valida y decodifica una cadena JSON.
     */
    public static function validateJson(string $json)
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('El formato de datos enviado no es un JSON válido');
        }
        return $data;
    }

    /**
     * Valida y sanitiza una URL.
     */
    public static function validateUrl(string $url): string
    {
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if ($url === false) {
            throw new InvalidArgumentException('La dirección URL proporcionada no es válida');
        }
        return $url;
    }

    /**
     * Valida que un archivo sea una imagen real.
     */
    public static function validateImage(string $filePath, array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp']): bool
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('El archivo de imagen no existe');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new InvalidArgumentException("Tipo de archivo no permitido: $mimeType. Solo se permiten imágenes JPG, PNG o WEBP.");
        }

        return true;
    }
}
