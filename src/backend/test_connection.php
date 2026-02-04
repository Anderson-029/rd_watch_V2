<?php
// test_connection.php
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Conexión</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #f0f2f5;
            margin: 0;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .success {
            color: #059669;
            background: #ecfdf5;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .error {
            color: #dc2626;
            background: #fef2f2;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 1.5rem;
            margin: 0 0 1rem 0;
            color: #1f2937;
        }

        code {
            background: #f3f4f6;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .details {
            text-align: left;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .status-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            display: block;
        }
    </style>
</head>

<body>
    <div class="card">
        <?php try {
            require 'config.php';
            // Si llegamos aquí, conexión exitosa
            $ver = $pdo->query("SELECT version()")->fetchColumn();
            $verShort = explode(" ", $ver)[1] ?? 'Unknown';
            ?>
            <div class="success">
                <span class="status-icon">✅</span>
                <strong>Conexión Exitosa</strong>
            </div>
            <h1>Base de Datos Conectada</h1>
            <div class="details">
                <p><strong>Motor:</strong> PostgreSQL <?php echo htmlspecialchars($verShort); ?></p>
                <p><strong>Usuario:</strong> <?php echo htmlspecialchars($env['DB_USER']); ?></p>
                <p><strong>Base de Datos:</strong> <?php echo htmlspecialchars($env['DB_NAME']); ?></p>
                <p><strong>Host:</strong> <?php echo htmlspecialchars($env['DB_HOST']); ?></p>
            </div>
        <?php } catch (Throwable $e) { ?>
            <div class="error">
                <span class="status-icon">❌</span>
                <strong>Error de Conexión</strong>
            </div>
            <h1>Algo salió mal</h1>
            <div class="details">
                <p>El sistema reportó:</p>
                <code><?php echo htmlspecialchars($e->getMessage()); ?></code>
            </div>
        <?php } ?>
    </div>
</body>

</html>