<?php
/**
 * API: CONFIGURACIÓN PARA PAGOS BANCARIOS
 * ---------------------------------------------------------
 * Propósito: Facilita al cliente los datos necesarios para realizar transferencias 
 * bancarias directas. Este endpoint es consumido durante el flujo de checkout 
 * cuando se selecciona el método de pago 'Transferencia'.
 * 
 * Estado de Implementación:
 * Actualmente utiliza valores estáticos (Hardcoded). 
 * RECOMENDACIÓN: Mover estos parámetros a una tabla `tab_Config_Pagos` para permitir 
 * cambios rápidos desde el panel administrativo sin tocar el código fuente.
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';

// 🛡️ BARRERA DE SEGURIDAD: Solo usuarios autenticados pueden ver datos para transferencia.
requireLogin();

// Verificación de integridad de la base de datos (aunque los datos sean estáticos, config.php es necesario)
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: No se pudo cargar el motor de configuración']);
    exit;
}

/**
 * ==========================================
 * 🏦 DATOS DEL NEGOCIO (RELOJERÍA DURÁN)
 * ==========================================
 * Estos datos deben ser exactos para evitar devoluciones bancarias.
 */
echo json_encode([
    'ok' => true,
    'banco' => [
        'nombre' => 'Bancolombia',
        'tipo_cuenta' => 'Ahorros',
        'numero_cuenta' => '518-000123-45',
        'titular' => 'Relojería Durán SAS',
        'nit_o_llave' => 'relojeria.duran@negocio', // Alias o Llave RED BANCO
        'instrucciones' => 'Por favor, envíe el comprobante de pago por el formulario de la orden para validar su pedido.'
    ]
]);