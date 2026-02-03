<?php
/**
 * Rate Limiter
 * Protección contra ataques de fuerza bruta basada en IP
 * Migrado de archivos temporales a Base de Datos (PostgreSQL) para persistencia y escalabilidad.
 */

/**
 * Verifica si una acción está permitida según el límite de tasa
 * @param string $action Nombre de la acción (ej: 'login', 'signup')
 * @param string $identifier Identificador único (normalmente IP)
 * @param int $maxAttempts Máximo de intentos permitidos
 * @param int $windowSeconds Ventana de tiempo en segundos
 * @return bool True si está permitido, false si excede el límite
 */
function check_rate_limit(string $action, string $identifier, int $maxAttempts, int $windowSeconds): bool
{
    global $pdo;

    // Si no hay conexión a BD, fallback a permitir (o podrías lanzar error según política)
    if (!$pdo) {
        return true;
    }

    try {
        // 1. Contar intentos recientes en la ventana de tiempo
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tab_Rate_Limits 
            WHERE nom_accion = :action 
              AND identificador = :id 
              AND fec_intento > (CURRENT_TIMESTAMP - INTERVAL ':window seconds')
        ");

        // PDO no permite bind direct de intervalos así, usamos una forma más segura
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tab_Rate_Limits 
            WHERE nom_accion = :action 
              AND identificador = :id 
              AND fec_intento > (NOW() - CAST(:window || ' seconds' AS INTERVAL))
        ");

        $stmt->execute([
            ':action' => $action,
            ':id' => $identifier,
            ':window' => $windowSeconds
        ]);

        $count = $stmt->fetchColumn();

        // 2. Verificar si excede el límite
        if ($count >= $maxAttempts) {
            return false;
        }

        // 3. Registrar nuevo intento
        $stmtInsert = $pdo->prepare("
            INSERT INTO tab_Rate_Limits (nom_accion, identificador) 
            VALUES (:action, :id)
        ");
        $stmtInsert->execute([
            ':action' => $action,
            ':id' => $identifier
        ]);

        return true;

    } catch (PDOException $e) {
        error_log("Error en check_rate_limit: " . $e->getMessage());
        return true; // En caso de fallo de BD, permitimos la acción por usabilidad
    }
}

/**
 * Obtiene el tiempo restante de bloqueo en segundos
 * @param string $action Nombre de la acción
 * @param string $identifier Identificador único
 * @param int $windowSeconds Ventana de tiempo
 * @return int Segundos restantes de bloqueo (0 si no está bloqueado)
 */
function get_rate_limit_remaining(string $action, string $identifier, int $windowSeconds): int
{
    global $pdo;

    if (!$pdo)
        return 0;

    try {
        // Buscamos el intento más antiguo dentro de la ventana que causó el bloqueo
        $stmt = $pdo->prepare("
            SELECT EXTRACT(EPOCH FROM (fec_intento + CAST(:window || ' seconds' AS INTERVAL) - NOW())) as remaining
            FROM tab_Rate_Limits
            WHERE nom_accion = :action 
              AND identificador = :id
              AND fec_intento > (NOW() - CAST(:window || ' seconds' AS INTERVAL))
            ORDER BY fec_intento ASC
            LIMIT 1
        ");

        $stmt->execute([
            ':action' => $action,
            ':id' => $identifier,
            ':window' => $windowSeconds
        ]);

        $remaining = (int) $stmt->fetchColumn();
        return $remaining > 0 ? $remaining : 0;

    } catch (PDOException $e) {
        error_log("Error en get_rate_limit_remaining: " . $e->getMessage());
        return 0;
    }
}

/**
 * Limpia el límite de tasa para un identificador (usar después de login exitoso)
 * @param string $action Nombre de la acción
 * @param string $identifier Identificador único
 */
function clear_rate_limit(string $action, string $identifier): void
{
    global $pdo;

    if (!$pdo)
        return;

    try {
        $stmt = $pdo->prepare("
            DELETE FROM tab_Rate_Limits 
            WHERE nom_accion = :action 
              AND identificador = :id
        ");
        $stmt->execute([
            ':action' => $action,
            ':id' => $identifier
        ]);
    } catch (PDOException $e) {
        error_log("Error en clear_rate_limit: " . $e->getMessage());
    }
}

/**
 * Envía respuesta de rate limit excedido
 * @param int $retryAfter Segundos para reintentar
 */
function rate_limit_fail_response(int $retryAfter = 60): void
{
    http_response_code(429);
    header('Content-Type: application/json');
    header('Retry-After: ' . $retryAfter);
    echo json_encode([
        'ok' => false,
        'msg' => "Has excedido el número de intentos permitidos. Por favor espera $retryAfter segundos.",
        'retry_after' => $retryAfter
    ]);
    exit;
}


