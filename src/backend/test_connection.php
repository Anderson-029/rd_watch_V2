<?php
/**
 * UTILITARIO: VERIFICADOR DE CONECTIVIDAD (DIAGNOSTIC UI)
 * ---------------------------------------------------------
 * Propósito: Provee una interfaz visual inmediata para diagnosticar el estado 
 * de la conexión entre el servidor web (PHP) y el motor de base de datos (PostgreSQL).
 * 
 * Lógica: 
 * 1. Intenta cargar el motor de configuración central (config.php).
 * 2. Ejecuta una consulta de metadatos (SELECT version()) para validar operatividad.
 * 3. Renderiza un reporte estilizado con CSS moderno indicando éxito o fallo detallado.
 * 
 * Uso: Es la primera herramienta a consultar tras despliegues en nuevos entornos 
 * o cambios en las variables de entorno (.env).
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RD-Watch | Estado del Backend</title>
    <style>
        :root {
            --success-bg: #ecfdf5;
            --success-text: #059669;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f0f2f5 0%, #e2e8f0 100%);
            margin: 0;
        }

        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            width: 90%;
            max-width: 450px;
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .status-badge.success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .status-badge.error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        h1 {
            font-size: 1.75rem;
            margin: 0 0 1rem 0;
            color: #111827;
            letter-spacing: -0.025em;
        }

        .details {
            text-align: left;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #475569;
            line-height: 1.6;
        }

        .details p {
            margin: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.5rem;
        }

        .details p:last-child {
            border: none;
        }

        code {
            display: block;
            background: #1e293b;
            color: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            text-align: left;
            margin-top: 1rem;
            overflow-x: auto;
        }

        .status-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }
    </style>
</head>

<body>
    <div class="card">
        <?php try {
            require 'config.php';

            /**
             * VERIFICACIÓN ACTIVA:
             * Solicitamos la versión del motor PostgreSQL para confirmar transacción exitosa.
             */
            $ver = $pdo->query("SELECT version()")->fetchColumn();
            $verShort = explode(" ", $ver)[1] ?? 'Recuperada';
            ?>

            <div class="status-badge success">
                <span>●</span> Conectado al Servidor
            </div>

            <h1>Infraestructura Lista</h1>
            <p style="color: #64748b">El núcleo de configuración y la base de datos operan correctamente.</p>

            <div class="details">
                <p><strong>Motor:</strong> PostgreSQL <?php echo htmlspecialchars($verShort); ?></p>
                <p><strong>Host:</strong> <?php echo htmlspecialchars($env['DB_HOST']); ?></p>
                <p><strong>Estado:</strong> Transaccional / Operativo</p>
                <p><strong>Sesión:</strong> Inicializada correctamente</p>
            </div>

        <?php } catch (Throwable $e) { ?>

            <div class="status-badge error">
                <span>●</span> Error Crítico
            </div>

            <h1>Falla de Infraestructura</h1>
            <p style="color: #64748b">No se pudo establecer comunicación con el motor de base de datos.</p>

            <div class="details">
                <p><strong>Causa reportada por PDO:</strong></p>
                <code><?php echo htmlspecialchars($e->getMessage()); ?></code>
            </div>

            <p style="font-size: 0.875rem; color: #94a3b8; margin-top: 1.5rem;">
                Revise sus variables en <strong>backend/.env</strong>
            </p>

        <?php } ?>
    </div>
</body>

</html>