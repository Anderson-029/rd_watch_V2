<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoint para obtener la clave pública de Stripe
 */

require_once('../security_headers.php');
require_once('../config.php');

header('Content-Type: application/json');

// Cargar variables de entorno
require_once('../load_env.php');

$stripePublicKey = getenv('STRIPE_PUBLIC_KEY');

if (!$stripePublicKey) {
    ErrorHandler::stopError("Configuración pública de Stripe no encontrada", 500);
}

ErrorHandler::sendSuccess("Configuración de Stripe obtenida", [
    'publicKey' => $stripePublicKey
]);
