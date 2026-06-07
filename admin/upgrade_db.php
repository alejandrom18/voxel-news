<?php
/**
 * upgrade_db.php - Actualización Automática de Base de Datos para VOXEL
 * 
 * Este script se encarga de:
 * 1. Conectarse a la base de datos MySQL de VOXEL de manera segura.
 * 2. Comprobar si las nuevas columnas `galeria` y `video_url` ya existen en la tabla `noticias`.
 * 3. Añadirlas en caliente de forma retrocompatible sin alterar o borrar las noticias existentes.
 * 4. Ofrecer la opción de auto-eliminación segura del archivo al finalizar para proteger la web.
 */

require_once '../conexion.php';

$mensaje = '';
$tipo_mensaje = 'info';
$completado = false;

// 1. Auto-eliminación segura por acción del usuario
if (isset($_GET['action']) && $_GET['action'] === 'self_destruct') {
    if (unlink(__FILE__)) {
        die("<h3>El script upgrade_db.php ha sido eliminado con éxito por seguridad. La base de datos ya está lista.</h3>");
    } else {
        $mensaje = "No se pudo eliminar el archivo automáticamente por permisos del servidor. Por favor, elimínalo manualmente en: admin/upgrade_db.php";
        $tipo_mensaje = 'error';
    }
}

// 2. Proceso de actualización
try {
    // A. Comprobar si la columna 'galeria' ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM noticias LIKE 'galeria'");
    $columna_galeria_existe = $stmt->fetch();

    if (!$columna_galeria_existe) {
        // Añadir columna galeria de tipo TEXT (para almacenar el JSON de nombres de archivos)
        $pdo->exec("ALTER TABLE noticias ADD COLUMN galeria TEXT NULL AFTER es_tendencia");
        $mensaje .= "• Columna <strong>`galeria`</strong> añadida con éxito.<br>";
    }

    // B. Comprobar si la columna 'video_url' ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM noticias LIKE 'video_url'");
    $columna_video_existe = $stmt->fetch();

    if (!$columna_video_existe) {
        // Añadir columna video_url de tipo VARCHAR(255) (para almacenar la URL de YouTube)
        $pdo->exec("ALTER TABLE noticias ADD COLUMN video_url VARCHAR(255) NULL AFTER galeria");
        $mensaje .= "• Columna <strong>`video_url`</strong> añadida con éxito.<br>";
    }

    if (empty($mensaje)) {
        $mensaje = "<strong>La base de datos ya estaba actualizada.</strong><br>Las columnas `galeria` y `video_url` ya existen en la tabla `noticias`. No se requirió ningún cambio.";
        $tipo_mensaje = 'info';
    } else {
        $mensaje = "<strong>¡Actualización Exitosa!</strong><br>" . $mensaje . "<br><em>Las columnas han sido creadas de forma 100% retrocompatible. Tus noticias actuales no han sufrido alteraciones.</em>";
        $tipo_mensaje = 'success';
    }
    
    $completado = true;
} catch (\PDOException $e) {
    $mensaje = "<strong>Error al actualizar la base de datos:</strong> " . htmlspecialchars($e->getMessage());
    $tipo_mensaje = 'error';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de DB | VOXEL</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #0b132b;
            color: #f4f6f9;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }

        .upgrade-card {
            max-width: 500px;
            background-color: #1c2541;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border-left: 5px solid #1215ca;
            text-align: center;
        }

        h1 {
            font-family: 'Oswald', sans-serif;
            font-size: 2rem;
            color: #ffffff;
            margin-top: 0;
            text-transform: uppercase;
        }

        .alert-box {
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: left;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            color: #34d399;
        }

        .alert-info {
            background-color: rgba(59, 130, 246, 0.15);
            border: 1px solid #3b82f6;
            color: #60a5fa;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #f87171;
        }

        .btn {
            display: inline-block;
            background-color: #1215ca;
            color: #ffffff;
            font-family: 'Oswald', sans-serif;
            font-size: 1.1rem;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 6px;
            margin-top: 15px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background-color: #0b0c9c;
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: #ef4444;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .warning-text {
            color: #eab308;
            font-size: 0.85rem;
            margin-top: 15px;
            display: block;
        }
    </style>
</head>
<body>

<div class="upgrade-card">
    <h1>Actualización de Base de Datos</h1>
    <p>Portal Informativo VOXEL - Incorporación de Multimedia Avanzada.</p>

    <div class="alert-box alert-<?= $tipo_mensaje ?>">
        <?= $mensaje ?>
    </div>

    <?php if ($completado): ?>
        <p>Por seguridad extrema, elimina este script de migración una vez que la base de datos esté lista para evitar lecturas innecesarias en el servidor.</p>
        
        <a href="upgrade_db.php?action=self_destruct" class="btn btn-danger">💥 Eliminar este Script (Auto-Destrucción)</a>
        <br>
        <a href="index.php" class="btn">🔑 Volver al Panel de Control</a>
        
        <span class="warning-text">⚠️ Al presionar el botón rojo, el archivo se borrará físicamente de forma instantánea.</span>
    <?php else: ?>
        <a href="upgrade_db.php" class="btn">🔄 Reintentar Actualización</a>
    <?php endif; ?>
</div>

</body>
</html>
