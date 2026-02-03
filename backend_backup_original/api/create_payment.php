<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoint para crear PaymentIntent de Stripe
 */

require_once('../security_headers.php');
require_once('../csrf.php');
require_once('../session_manager.php');
require_once('../config.php');

header('Content-Type: application/json');

require_valid_session();

// Cargar variables de entorno (incluyendo las de Stripe)
// El archivo load_env.php ya debe estar siendo cargado por config.php o similar
// Si no, lo cargamos aquí para asegurar disponibilidad de STRIPE_SECRET_KEY
require_once('../load_env.php');

$stripeSecretKey = getenv('STRIPE_SECRET_KEY');

if (!$stripeSecretKey) {
    ErrorHandler::stopError("Configuración de Stripe no encontrada", 500);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'POST') {
        ErrorHandler::stopError("Método no permitido", 405);
    }

    require_csrf_token();

    // En un escenario real, aquí deberíamos calcular el monto basado en el carrito
    // Por simplicidad para la unificación, usaremos un valor de prueba o el enviado
    $data = json_decode(file_get_contents("php://input"), true);
    $amount = isset($data['amount']) ? (int) $data['amount'] : 1000; // Valor en centavos (e.g., 10.00 USD)
    $currency = isset($data['currency']) ? $data['currency'] : 'usd';

    // Inicializar petición a Stripe vía cURL (para no depender de la SDK de PHP si no está instalada)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'amount' => $amount,
        'currency' => $currency,
        'payment_method_types[]' => 'card'
    ]));
    curl_setopt($ch, CURLOPT_USERPWD, $stripeSecretKey . ':');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode === 200) {
        ErrorHandler::sendSuccess("PaymentIntent creado", [
            'clientSecret' => $responseData['client_secret']
        ]);
    } else {
        ErrorHandler::stopError($responseData['error']['message'] ?? "Error al crear pago en Stripe", 400);
    }

} catch (PDOException $e) {
    ErrorHandler::handleException($e);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}
