<?php
// 1. Cargamos el middleware de autenticación (valida que haya sesión activa)
require_once 'auth.php';

// 2. Subimos un nivel de carpeta para conectarnos a la base de datos
require_once '../conexion.php';

// 3. Traemos todas las noticias de la base de datos, las más recientes primero
$stmt = $pdo->query("SELECT * FROM noticias ORDER BY fecha_creacion DESC");
$noticias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | VOXEL</title>
    <!-- Usamos la misma fuente Oswald para consistencia de marca -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f4f6f9;
            --text-color: #1e1f1e;
            --primary-color: #1215ca; /* El azul corporativo de VOXEL */
            --accent-red: #ef4444;
            --border-color: #ddd;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 40px 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        /* Barra de administración para mostrar sesión activa y botón de cerrar */
        .admin-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #1e1f1e;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            border-left: 4px solid var(--primary-color);
        }

        .admin-bar a {
            color: var(--accent-red);
            text-decoration: none;
            font-weight: bold;
            font-family: 'Oswald', sans-serif;
            letter-spacing: 0.5px;
            transition: opacity 0.2s;
        }

        .admin-bar a:hover {
            opacity: 0.8;
        }

        h1 {
            font-family: 'Oswald', sans-serif;
            font-size: 2.5rem;
            margin-top: 0;
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Botón de Agregar Noticia */
        .btn-add {
            background-color: var(--primary-color);
            color: #fff;
            padding: 10px 20px;
            text-decoration: none;
            font-family: 'Oswald', sans-serif;
            font-size: 1.1rem;
            border-radius: 6px;
            transition: opacity 0.2s;
        }

        .btn-add:hover {
            opacity: 0.9;
        }

        /* Tabla de Noticias */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: #f8f9fa;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-size: 1rem;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background-color: #fdfdfd;
        }

        /* Etiquetas de Tendencia */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .badge-si {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-no {
            background-color: #f3f4f6;
            color: #374151;
        }

        /* Botones de Acción (Editar / Eliminar) */
        .action-btns a {
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85rem;
            margin-right: 5px;
            display: inline-block;
        }

        .btn-edit {
            background-color: #3b82f6;
            color: #fff;
        }

        .btn-delete {
            background-color: var(--accent-red);
            color: #fff;
        }

        .action-btns a:hover {
            opacity: 0.85;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Barra superior de sesión administrativa -->
    <div class="admin-bar">
        <span>Conectado como: <strong><?= htmlspecialchars($_SESSION['admin_nombre'] ?? $_SESSION['admin_username']) ?></strong></span>
        <a href="login.php?action=logout">🚪 Cerrar Sesión</a>
    </div>

    <h1>
        Panel de Control VOXEL
        <!-- Dejamos el enlace listo, aunque nuevo.php lo crearemos en el siguiente paso -->
        <a href="nuevo.php" class="btn-add">➕ Redactar Noticia</a>
    </h1>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Categoría</th>
                <th>¿Es Tendencia?</th>
                <th>Fecha de Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($noticias as $noticia): ?>
                <tr>
                    <td><?= $noticia['id'] ?></td>
                    <td><strong><?= htmlspecialchars($noticia['titulo']) ?></strong></td>
                    <td><?= htmlspecialchars($noticia['categoria']) ?></td>
                    <td>
                        <?php if ($noticia['es_tendencia'] == 1): ?>
                            <span class="badge badge-si">Sí</span>
                        <?php else: ?>
                            <span class="badge badge-no">No</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($noticia['fecha_creacion'])) ?></td>
                    <td class="action-btns">
                        <!-- Dejamos listos los enlaces para editar y eliminar apuntando al ID de la noticia -->
                        <a href="editar.php?id=<?= $noticia['id'] ?>" class="btn-edit">Editar</a>
                        <a href="eliminar.php?id=<?= $noticia['id'] ?>" class="btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta noticia?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>