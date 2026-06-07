<?php
// 1. Cargamos el middleware de autenticación (valida sesión activa)
require_once 'auth.php';

// 2. Importamos la conexión subiendo un nivel de carpeta
require_once '../conexion.php';

// 2. Capturamos el ID enviado por la URL y verificamos que sea un número válido
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    
    $id = (int)$_GET['id']; // Forzamos a que el ID sea tratado como un número entero
    
    try {
        // PASO A: Buscamos la noticia en la base de datos para saber qué imagen y galería tiene
        $stmt_select = $pdo->prepare("SELECT imagen, galeria FROM noticias WHERE id = :id");
        $stmt_select->execute(['id' => $id]);
        $noticia = $stmt_select->fetch();
        
        if ($noticia) {
            $nombre_imagen = $noticia['imagen'];
            
            // PASO B: Si la imagen principal existe y no es default.jpg, la borramos de disco
            if ($nombre_imagen !== 'default.jpg') {
                $ruta_imagen = '../uploads/' . $nombre_imagen;
                if (file_exists($ruta_imagen)) {
                    unlink($ruta_imagen); // Borra la foto principal
                }
            }
            
            // PASO B-2: Borrar físicamente las imágenes secundarias de la galería
            $galeria = json_decode($noticia['galeria'], true);
            if (is_array($galeria)) {
                foreach ($galeria as $foto_galeria) {
                    $ruta_foto = '../uploads/' . $foto_galeria;
                    if (file_exists($ruta_foto)) {
                        unlink($ruta_foto); // Borra la foto de la galería
                    }
                }
            }
            
            // PASO C: Ahora sí, eliminamos la noticia de la base de datos MySQL
            $stmt_delete = $pdo->prepare("DELETE FROM noticias WHERE id = :id");
            $stmt_delete->execute(['id' => $id]);
        }
        
    } catch (\PDOException $e) {
        // En un script silencioso, si falla algo, mostramos el error y detenemos
        die("Error al eliminar la noticia: " . $e->getMessage());
    }
}

// 3. Redireccionamos al administrador de vuelta al Panel de Control principal
header("Location: index.php");
exit;