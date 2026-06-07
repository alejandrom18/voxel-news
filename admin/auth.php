<?php
/**
 * auth.php - Middleware de Autenticación del Portal Administrativo VOXEL
 * 
 * Este archivo centraliza el control de acceso para todo el directorio /admin/.
 * Debe ser requerido de forma obligatoria en la línea número 1 de todos los scripts CRUD
 * (index.php, nuevo.php, editar.php, eliminar.php).
 * 
 * Si un visitante intenta ingresar a estas páginas sin estar autenticado, la sesión
 * no validará su identidad y será redirigido inmediatamente al panel de login.
 */

// Iniciamos la sesión si no está iniciada en este hilo de ejecución de PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validamos la existencia y valor de la credencial en el array global $_SESSION
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Si la sesión no es válida, redirigimos al usuario al login
    header("Location: login.php");
    
    // Detenemos inmediatamente la ejecución para que no se renderice el panel en segundo plano
    exit;
}
?>
