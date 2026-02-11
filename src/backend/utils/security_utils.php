<?php
/**
 * 🛡️ UTILS: FUNCIONES DE SEGURIDAD (SECURITY UTILS)
 * ---------------------------------------------------------
 * Propósito: Proveer funciones reutilizables para protección contra ataques comunes.
 * 
 * 2. Sanitización: Limpieza básica de inputs (aunque PDO se encarga de SQLi).
 * 3. Gestión de Input: Caché de datos JSON para evitar re-lectura de php://input.
 * 4. Debug: Registro de eventos en debug_rdwatch.log.
 */

/**
 * Registra un mensaje de depuración en un archivo local del proyecto.
 */
function logDebug($message) {
    $logFile = __DIR__ . '/../../../debug_rdwatch.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Variable global para cachear el input JSON.
 */
$GLOBALS['__CACHED_JSON_INPUT'] = null;

/**
 * Obtiene el cuerpo de la petición JSON de forma segura, permitiendo múltiples lecturas.
 */
function getCachedJsonInput() {
    if ($GLOBALS['__CACHED_JSON_INPUT'] === null) {
        $raw = file_get_contents('php://input');
        $GLOBALS['__CACHED_JSON_INPUT'] = json_decode($raw, true) ?? [];
    }
    return $GLOBALS['__CACHED_JSON_INPUT'];
}

/**
 * Verifica si una acción está permitida bajo las reglas de Rate Limiting.
 * Retorna TRUE si puede proceder, FALSE si está bloqueado.
 * 
 * @param PDO $pdo Conexión a BD
 * @param string $ip Dirección IP del cliente
 * @param string $action Nombre de la acción (ej: 'login', 'signup')
 * @param int $limit Límite de intentos permitidos (Default: 5)
 * @param int $windowMinutes Ventana de tiempo en minutos (Default: 15)
 * @return bool
 */
function checkRateLimit($pdo, $ip, $action, $limit = 5, $windowMinutes = 15)
{
    try {
        $windowMinutes = (int)$windowMinutes; // 🛡️ Cast a int para prevenir inyección en el Interval
        $sql = "SELECT COUNT(*) as intentos 
                FROM tab_Rate_Limits 
                WHERE identificador = ? 
                AND nom_accion = ? 
                AND fec_intento > (NOW() - INTERVAL '$windowMinutes minutes')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['intentos'] >= $limit) {
            return false; // Bloqueado
        }
        return true; // Permitido
    } catch (Exception $e) {
        // En caso de error de BD, permitir por defecto para no bloquear servicio (Fail Open)
        // O bloquear (Fail Closed) dependiendo de la política. Por ahora Fail Open con log.
        error_log("Error en checkRateLimit: " . $e->getMessage());
        return true;
    }
}

/**
 * Registra un intento fallido en la tabla de Rate Limits.
 * 
 * @param PDO $pdo Conexión a BD
 * @param string $ip Dirección IP del cliente
 * @param string $action Nombre de la acción
 */
function logRateLimit($pdo, $ip, $action)
{
    try {
        $sql = "INSERT INTO tab_Rate_Limits (identificador, nom_accion, fec_intento, usr_insert) 
                VALUES (?, ?, NOW(), 'system')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $action]);
    } catch (Exception $e) {
        error_log("Error en logRateLimit: " . $e->getMessage());
    }
}

/**
 * Limpia los intentos previos (ej: tras un login exitoso).
 * 
 * @param PDO $pdo Conexión a BD
 * @param string $ip Dirección IP del cliente
 * @param string $action Nombre de la acción
 */
function clearRateLimit($pdo, $ip, $action)
{
    try {
        // Opcional: Borrar registros viejos o específicos
        // Por simplicidad en auditoría, a veces es mejor no borrar, 
        // pero para evitar falsos positivos tras éxito, se podría "resetear" 
        // borrando los recientes.
        $sql = "DELETE FROM tab_Rate_Limits 
                WHERE identificador = ? AND nom_accion = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $action]);
    } catch (Exception $e) {
        error_log("Error en clearRateLimit: " . $e->getMessage());
    }
}

/**
 * Obtiene la IP real del cliente (considerando proxies si es necesario).
 */
function getClientIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
/**
 * 🎫 REQUIRE LOGIN
 * Asegura que el usuario esté autenticado. Si no, corta la ejecución.
 */
function requireLogin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Sesión no iniciada o expirada. Por favor, ingrese de nuevo.']);
        exit;
    }
}

/**
 * 👮 REQUIRE ROLE
 * Asegura que el usuario tenga un rol específico.
 * @param string $role Rol requerido (ej: 'admin', 'cliente')
 */
function requireRole($role)
{
    requireLogin(); // Primero validar que esté logueado

    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Acceso Denegado: Insuficientes privilegios para esta operación.']);
        exit;
    }
}
/**
 * 🧹 SANITIZE HTML
 * Escapa caracteres especiales para prevenir XSS.
 * @param string $data Texto a sanitizar
 * @return string Texto escapado
 */
function sanitizeHtml($data)
{
    if (is_array($data)) {
        return array_map('sanitizeHtml', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * 🎫 GENERATE CSRF TOKEN
 * Genera un token aleatorio y lo guarda en la sesión.
 * @return string
 */
function generateCsrfToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Solo generar si no existe para evitar rotaciones innecesarias en una misma carga
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * 🔍 VALIDATE CSRF TOKEN
 * Verifica que el token recibido coincida con el de la sesión.
 * Corta la ejecución si no coinciden.
 */
function validateCsrfToken($receivedToken = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = $receivedToken;

    // 1. Intentar obtener de cabeceras HTTP (Estándar para AJAX)
    if (!$token) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token && function_exists('getallheaders')) {
            $headers = getallheaders();
            $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
        }
    }
    
    // 2. Intentar obtener del cuerpo JSON
    if (!$token) {
        $input = getCachedJsonInput();
        $token = $input['csrf_token'] ?? null;
    }

    // DEBUG LOGS (Solo habilitar en diagnóstico)
    $storedToken = $_SESSION['csrf_token'] ?? 'EMPTY_SESSION';
    $finalToken = $token ?? 'MISSING_IN_REQUEST';
    
    if ($finalToken === 'MISSING_IN_REQUEST' || !hash_equals($storedToken, $finalToken)) {
        logDebug("CSRF FAILED: Stored[$storedToken] vs Received[$finalToken] | User:" . ($_SESSION['user_id'] ?? 'NONE'));
    }

    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false, 
            'error_type' => 'CSRF_INVALID',
            'msg' => 'Error de Seguridad: Token CSRF inválido o ausente. Recargue la página e intente de nuevo.',
            'debug' => ['received' => $finalToken, 'session_exists' => !empty($_SESSION['csrf_token'])]
        ]);
        exit;
    }
}
