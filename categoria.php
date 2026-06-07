<?php 
// 1. Conectamos la base de datos importando conexion.php
require_once 'conexion.php';

// 1.B ENRUTADOR DINÁMICO PARA SOCIAL SHARING & URL ÚNICA
$noticia_compartida = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_compartida = (int)$_GET['id'];
    $stmt_compartida = $pdo->prepare("SELECT * FROM noticias WHERE id = :id LIMIT 1");
    $stmt_compartida->execute(['id' => $id_compartida]);
    $noticia_compartida = $stmt_compartida->fetch();
}

// 2. OBTENEMOS LA CATEGORÍA POR URL Y LA VALIDAMOS
// Si no se especifica ninguna, asignamos 'deportes' como fallback seguro.
$categoria_actual = isset($_GET['nombre']) ? trim($_GET['nombre']) : 'deportes';

// Lista de categorías permitidas en nuestro sistema
$categorias_validas = ['IA', 'finanzas', 'deportes', 'gaming', 'internacional'];

if (!in_array($categoria_actual, $categorias_validas)) {
    // Si meten una categoría extraña por la URL, los redirigimos al inicio
    header("Location: index.php");
    exit();
}

// 3. LÓGICA DE PAGINACIÓN DINÁMICA
// Definimos cuántas noticias mostrar por página en el listado horizontal
$noticias_por_pagina = 4;

// Capturamos el número de página actual desde la URL (p.ej., categoria.php?nombre=deportes&p=2)
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) {
    $pagina_actual = 1;
}

// Calculamos el desfase (OFFSET) para la consulta SQL
$offset = ($pagina_actual - 1) * $noticias_por_pagina;

// 4. CONSULTA 1: Traemos las noticias de esta categoría correspondientes a esta página
// Usamos bindValue con PDO de forma sumamente segura para prevenir inyección SQL.
$stmt = $pdo->prepare("SELECT * FROM noticias WHERE categoria = :categoria ORDER BY fecha_creacion DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':categoria', $categoria_actual, PDO::PARAM_STR);
$stmt->bindValue(':limit', $noticias_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$noticias = $stmt->fetchAll();

// 5. CONSULTA 2: Contamos el total de noticias en esta categoría para calcular el total de páginas
$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE categoria = :categoria");
$stmt_total->execute(['categoria' => $categoria_actual]);
$total_noticias = $stmt_total->fetchColumn();

// Calculamos el total de páginas necesarias
$total_paginas = ceil($total_noticias / $noticias_por_pagina);

/**
 * FUNCIÓN AUXILIAR DE SEGURIDAD PARA IMÁGENES:
 * Si la noticia no tiene imagen cargada o la columna está vacía, intenta extraer la miniatura de YouTube si hay un video.
 * De lo contrario, devuelve 'uploads/default.jpg'.
 */
function obtenerImagenReal($nombreImagen, $videoUrl = '') {
    // Si hay una imagen personalizada real (que no sea el marcador de posición por defecto)
    if (!empty($nombreImagen) && $nombreImagen !== 'default.jpg' && $nombreImagen !== 'default.png') {
        return 'uploads/' . $nombreImagen;
    }
    if (!empty($videoUrl)) {
        $regExp = '/^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/';
        if (preg_match($regExp, $videoUrl, $matches)) {
            if (strlen($matches[2]) === 11) {
                return "https://img.youtube.com/vi/" . $matches[2] . "/hqdefault.jpg";
            }
        }
    }
    return 'uploads/default.jpg';
}

/**
 * FUNCIÓN AUXILIAR DRY PARA RENDERIZAR EL ALMACÉN DE DATOS OCULTO DE CADA NOTICIA:
 * Inyecta de forma segura el ID, título, categoría, fecha, imagen principal, cuerpo
 * de texto y los nuevos campos avanzados (galería y url de video) para que JavaScript
 * los capture limpiamente y active el modal.
 */
function renderDataStore($noticia) {
    echo '<div class="article-data-store" style="display: none;">';
    echo '<span class="data-id">' . $noticia['id'] . '</span>';
    echo '<span class="data-title">' . htmlspecialchars($noticia['titulo']) . '</span>';
    echo '<span class="data-category">' . htmlspecialchars($noticia['categoria']) . '</span>';
    echo '<span class="data-date">' . date('d/m/Y H:i', strtotime($noticia['fecha_creacion'])) . '</span>';
    echo '<span class="data-image">' . obtenerImagenReal($noticia['imagen'], $noticia['video_url'] ?? '') . '</span>';
    echo '<div class="data-body">' . nl2br(htmlspecialchars($noticia['contenido'])) . '</div>';
    echo '<span class="data-video">' . htmlspecialchars($noticia['video_url'] ?? '') . '</span>';
    echo '<span class="data-gallery">' . htmlspecialchars($noticia['galeria'] ?? '') . '</span>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Etiquetas Meta SEO Dinámicas por Categoría -->
    <?php if ($noticia_compartida): ?>
        <title><?= htmlspecialchars($noticia_compartida['titulo']) ?> | VOXEL</title>
        <meta name="description" content="<?= htmlspecialchars(mb_strimwidth(strip_tags($noticia_compartida['contenido']), 0, 160, '...')) ?>">
        <meta name="keywords" content="voxel, noticias, <?= htmlspecialchars($noticia_compartida['categoria']) ?>, actualidad">
    <?php else: ?>
        <title><?= ($categoria_actual == 'IA') ? 'Inteligencia Artificial' : ucfirst($categoria_actual) ?> | VOXEL</title>
        <meta name="description" content="Lee las últimas noticias de <?= ($categoria_actual == 'IA') ? 'Inteligencia Artificial' : htmlspecialchars($categoria_actual) ?> en VOXEL. Artículos redactados al instante sobre las tendencias globales del momento.">
        <meta name="keywords" content="voxel, noticias, <?= htmlspecialchars($categoria_actual) ?>, actualidad, tendencias, lectura">
    <?php endif; ?>
    <meta name="author" content="Redacción VOXEL">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph para optimizar la compartición en redes sociales -->
    <meta property="og:type" content="article">
    <?php if ($noticia_compartida): ?>
        <?php 
            $img_compartida = obtenerImagenReal($noticia_compartida['imagen'], $noticia_compartida['video_url'] ?? '');
            $img_og = (strpos($img_compartida, 'http') === 0) ? $img_compartida : 'https://voxel.42web.io/' . $img_compartida;
        ?>
        <meta property="og:title" content="<?= htmlspecialchars($noticia_compartida['titulo']) ?> | VOXEL">
        <meta property="og:description" content="<?= htmlspecialchars(mb_strimwidth(strip_tags($noticia_compartida['contenido']), 0, 160, '...')) ?>">
        <meta property="og:image" content="<?= $img_og ?>">
        <meta property="og:url" content="https://voxel.42web.io/categoria.php?nombre=<?= htmlspecialchars($categoria_actual) ?>&id=<?= $noticia_compartida['id'] ?>">
    <?php else: ?>
        <meta property="og:title" content="<?= ($categoria_actual == 'IA') ? 'Inteligencia Artificial' : ucfirst($categoria_actual) ?> | VOXEL">
        <meta property="og:description" content="Mantente informado con lo último de <?= htmlspecialchars($categoria_actual) ?> en VOXEL. Reportajes exclusivos con interactividad premium.">
        <meta property="og:image" content="https://voxel.42web.io/voxel.jpg">
        <meta property="og:url" content="https://voxel.42web.io/categoria.php?nombre=<?= htmlspecialchars($categoria_actual) ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap" rel="stylesheet">
    
    <!-- Cargamos style.css con el Cache Buster v=2.2 para refrescar los nuevos estilos del listado -->
    <link rel="stylesheet" href="style.css?v=2.4">
</head>

<body>

    <!-- MENÚ SUPERIOR DE NAVEGACIÓN -->
    <header class="header">
        <div class="logo">
            <!-- Redirecciona a la portada al hacer clic en el logotipo animado -->
            <a href="index.php" style="text-decoration: none;">
                <svg width="180" height="45" viewBox="0 15 400 70" xmlns="http://www.w3.org/2000/svg">
                    <style>
                        @keyframes colorSwapText {
                            0%, 45% { fill: #1e1f1e; }
                            50%, 95% { fill: #1215ca; }
                            100% { fill: #1e1f1e; }
                        }

                        @keyframes colorSwapX {
                            0%, 45% { fill: #1215ca; }
                            50%, 95% { fill: #1e1f1e; }
                            100% { fill: #1215ca; }
                        }

                        .voxel-text {
                            font-family: 'Impact', 'Arial Black', sans-serif;
                            font-size: 85px;
                            font-weight: 900;
                            font-style: italic;
                            fill: #1e1f1e;
                            letter-spacing: -2px;
                            animation: colorSwapText 4s infinite ease-in-out;
                        }

                        .voxel-x {
                            fill: #1215ca;
                            animation: colorSwapX 4s infinite ease-in-out;
                        }
                    </style>
                    <text x="5" y="80" class="voxel-text">
                        VO<tspan class="voxel-x">X</tspan>EL
                    </text>
                </svg>
            </a>
        </div>

        <input type="checkbox" id="menu-toggle" class="menu-toggle">
        <label for="menu-toggle" class="menu-icon">
            <svg width="28" height="20" viewBox="0 0 28 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="28" height="4" rx="2" fill="var(--font-color)" />
                <rect y="8" width="28" height="4" rx="2" fill="var(--font-color)" />
                <rect y="16" width="28" height="4" rx="2" fill="var(--font-color)" />
            </svg>
        </label>

        <!-- 
           NAVEGACIÓN DINÁMICA CON CLASE ACTIVE:
           Comprobamos mediante una expresión PHP inline si la categoría del menú coincide 
           con $categoria_actual. Si es verdadero, inyecta la clase 'active', la cual le da
           un color azul y un subrayado permanente a la opción en la que nos encontramos.
        -->
        <nav class="nav">
            <ul class="nav-links">
                <li><a href="categoria.php?nombre=internacional" class="<?= ($categoria_actual == 'internacional') ? 'active' : '' ?>">Internacionales</a></li>
                <li><a href="categoria.php?nombre=deportes" class="<?= ($categoria_actual == 'deportes') ? 'active' : '' ?>">Deportes</a></li>
                <li><a href="categoria.php?nombre=gaming" class="<?= ($categoria_actual == 'gaming') ? 'active' : '' ?>">Gaming</a></li>
                <li><a href="categoria.php?nombre=IA" class="<?= ($categoria_actual == 'IA') ? 'active' : '' ?>">IA</a></li>
                <li><a href="categoria.php?nombre=finanzas" class="<?= ($categoria_actual == 'finanzas') ? 'active' : '' ?>">Finanzas</a></li>
            </ul>
        </nav>
    </header>

    <main class="main-container">
        
        <!-- Gran Título Centrado del Boceto (Nombre de la Categoría) -->
        <h1 class="category-page-title"><?= ($categoria_actual == 'IA') ? 'Inteligencia Artificial' : ucfirst($categoria_actual) ?></h1>

        <!-- CUADRÍCULA DEL BOCETO EN DOS COLUMNAS -->
        <div class="category-layout">
            
            <!-- COLUMNA IZQUIERDA (Lista de Noticias) -->
            <section class="feed-category">
                <?php if (count($noticias) > 0): ?>
                    
                    <?php foreach ($noticias as $noticia): ?>
                        <!-- Tarjeta horizontal del Boceto (Imagen izquierda | Título e Info derecha) -->
                        <article class="news-card-horizontal clickable-article">
                            <!-- Almacén de datos dinámico unificado -->
                            <?php renderDataStore($noticia); ?>
                            
                            <!-- Foto horizontal de la noticia -->
                            <img src="<?= obtenerImagenReal($noticia['imagen'], $noticia['video_url'] ?? '') ?>" alt="Foto noticia" class="card-horizontal-img">
                            
                            <!-- Información y Título a la derecha -->
                            <div class="card-horizontal-info">
                                <span class="tag" style="align-self: flex-start;"><?= $noticia['categoria'] ?></span>
                                <h3><?= htmlspecialchars($noticia['titulo']) ?></h3>
                                <!-- Truncado horizontal a 150 caracteres para extracto visual -->
                                <p><?= mb_strimwidth(htmlspecialchars($noticia['contenido']), 0, 150, "...") ?></p>
                                <span class="date-tag">Publicado el <?= date('d/m/Y', strtotime($noticia['fecha_creacion'])) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <!-- 
                       BLOQUE DE PAGINACIÓN DINÁMICA (Boceto):
                       Muestra botones de ANTERIOR, SIGUIENTE y NÚMEROS DE PÁGINAS adaptables.
                    -->
                    <div class="pagination">
                        <!-- Botón Anterior -->
                        <?php if ($pagina_actual > 1): ?>
                            <a href="categoria.php?nombre=<?= $categoria_actual ?>&p=<?= $pagina_actual - 1 ?>" class="pag-btn">&laquo; Anterior</a>
                        <?php else: ?>
                            <span class="pag-btn disabled">&laquo; Anterior</span>
                        <?php endif; ?>

                        <!-- Números de Páginas Circulares -->
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <a href="categoria.php?nombre=<?= $categoria_actual ?>&p=<?= $i ?>" 
                               class="pag-number <?= ($i == $pagina_actual) ? 'active-pag' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <!-- Botón Siguiente -->
                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="categoria.php?nombre=<?= $categoria_actual ?>&p=<?= $pagina_actual + 1 ?>" class="pag-btn">Siguiente &raquo;</a>
                        <?php else: ?>
                            <span class="pag-btn disabled">Siguiente &raquo;</span>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <!-- Mensaje amigable en caso de no contar con redactores activos en esta sección -->
                    <div style="background-color: #ffffff; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                        <h3 style="font-family: var(--font); font-size: 1.6rem; color: var(--color-third); margin-bottom: 10px;">¡Sección en redacción!</h3>
                        <p style="color: #666; font-family: Arial, sans-serif;">Actualmente no se han redactado noticias en esta categoría. Te invitamos a volver pronto.</p>
                        <a href="index.php" class="pag-btn" style="display: inline-block; margin-top: 20px;">Volver al Inicio</a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- COLUMNA DERECHA (Barra de Publicidad Vertical del Boceto) -->
            <aside class="sidebar-category">
                <div class="ad-vertical">
                    <!-- Banner publicitario de 300x600 px en un color azul a juego con el diseño del sitio -->
                    <img src="https://placehold.co/300x600/1215ca/ffffff?text=Publicidad+Vertical+300x600"
                        alt="Anuncio publicitario lateral" style="max-width: 100%; height: auto; border-radius: 12px;">
                </div>
            </aside>
            
        </div>
    </main>

    <!-- FOOTER DE PÁGINA -->
    <footer class="footer">
        <div class="footer-content" style="text-align: center; padding: 20px;">
            <p>&copy; 2026 VOXEL. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- MODAL DE LECTURA E INTERACTIVIDAD PREMIUM (100% REUTILIZADO Y MEJORADO) -->
    <div id="article-modal" class="modal-overlay">
        <div class="modal-container">
            <!-- Botón de cierre superior (X) - Fijo en la esquina del modal -->
            <button class="modal-close" id="modal-close-btn">&times;</button>
            
            <!-- ÁREA DE SCROLL COMPLETO (Imagen + Texto fluyen juntos de forma natural) -->
            <div class="modal-scroll-area">
                
                <!-- 1. Cabecera Multimedia (Con imagen cover encajonada y botones a los lados) -->
                <div class="modal-media-wrapper">
                    <!-- Flecha Izquierda: Ubicada a la izquierda de la imagen -->
                    <button class="slider-btn prev-btn" id="slider-prev-btn">&#10094;</button>
                    
                    <img id="modal-article-img" src="" alt="Multimedia de la noticia">
                    
                    <!-- NUEVO: Contenedor responsivo para el reproductor de video de YouTube colocado arriba -->
                    <div id="modal-video-wrapper" style="display: none; width: 100%; height: 100%; border-radius: 12px; overflow: hidden; position: relative;"></div>
                    
                    <!-- Contador de diapositivas flotante -->
                    <div class="slider-counter" id="slider-counter" style="display: none;">1 / 1</div>
                    
                    <!-- Flecha Derecha: Ubicada a la derecha de la imagen -->
                    <button class="slider-btn next-btn" id="slider-next-btn">&#10095;</button>
                </div>

                <!-- 2. Cuerpo del Artículo (Fluye en el mismo scroll general del modal) -->
                <div class="modal-body-wrapper">
                    <span class="tag" id="modal-article-tag">Categoría</span>
                    <h2 id="modal-article-title">Título del Artículo</h2>
                    
                    <div class="modal-meta">
                        <span>Publicado el: <strong id="modal-article-date">00/00/0000</strong></span>
                        <span> • Redacción VOXEL</span>
                    </div>

                    <!-- NUEVO: Botones de compartir en Redes Sociales -->
                    <div class="share-bar">
                        <span>Compartir:</span>
                        <a href="#" class="share-btn btn-facebook" id="share-fb" target="_blank">Facebook</a>
                        <a href="#" class="share-btn btn-twitter" id="share-tw" target="_blank">X (Twitter)</a>
                        <a href="#" class="share-btn btn-whatsapp" id="share-wa" target="_blank">WhatsApp</a>
                        <button class="share-btn btn-instagram" id="share-ig">Instagram</button>
                        <button class="share-btn btn-tiktok" id="share-tk">TikTok</button>
                        <button class="share-btn btn-copy" id="share-copy">Copiar Enlace</button>
                    </div>
                    
                    <!-- Contenedor del texto de lectura cómodo (Arial, justificado) -->
                    <div class="modal-text-content" id="modal-article-body">
                        Cargando contenido...
                    </div>

                    <!-- 3. Espacio Publicitario al Final del Artículo (Monetización Premium) -->
                    <div class="modal-ad-banner">
                        <img src="https://placehold.co/728x90/00F0FF/0B132B?text=Anuncio+Patrocinado+-+Gracias+por+leer+VOXEL" 
                             alt="Publicidad en lectura" class="modal-ad-img">
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <?php if ($noticia_compartida): ?>
    <!-- Elemento invisible de respaldo para asegurar que el router JS pueda abrirlo
          si la noticia compartida es antigua y no se renderizó en el listado actual -->
    <div class="clickable-article" id="shared-article-fallback" style="display:none;">
        <div class="article-data-store">
            <span class="data-id"><?= $noticia_compartida['id'] ?></span>
            <span class="data-title"><?= htmlspecialchars($noticia_compartida['titulo']) ?></span>
            <span class="data-category"><?= htmlspecialchars($noticia_compartida['categoria']) ?></span>
            <span class="data-date"><?= date('d/m/Y H:i', strtotime($noticia_compartida['fecha_creacion'])) ?></span>
            <span class="data-image"><?= obtenerImagenReal($noticia_compartida['imagen'], $noticia_compartida['video_url'] ?? '') ?></span>
            <div class="data-body"><?= nl2br(htmlspecialchars($noticia_compartida['contenido'])) ?></div>
            <span class="data-video"><?= htmlspecialchars($noticia_compartida['video_url'] ?? '') ?></span>
            <span class="data-gallery"><?= htmlspecialchars($noticia_compartida['galeria'] ?? '') ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- =======================================================================
         JAVASCRIPT DE CONTROL PARA EL MODAL DE LECTURA (100% Compatible)
         ======================================================================= -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Seleccionamos los elementos clave del Modal
        const modal = document.getElementById('article-modal');
        const closeBtn = document.getElementById('modal-close-btn');
        const modalImg = document.getElementById('modal-article-img');
        const modalTag = document.getElementById('modal-article-tag');
        const modalTitle = document.getElementById('modal-article-title');
        const modalDate = document.getElementById('modal-article-date');
        const modalBody = document.getElementById('modal-article-body');

        // Seleccionamos todas las tarjetas horizontales marcadas como clicables
        const articles = document.querySelectorAll('.clickable-article');

        // Por cada elemento clicable, agregamos un detector de clics
        articles.forEach(article => {
            article.addEventListener('click', () => {
                
                // 1. Buscamos el almacén de datos oculto '.article-data-store' dentro de esta tarjeta
                const dataStore = article.querySelector('.article-data-store');
                if (!dataStore) return;
                
                // 2. Extraemos los textos de forma 100% limpia y sin roturas de comillas
                const id = dataStore.querySelector('.data-id').textContent;
                const titulo = dataStore.querySelector('.data-title').textContent;
                const contenido = dataStore.querySelector('.data-body').innerHTML; // Conserva <br> generados por PHP
                const imagen = dataStore.querySelector('.data-image').textContent;
                const categoria = dataStore.querySelector('.data-category').textContent;
                const fecha = dataStore.querySelector('.data-date').textContent;
                const videoUrl = dataStore.querySelector('.data-video') ? dataStore.querySelector('.data-video').textContent : '';
                const galleryJson = dataStore.querySelector('.data-gallery') ? dataStore.querySelector('.data-gallery').textContent : '';

                // 3. Llenamos el modal dinámicamente con los datos correspondientes
                modalTag.textContent = categoria;
                modalTitle.textContent = titulo;
                modalDate.textContent = fecha;
                modalBody.innerHTML = contenido; // Inyectamos el texto limpio formateado

                // 4. HISTORIAL DE URL DINÁMICO (History API)
                // Cambia la URL en la barra de direcciones al abrir la noticia sin recargar la página
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('id', id);
                window.history.pushState({ id: id }, '', currentUrl);

                // 5. CONFIGURACIÓN DE BOTONES DE COMPARTIR SOCIAL
                const shareUrl = window.location.origin + window.location.pathname + '?nombre=<?= htmlspecialchars($categoria_actual) ?>&id=' + id;
                const shareTitle = encodeURIComponent(titulo);
                
                document.getElementById('share-fb').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl);
                document.getElementById('share-tw').href = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(shareUrl) + '&text=' + shareTitle;
                document.getElementById('share-wa').href = 'https://api.whatsapp.com/send?text=' + shareTitle + '%20' + encodeURIComponent(shareUrl);
                
                // Botón inteligente de copiar enlace
                const copyBtn = document.getElementById('share-copy');
                copyBtn.onclick = (e) => {
                    e.preventDefault();
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        const originalText = copyBtn.innerText;
                        copyBtn.innerText = '¡Copiado! ✓';
                        setTimeout(() => {
                            copyBtn.innerText = originalText;
                        }, 2000);
                    }).catch(err => {
                        console.error('Error al copiar enlace: ', err);
                    });
                };

                // Botón inteligente de copiar enlace específico para Instagram
                const instagramBtn = document.getElementById('share-ig');
                instagramBtn.onclick = (e) => {
                    e.preventDefault();
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        const originalText = instagramBtn.innerText;
                        instagramBtn.innerText = '¡Copiado para Instagram! 📸';
                        setTimeout(() => {
                            instagramBtn.innerText = originalText;
                        }, 2000);
                    }).catch(err => {
                        console.error('Error al copiar enlace: ', err);
                    });
                };

                // Botón inteligente de copiar enlace específico para TikTok
                const tiktokBtn = document.getElementById('share-tk');
                tiktokBtn.onclick = (e) => {
                    e.preventDefault();
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        const originalText = tiktokBtn.innerText;
                        tiktokBtn.innerText = '¡Copiado para TikTok! 🎵';
                        setTimeout(() => {
                            tiktokBtn.innerText = originalText;
                        }, 2000);
                    }).catch(err => {
                        console.error('Error al copiar enlace: ', err);
                    });
                };

                // 6. DETECTOR DE REPRODUCTOR DE VIDEO DE YOUTUBE EMBEBIDO EN LA CABECERA
                const videoWrapper = document.getElementById('modal-video-wrapper');
                videoWrapper.innerHTML = '';
                videoWrapper.style.display = 'none';
                modalImg.style.display = 'block';

                let videoId = '';
                if (videoUrl) {
                    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
                    const match = videoUrl.match(regExp);
                    if (match && match[2].length === 11) {
                        videoId = match[2];
                    }
                }

                if (videoId) {
                    // Si hay video, inyectamos el reproductor y ocultamos la imagen
                    videoWrapper.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?autoplay=0" style="position: absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>`;
                    videoWrapper.style.display = 'block';
                    modalImg.style.display = 'none';
                }

                // 7. CONTROL DEL SLIDER INTERACTIVO DE IMÁGENES
                const prevBtn = document.getElementById('slider-prev-btn');
                const nextBtn = document.getElementById('slider-next-btn');
                const counter = document.getElementById('slider-counter');

                let imagesList = [imagen]; // Imagen principal por defecto
                if (galleryJson) {
                    try {
                        const extraImages = JSON.parse(galleryJson);
                        if (Array.isArray(extraImages)) {
                            extraImages.forEach(img => {
                                imagesList.push('uploads/' + img);
                            });
                        }
                    } catch (e) {
                        console.error("Error al deserializar JSON de galería:", e);
                    }
                }

                let currentSlideIndex = 0;
                
                // Configurar imagen de inicio (solo si no hay video)
                if (!videoId) {
                    modalImg.src = imagesList[0];
                    modalImg.style.transition = 'opacity 0.2s ease-in-out';
                    modalImg.style.opacity = '1';
                }

                const updateSliderView = () => {
                    modalImg.style.opacity = '0';
                    setTimeout(() => {
                        modalImg.src = imagesList[currentSlideIndex];
                        modalImg.style.opacity = '1';
                    }, 150);
                    counter.innerText = `${currentSlideIndex + 1} / ${imagesList.length}`;
                };

                // Habilitamos flechas si hay más de una foto y NO hay video activo
                if (imagesList.length > 1 && !videoId) {
                    counter.innerText = `1 / ${imagesList.length}`;
                    counter.style.display = 'block';
                    prevBtn.style.display = 'flex';
                    nextBtn.style.display = 'flex';
                    
                    // Clonamos botones para eliminar listeners previos acumulados
                    const newPrevBtn = prevBtn.cloneNode(true);
                    const newNextBtn = nextBtn.cloneNode(true);
                    prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);
                    nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);
                    
                    newPrevBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        currentSlideIndex = (currentSlideIndex === 0) ? imagesList.length - 1 : currentSlideIndex - 1;
                        updateSliderView();
                    });
                    
                    newNextBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        currentSlideIndex = (currentSlideIndex === imagesList.length - 1) ? 0 : currentSlideIndex + 1;
                        updateSliderView();
                    });
                } else {
                    counter.style.display = 'none';
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                }

                // 8. Mostramos el modal
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });

        // Evento para cerrar el modal al presionar el botón de la (X)
        const cerrarModal = () => {
            modal.classList.remove('active');
            document.body.style.overflow = ''; // Restauramos el scroll
            
            // Limpiar reproductor de video en reproducción activa
            document.getElementById('modal-video-wrapper').innerHTML = '';
            
            // Limpiamos el ID de la URL al cerrar el modal
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('id');
            window.history.pushState({}, '', currentUrl);
        };

        closeBtn.addEventListener('click', cerrarModal);

        // Evento para cerrar el modal al hacer clic en el fondo gris translúcido
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                cerrarModal();
            }
        });

        // ==========================================
        // CONTROL DE AMPLIACIÓN (LIGHTBOX)
        // ==========================================
        const lightbox = document.getElementById('lightbox-overlay');
        const lightboxImg = document.getElementById('lightbox-img');

        modalImg.addEventListener('click', () => {
            // Solo abrimos si no se está mostrando un video (la imagen está visible)
            if (modalImg.style.display !== 'none' && modalImg.src) {
                lightboxImg.src = modalImg.src;
                lightbox.classList.add('active');
            }
        });

        lightbox.addEventListener('click', () => {
            lightbox.classList.remove('active');
        });

        // 9. APERTURA AUTOMÁTICA AL CARGAR LA PÁGINA (Router de URLs dinámicas)
        const urlParams = new URLSearchParams(window.location.search);
        const sharedId = urlParams.get('id');
        if (sharedId) {
            const targetArticle = Array.from(articles).find(art => {
                const ds = art.querySelector('.article-data-store');
                return ds && ds.querySelector('.data-id') && ds.querySelector('.data-id').textContent === sharedId;
            });
            
            if (targetArticle) {
                setTimeout(() => {
                    targetArticle.click();
                }, 300); // Retraso suave para transiciones limpias
            }
        }
    });
    </script>

    <!-- LIGHTBOX OVERLAY PARA AMPLIAR IMÁGENES -->
    <div id="lightbox-overlay" class="lightbox-overlay">
        <img id="lightbox-img" class="lightbox-img" src="" alt="Imagen ampliada">
    </div>
</body>
</html>
