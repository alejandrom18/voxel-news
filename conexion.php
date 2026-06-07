<?php

$host = 'sql303.infinityfree.com';
$db   = 'if0_41859362_voxel';
$user = 'if0_41859362';
$pass = 'Palywowop1801'; 
$charset = 'utf8mb4'; 
// DSN (Data Source Name): Cadena que le dice a PDO qué base de datos usar
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
// Opciones adicionales para configurar el comportamiento de la base de datos
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Nos muestra errores detallados en pantalla si algo falla
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve las noticias como arrays limpios (ej. $noticia['titulo'])
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Hace que nuestras consultas sean 100% seguras contra ataques
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 1. Guardamos la traza técnica real en el log de errores privado del servidor (no visible al público)
    error_log("Error de conexión a la Base de Datos VOXEL: " . $e->getMessage());
    
    // 2. Mostramos al usuario final una pantalla de mantenimiento sumamente premium integrada a la marca
    die("
    <div style='font-family: \"Arial\", sans-serif; text-align: center; background-color: #0b132b; color: #f4f6f9; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box;'>
        <div style='max-width: 500px; padding: 40px; background-color: #1c2541; border-radius: 12px; box-shadow: 0 10px 35px rgba(0,0,0,0.5); border-left: 5px solid #1215ca;'>
            <h1 style='font-family: \"Impact\", \"Arial Black\", sans-serif; font-size: 2.5rem; letter-spacing: 1px; color: #ffffff; margin-top: 0; margin-bottom: 15px;'>VOXEL</h1>
            <h2 style='font-size: 1.5rem; margin-bottom: 15px; color: #ffffff;'>Servicio en Mantenimiento</h2>
            <p style='color: #a5b1c2; font-size: 1rem; line-height: 1.6; margin-bottom: 25px;'>
                Estamos optimizando nuestros sistemas para ofrecerte la mejor experiencia informativa. El portal estará disponible nuevamente en unos instantes.
            </p>
            <span style='color: #6c7a89; font-size: 0.8rem;'>Código de error: DB_CONN_OFFLINE</span>
        </div>
    </div>
    ");
}