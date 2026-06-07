<?php
/**
 * utils.php - Librería de Utilidades Administrativas para VOXEL
 * 
 * Contiene funciones auxiliares compartidas en el panel administrativo,
 * enfocadas principalmente en la seguridad y en la optimización de recursos.
 */

/**
 * Optimiza una imagen cargada, la redimensiona si supera un ancho máximo 
 * manteniendo la relación de aspecto, y la convierte al formato WebP de alto rendimiento.
 *
 * @param string $ruta_temporal Ruta en el directorio temporal del servidor ($_FILES['campo']['tmp_name']).
 * @param string $ruta_destino_webp Ruta completa definitiva donde se guardará en disco (ej. '../uploads/foto.webp').
 * @param int $max_ancho Ancho máximo permitido en píxeles. Por defecto 1200px.
 * @return bool Devuelve true si la conversión y guardado fueron exitosos, false de lo contrario.
 */
function optimizarYConvertirImagenWebP($ruta_temporal, $ruta_destino_webp, $max_ancho = 1200) {
    // 1. Obtener los metadatos reales de la imagen (dimensiones y tipo)
    $info = getimagesize($ruta_temporal);
    if ($info === false) {
        return false; // El archivo no es una imagen válida o utilizable por PHP
    }
    
    $ancho_original = $info[0];
    $alto_original = $info[1];
    $mime = $info['mime'];
    
    // 2. Cargar la imagen origen en memoria según su tipo real (MIME)
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $imagen_origen = imagecreatefromjpeg($ruta_temporal);
            break;
            
        case 'image/png':
            $imagen_origen = imagecreatefrompng($ruta_temporal);
            // PASO PREMIUM: Si la imagen es PNG, debemos conservar sus canales de transparencia (alpha)
            // antes de procesarla o convertirla.
            if ($imagen_origen) {
                imagepalettetotruecolor($imagen_origen);
                imagealphablending($imagen_origen, true);
                imagesavealpha($imagen_origen, true);
            }
            break;
            
        case 'image/webp':
            $imagen_origen = imagecreatefromwebp($ruta_temporal);
            break;
            
        default:
            return false; // Formato no soportado por este optimizador
    }
    
    // Si falló la creación del recurso gráfico
    if (!$imagen_origen) {
        return false;
    }
    
    // 3. Calcular las nuevas dimensiones manteniendo la relación de aspecto
    if ($ancho_original > $max_ancho) {
        // Redimensionar proporcionalmente
        $nuevo_ancho = $max_ancho;
        $nuevo_alto = (int)round(($alto_original / $ancho_original) * $max_ancho);
        
        // Crear un lienzo en blanco para la nueva imagen optimizada
        $imagen_opt = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
        
        // Mantener canales alfa (transparencia) para WebP/PNG en el lienzo nuevo
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($imagen_opt, false);
            imagesavealpha($imagen_opt, true);
            
            // Rellenar con color transparente
            $color_transparente = imagecolorallocatealpha($imagen_opt, 0, 0, 0, 127);
            imagefill($imagen_opt, 0, 0, $color_transparente);
        }
        
        // Copiar y redimensionar con muestreo de alta calidad (resampled)
        imagecopyresampled(
            $imagen_opt,       // Lienzo destino
            $imagen_origen,    // Recurso original
            0, 0, 0, 0,        // Coordenadas X/Y de destino e inicio
            $nuevo_ancho,      // Dimensiones de destino
            $nuevo_alto,
            $ancho_original,   // Dimensiones de origen
            $alto_original
        );
        
        // Destruimos el recurso original de alta carga en memoria
        imagedestroy($imagen_origen);
        
        // Asignamos la imagen optimizada como la final
        $imagen_final = $imagen_opt;
    } else {
        // Si no supera el ancho máximo, conservamos las dimensiones originales
        $imagen_final = $imagen_origen;
        
        // Si es WebP original, simplemente podemos moverlo, pero al guardarlo de nuevo
        // nos aseguramos de comprimirlo al nivel óptimo.
    }
    
    // 4. Guardar físicamente la imagen convertida en formato WebP
    // El tercer parámetro (80) es el nivel de compresión (0-100). 
    // 80 es el estándar de oro de la industria para páginas web rápidas.
    $resultado = imagewebp($imagen_final, $ruta_destino_webp, 80);
    
    // 5. Liberar memoria RAM en el servidor destruyendo el recurso gráfico
    imagedestroy($imagen_final);
    
    return $resultado;
}
?>
