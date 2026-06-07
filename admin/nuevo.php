<?php
// 1. Cargamos el middleware de autenticación (valida sesión activa)
require_once 'auth.php';

// 2. Cargamos la librería de utilidades para procesamiento de imágenes
require_once 'utils.php';

// 3. Importamos la conexión subiendo un nivel de carpeta
require_once '../conexion.php';

// Variable para guardar mensajes de error si los hay
$error = '';

// 2. DETECTOR DE ENVÍO: Verificamos si el usuario envió el formulario por método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Capturamos y limpiamos los textos de espacios vacíos iniciales y finales
    $titulo = trim($_POST['titulo']);
    $contenido = trim($_POST['contenido']);
    $categoria = $_POST['categoria'];
    $video_url = isset($_POST['video_url']) ? trim($_POST['video_url']) : '';
    
    // Si el checkbox de tendencia fue marcado, vale 1, de lo contrario vale 0
    $es_tendencia = isset($_POST['es_tendencia']) ? 1 : 0;
    
    // Inicializamos las variables de los nuevos campos multimedia
    $galeria_json = null;
    
    // Validación básica: Que no envíen campos obligatorios vacíos
    if (empty($titulo) || empty($contenido) || empty($categoria)) {
        $error = 'Por favor, rellena todos los campos obligatorios.';
    } else {
        
        // 4. PROCESO DE SUBIDA DE IMAGEN CON SEGURIDAD EXTREMA Y OPTIMIZACIÓN
        $nombre_imagen = 'default.jpg'; // Imagen por defecto por si no suben ninguna
        
        // Verificamos si el usuario seleccionó una imagen y no hay errores de subida
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            
            $ruta_temporal = $_FILES['imagen']['tmp_name']; // Ruta temporal del servidor
            $nombre_original = basename($_FILES['imagen']['name']); // Nombre original de la foto
            
            // Extraemos la extensión original de forma segura
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            
            // FILTRO 1: Whitelist estricta de extensiones de imagen permitidas
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($extension, $extensiones_permitidas)) {
                $error = 'El archivo de imagen no tiene un formato válido (solo se permiten JPG, JPEG, PNG y WEBP).';
            } else {
                // FILTRO 2: Inspección profunda de tipo MIME real para evitar evasiones de código (RCE)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_real = finfo_file($finfo, $ruta_temporal);
                finfo_close($finfo);

                $mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime_real, $mimes_permitidos)) {
                    $error = 'El contenido del archivo no corresponde a una imagen válida (MIME detectado: ' . htmlspecialchars($mime_real) . ').';
                }
            }
            
            // FILTRO 3: Si todo es válido, convertimos y optimizamos en formato WebP dinámicamente
            if (empty($error)) {
                // Generamos un nombre de archivo único forzando la extensión a .webp
                $nombre_imagen = time() . '_' . uniqid() . '.webp';
                $ruta_destino = '../uploads/' . $nombre_imagen;
                
                // Procesamos la imagen con nuestra librería GD
                if (!optimizarYConvertirImagenWebP($ruta_temporal, $ruta_destino, 1200)) {
                    // Fallback de seguridad en caso de fallo crítico en la librería de procesamiento GD:
                    // Intentamos moverla directamente renombrándola pero manteniendo la extensión de origen
                    $nombre_imagen = time() . '_' . uniqid() . '.' . $extension;
                    $ruta_destino_fallback = '../uploads/' . $nombre_imagen;
                    
                    if (!move_uploaded_file($ruta_temporal, $ruta_destino_fallback)) {
                        $error = 'Hubo un error al guardar la imagen en el servidor.';
                    }
                }
            }
        }

        // B. PROCESO DE SUBIDA DE GALERÍA (Múltiples imágenes opcionales)
        if (empty($error) && isset($_FILES['galeria']) && !empty($_FILES['galeria']['name'][0])) {
            $total_archivos = count($_FILES['galeria']['name']);
            $nombres_galeria = [];
            
            for ($i = 0; $i < $total_archivos; $i++) {
                if ($_FILES['galeria']['error'][$i] === UPLOAD_ERR_OK) {
                    $ruta_temporal_gal = $_FILES['galeria']['tmp_name'][$i];
                    $nombre_original_gal = basename($_FILES['galeria']['name'][$i]);
                    $extension_gal = strtolower(pathinfo($nombre_original_gal, PATHINFO_EXTENSION));
                    
                    // VALIDACIÓN 1: Whitelist de extensiones
                    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($extension_gal, $extensiones_permitidas)) {
                        $error = 'Uno de los archivos de la galería tiene un formato no válido (solo JPG, JPEG, PNG y WEBP).';
                        break;
                    }
                    
                    // VALIDACIÓN 2: Inspección profunda de tipo MIME real
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_real_gal = finfo_file($finfo, $ruta_temporal_gal);
                    finfo_close($finfo);
                    
                    $mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($mime_real_gal, $mimes_permitidos)) {
                        $error = 'Uno de los archivos de la galería no corresponde a una imagen válida.';
                        break;
                    }
                    
                    // VALIDACIÓN 3: Compresión e inyección WebP
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
            
            // Si procesamos imágenes secundarias con éxito, las guardamos como JSON
            if (!empty($nombres_galeria)) {
                $galeria_json = json_encode($nombres_galeria);
            }
        }
        
        // 5. GUARDAR EN LA BASE DE DATOS (Si no hay errores previos)
        if (empty($error)) {
            try {
                // Usamos una Consulta Preparada con marcadores por seguridad
                $stmt = $pdo->prepare("INSERT INTO noticias (titulo, contenido, categoria, imagen, es_tendencia, galeria, video_url) 
                                       VALUES (:titulo, :contenido, :categoria, :imagen, :es_tendencia, :galeria, :video_url)");
                
                // Ejecutamos la consulta asociando las variables reales
                $stmt->execute([
                    'titulo'       => $titulo,
                    'contenido'    => $contenido,
                    'categoria'    => $categoria,
                    'imagen'       => $nombre_imagen,
                    'es_tendencia' => $es_tendencia,
                    'galeria'      => $galeria_json,
                    'video_url'    => !empty($video_url) ? $video_url : null
                ]);
                
                // 5. REDIRECCIÓN AUTOMÁTICA al Panel de Control tras guardar
                header("Location: index.php");
                exit; // Detenemos la ejecución de este script
                
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
    <title>Redactar Noticia | VOXEL Admin</title>
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

        /* Elementos del Formulario */
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

        /* Checkbox de Tendencia */
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

        /* Botón de Publicar */
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
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ Volver al Panel de Control</a>
    
    <h1>Redactar Nueva Noticia</h1>

    <!-- Si hay errores, los mostramos en esta caja roja -->
    <?php if (!empty($error)): ?>
        <div class="error-box">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- IMPORTANTE: enctype="multipart/form-data" para permitir subida de imágenes -->
    <form action="nuevo.php" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label for="titulo">Titular de la Noticia *</label>
            <input type="text" id="titulo" name="titulo" placeholder="Ej. El nuevo parche de Gaming cambia el meta..." required>
        </div>

        <div class="form-group">
            <label for="categoria">Categoría *</label>
            <select id="categoria" name="categoria" required>
                <option value="">-- Selecciona una categoría --</option>
                <option value="internacional">Internacionales</option>
                <option value="deportes">Deportes</option>
                <option value="gaming">Gaming</option>
                <option value="IA">IA</option>
                <option value="finanzas">Finanzas</option>
            </select>
        </div>

        <div class="form-group">
            <label for="imagen">Seleccionar Imagen Principal * (JPG, PNG, WEBP)</label>
            <input type="file" id="imagen" name="imagen" accept="image/*">
        </div>

        <div class="form-group">
            <label for="galeria">Imágenes adicionales para Galería (Opcional - Selecciona varias a la vez)</label>
            <input type="file" id="galeria" name="galeria[]" accept="image/*" multiple>
        </div>

        <div class="form-group">
            <label for="video_url">URL de Video de YouTube (Opcional)</label>
            <input type="text" id="video_url" name="video_url" placeholder="Ej. https://www.youtube.com/watch?v=dQw4w9WgXcQ">
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" id="es_tendencia" name="es_tendencia" value="1">
            <label for="es_tendencia"><strong>¿Destacar como Tendencia del Día en el carrusel superior?</strong></label>
        </div>

        <div class="form-group">
            <label for="contenido">Cuerpo de la Noticia *</label>
            <textarea id="contenido" name="contenido" placeholder="Escribe aquí el contenido completo del artículo..." required></textarea>
        </div>

        <button type="submit" class="btn-submit">🚀 Publicar Noticia</button>
    </form>
</div>

</body>
</html>