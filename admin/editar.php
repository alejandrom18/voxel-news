<?php
// 1. Cargamos el middleware de autenticación (valida sesión activa)
require_once 'auth.php';

// 2. Cargamos la librería de utilidades para procesamiento de imágenes
require_once 'utils.php';

// 3. Importamos la conexión subiendo un nivel de carpeta
require_once '../conexion.php';

$error = '';

// 2. LECTURA INICIAL: Validamos que nos envíen un ID válido por la URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Buscamos la noticia actual para rellenar los campos del formulario
try {
    $stmt = $pdo->prepare("SELECT * FROM noticias WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $noticia = $stmt->fetch();
    
    // Si la noticia no existe en la base de datos, redirigimos al panel
    if (!$noticia) {
        header("Location: index.php");
        exit;
    }
} catch (\PDOException $e) {
    die("Error al buscar la noticia: " . $e->getMessage());
}

// 3. DETECTOR DE ACTUALIZACIÓN: Se ejecuta cuando envías el formulario modificado (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $titulo = trim($_POST['titulo']);
    $contenido = trim($_POST['contenido']);
    $categoria = $_POST['categoria'];
    $es_tendencia = isset($_POST['es_tendencia']) ? 1 : 0;
    $video_url = isset($_POST['video_url']) ? trim($_POST['video_url']) : '';
    
    if (empty($titulo) || empty($contenido) || empty($categoria)) {
        $error = 'Por favor, rellena todos los campos obligatorios.';
    } else {
        
        // Conservamos el nombre de la imagen actual por defecto
        $nombre_imagen = $noticia['imagen'];
        $galeria_json = $noticia['galeria'];
        
        // Verificamos si el usuario subió una NUEVA imagen para reemplazar la vieja
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            
            $ruta_temporal = $_FILES['imagen']['tmp_name'];
            $nombre_original = basename($_FILES['imagen']['name']);
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            
            // FILTRO 1: Whitelist estricta de extensiones de imagen permitidas
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($extension, $extensiones_permitidas)) {
                $error = 'El archivo de imagen no tiene un formato válido (solo se permiten JPG, JPEG, PNG y WEBP).';
            } else {
                // FILTRO 2: Inspección profunda de tipo MIME real (RCE protection)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_real = finfo_file($finfo, $ruta_temporal);
                finfo_close($finfo);

                $mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime_real, $mimes_permitidos)) {
                    $error = 'El contenido del archivo no corresponde a una imagen válida (MIME detectado: ' . htmlspecialchars($mime_real) . ').';
                }
            }
            
            // FILTRO 3: Si la imagen es válida, la optimizamos y la guardamos como WebP
            if (empty($error)) {
                // Generamos un nuevo nombre único con extensión .webp obligatoriamente
                $nueva_imagen = time() . '_' . uniqid() . '.webp';
                $ruta_destino = '../uploads/' . $nueva_imagen;
                
                // Procesamos y convertimos con la librería GD
                if (optimizarYConvertirImagenWebP($ruta_temporal, $ruta_destino, 1200)) {
                    // PASO CLAVE: Si se guardó la nueva con éxito, borramos la vieja del disco
                    if ($nombre_imagen !== 'default.jpg') {
                        $ruta_vieja = '../uploads/' . $nombre_imagen;
                        if (file_exists($ruta_vieja)) {
                            unlink($ruta_vieja); // Borra la foto antigua
                        }
                    }
                    // Actualizamos la variable con el nuevo nombre
                    $nombre_imagen = $nueva_imagen;
                } else {
                    // Fallback en caso de que falle la compresión GD
                    $nueva_imagen = time() . '_' . uniqid() . '.' . $extension;
                    $ruta_destino_fallback = '../uploads/' . $nueva_imagen;
                    
                    if (move_uploaded_file($ruta_temporal, $ruta_destino_fallback)) {
                        if ($nombre_imagen !== 'default.jpg') {
                            $ruta_vieja = '../uploads/' . $nombre_imagen;
                            if (file_exists($ruta_vieja)) {
                                unlink($ruta_vieja);
                            }
                        }
                        $nombre_imagen = $nueva_imagen;
                    } else {
                        $error = 'Error al subir la nueva imagen al servidor.';
                    }
                }
            }
        }

        // B. PROCESO DE REEMPLAZO DE LA GALERÍA DE IMÁGENES
        if (empty($error) && isset($_FILES['galeria']) && !empty($_FILES['galeria']['name'][0])) {
            $total_archivos = count($_FILES['galeria']['name']);
            $nombres_galeria = [];
            
            // Leemos la galería vieja para borrar físicamente todas sus imágenes de disco y liberar espacio
            $galeria_vieja = json_decode($noticia['galeria'], true);
            if (is_array($galeria_vieja)) {
                foreach ($galeria_vieja as $foto_vieja) {
                    $ruta_foto_vieja = '../uploads/' . $foto_vieja;
                    if (file_exists($ruta_foto_vieja)) {
                        unlink($ruta_foto_vieja); // Elimina de disco
                    }
                }
            }
            
            for ($i = 0; $i < $total_archivos; $i++) {
                if ($_FILES['galeria']['error'][$i] === UPLOAD_ERR_OK) {
                    $ruta_temporal_gal = $_FILES['galeria']['tmp_name'][$i];
                    $nombre_original_gal = basename($_FILES['galeria']['name'][$i]);
                    $extension_gal = strtolower(pathinfo($nombre_original_gal, PATHINFO_EXTENSION));
                    
                    // Whitelist
                    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($extension_gal, $extensiones_permitidas)) {
                        // Inspección de tipo MIME
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_real_gal = finfo_file($finfo, $ruta_temporal_gal);
                        finfo_close($finfo);
                        
                        $mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
                        if (in_array($mime_real_gal, $mimes_permitidos)) {
                            $nombre_imagen_gal = time() . '_gal_' . $i . '_' . uniqid() . '.webp';
                            $ruta_destino_gal = '../uploads/' . $nombre_imagen_gal;
                            
                            if (optimizarYConvertirImagenWebP($ruta_temporal_gal, $ruta_destino_gal, 1200)) {
                                $nombres_galeria[] = $nombre_imagen_gal;
                            } else {
                                // Fallback
                                $nombre_imagen_gal = time() . '_gal_' . $i . '_' . uniqid() . '.' . $extension_gal;
                                $ruta_destino_fallback_gal = '../uploads/' . $nombre_imagen_gal;
                                if (move_uploaded_file($ruta_temporal_gal, $ruta_destino_fallback_gal)) {
                                    $nombres_galeria[] = $nombre_imagen_gal;
                                }
                            }
                        }
                    }
                }
            }
            
            if (!empty($nombres_galeria)) {
                $galeria_json = json_encode($nombres_galeria);
            } else {
                $galeria_json = null;
            }
        }
        
        // 4. GUARDAR CAMBIOS (SQL UPDATE)
        if (empty($error)) {
            try {
                // Sentencia UPDATE para actualizar la fila existente con las nuevas columnas multimedia
                $stmt_update = $pdo->prepare("UPDATE noticias 
                                             SET titulo = :titulo, contenido = :contenido, categoria = :categoria, imagen = :imagen, es_tendencia = :es_tendencia, galeria = :galeria, video_url = :video_url 
                                             WHERE id = :id");
                
                $stmt_update->execute([
                    'titulo'       => $titulo,
                    'contenido'    => $contenido,
                    'categoria'    => $categoria,
                    'imagen'       => $nombre_imagen,
                    'es_tendencia' => $es_tendencia,
                    'galeria'      => $galeria_json,
                    'video_url'    => !empty($video_url) ? $video_url : null,
                    'id'           => $id
                ]);
                
                // Redireccionamos tras guardar los cambios con éxito
                header("Location: index.php");
                exit;
                
            } catch (\PDOException $e) {
                $error = 'Error en la base de datos: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Noticia | VOXEL Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f4f6f9;
            --text-color: #1e1f1e;
            --primary-color: #1215ca;
            --error-color: #ef4444;
            --border-color: #ccc;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 40px 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        h1 {
            font-family: 'Oswald', sans-serif;
            font-size: 2.2rem;
            margin-top: 0;
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 10px;
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
        }

        .error-box {
            background-color: #fee2e2;
            color: var(--error-color);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        textarea {
            height: 150px;
            resize: vertical;
            font-family: inherit;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            padding: 12px 25px;
            font-family: 'Oswald', sans-serif;
            font-size: 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            transition: opacity 0.2s;
        }

        .btn-submit:hover {
            opacity: 0.9;
        }

        /* Vista previa de imagen actual */
        .preview-box {
            margin-top: 10px;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 6px;
            background-color: #fafafa;
        }

        .preview-box img {
            max-width: 150px;
            display: block;
            margin-top: 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ Volver al Panel de Control</a>
    
    <h1>Editar Noticia</h1>

    <?php if (!empty($error)): ?>
        <div class="error-box">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form action="editar.php?id=<?= $id ?>" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label for="titulo">Titular de la Noticia *</label>
            <!-- Rellenamos el valor por defecto con htmlspecialchars() -->
            <input type="text" id="titulo" name="titulo" value="<?= htmlspecialchars($noticia['titulo']) ?>" required>
        </div>

        <div class="form-group">
            <label for="categoria">Categoría *</label>
            <select id="categoria" name="categoria" required>
                <!-- Comparamos el valor de la base de datos para marcar la opción como 'selected' -->
                <option value="internacional" <?= $noticia['categoria'] === 'internacional' ? 'selected' : '' ?>>Internacionales</option>
                <option value="deportes" <?= $noticia['categoria'] === 'deportes' ? 'selected' : '' ?>>Deportes</option>
                <option value="gaming" <?= $noticia['categoria'] === 'gaming' ? 'selected' : '' ?>>Gaming</option>
                <option value="IA" <?= $noticia['categoria'] === 'IA' ? 'selected' : '' ?>>IA</option>
                <option value="finanzas" <?= $noticia['categoria'] === 'finanzas' ? 'selected' : '' ?>>Finanzas</option>
            </select>
        </div>

        <div class="form-group">
            <label for="imagen">Reemplazar Imagen Principal (Dejar vacío para conservar la actual)</label>
            <input type="file" id="imagen" name="imagen" accept="image/*">
            
            <div class="preview-box">
                <span><strong>Imagen principal actual:</strong> <?= htmlspecialchars($noticia['imagen']) ?></span>
                <img src="../uploads/<?= htmlspecialchars($noticia['imagen']) ?>" alt="Vista previa actual">
            </div>
        </div>

        <div class="form-group">
            <label for="galeria">Reemplazar Galería de Imágenes (Opcional - Selecciona varias a la vez)</label>
            <input type="file" id="galeria" name="galeria[]" accept="image/*" multiple>
            
            <!-- Vista previa de las miniaturas actuales de la galería -->
            <?php 
            $galeria_actual = json_decode($noticia['galeria'], true);
            if (is_array($galeria_actual) && !empty($galeria_actual)): 
            ?>
                <div class="preview-box" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                    <span style="width: 100%; display: block;"><strong>Galería actual:</strong></span>
                    <?php foreach ($galeria_actual as $foto): ?>
                        <img src="../uploads/<?= htmlspecialchars($foto) ?>" style="max-width: 80px; height: 60px; object-fit: cover; border-radius: 4px;" alt="Miniatura galería">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="video_url">URL de Video de YouTube (Opcional)</label>
            <input type="text" id="video_url" name="video_url" value="<?= htmlspecialchars($noticia['video_url'] ?? '') ?>" placeholder="Ej. https://www.youtube.com/watch?v=dQw4w9WgXcQ">
        </div>

        <div class="form-group checkbox-group">
            <!-- Comparamos si es 1 para marcar la casilla como 'checked' -->
            <input type="checkbox" id="es_tendencia" name="es_tendencia" value="1" <?= $noticia['es_tendencia'] == 1 ? 'checked' : '' ?>>
            <label for="es_tendencia"><strong>¿Destacar como Tendencia del Día en el carrusel superior?</strong></label>
        </div>

        <div class="form-group">
            <label for="contenido">Cuerpo de la Noticia *</label>
            <textarea id="contenido" name="contenido" required><?= htmlspecialchars($noticia['contenido']) ?></textarea>
        </div>

        <button type="submit" class="btn-submit">💾 Guardar Cambios</button>
    </form>
</div>

</body>
</html>