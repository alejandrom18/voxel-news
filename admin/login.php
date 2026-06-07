<?php
/**
 * login.php - Portal de Acceso Administrativo Premium para VOXEL
 * 
 * Este archivo implementa el portal de acceso y el control de cierre de sesión.
 * Utiliza BCRYPT para la verificación de contraseñas de alta seguridad.
 * El diseño está adaptado meticulosamente a la marca VOXEL (estilo oscuro de alto contraste y fuentes Oswald).
 */

require_once '../conexion.php';

// Iniciamos la sesión si no se ha hecho previamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$info_mensaje = '';

// A. MANEJO DE CIERRE DE SESIÓN (Logout)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Vaciamos todas las variables de sesión
    $_SESSION = [];
    
    // Destruimos la cookie de sesión si existe
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruimos físicamente la sesión en el servidor
    session_destroy();
    
    $info_mensaje = 'Sesión cerrada de forma segura. ¡Vuelve pronto!';
}

// Si el usuario ya está autenticado, lo redirigimos directamente al panel para ahorrarle pasos
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// B. PROCESO DE AUTENTICACIÓN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, rellene todos los campos.';
    } else {
        try {
            // Buscamos al usuario en la base de datos de manera segura con Consulta Preparada
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario LIMIT 1");
            $stmt->execute(['usuario' => $usuario]);
            $user_data = $stmt->fetch();

            if ($user_data) {
                // Comprobamos si la contraseña coincide con el hash encriptado
                if (password_verify($password, $user_data['password'])) {
                    // Inicializamos las credenciales en la sesión
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username']  = $user_data['usuario'];
                    $_SESSION['admin_nombre']    = $user_data['nombre'];

                    // Redirección inmediata al Panel Administrativo principal
                    header("Location: index.php");
                    exit;
                } else {
                    $error = 'Contraseña incorrecta. Inténtelo de nuevo.';
                }
            } else {
                $error = 'El usuario no existe en nuestro sistema.';
            }
        } catch (\PDOException $e) {
            $error = 'Error del sistema: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | VOXEL Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0b132b;
            --bg-card: #1c2541;
            --primary: #1215ca;
            --primary-glow: #1a1df2;
            --text-light: #f4f6f9;
            --accent-red: #ef4444;
            --accent-green: #10b981;
            --border-color: #3a506b;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
        }

        /* Efecto de fondo sutil con gradiente */
        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(18,21,202,0.15) 0%, rgba(0,0,0,0) 70%);
            top: 20%;
            left: 20%;
            z-index: -1;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(18,21,202,0.1) 0%, rgba(0,0,0,0) 70%);
            bottom: 10%;
            right: 15%;
            z-index: -1;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-card {
            background-color: var(--bg-card);
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            box-shadow: 0 20px 45px rgba(18, 21, 202, 0.25);
        }

        /* Logotipo Animado */
        .logo-wrapper {
            margin-bottom: 30px;
            display: flex;
            justify-content: center;
        }

        @keyframes pulseLogo {
            0%, 100% { filter: drop-shadow(0 0 2px rgba(18,21,202,0.4)); }
            50% { filter: drop-shadow(0 0 10px rgba(18,21,202,0.8)); }
        }

        .logo-wrapper svg {
            animation: pulseLogo 3s infinite ease-in-out;
        }

        h2 {
            font-family: 'Oswald', sans-serif;
            font-size: 1.8rem;
            margin: 0 0 25px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Alertas de error e información */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-align: left;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--accent-red);
            color: #f87171;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--accent-green);
            color: #34d399;
        }

        /* Elementos del Formulario */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }

        label {
            display: block;
            font-family: 'Oswald', sans-serif;
            font-size: 0.95rem;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: #a5b1c2;
            letter-spacing: 0.5px;
        }

        .input-control {
            width: 100%;
            padding: 12px 16px;
            background-color: #0b132b;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
            font-size: 1rem;
            box-sizing: border-box;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-control:focus {
            border-color: var(--primary-glow);
            box-shadow: 0 0 0 3px rgba(18, 21, 202, 0.3);
        }

        /* Botón de envío */
        .btn-login {
            width: 100%;
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            padding: 14px;
            font-family: 'Oswald', sans-serif;
            font-size: 1.25rem;
            text-transform: uppercase;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(18, 21, 202, 0.4);
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: var(--primary-glow);
            box-shadow: 0 6px 18px rgba(18, 21, 202, 0.6);
            transform: translateY(-2px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .footer-link {
            display: block;
            margin-top: 25px;
            font-size: 0.85rem;
            color: #6c7a89;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-link:hover {
            color: var(--text-light);
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        
        <!-- Logotipo Oficial de VOXEL adaptado con animaciones y brillo -->
        <div class="logo-wrapper">
            <svg width="180" height="45" viewBox="0 15 400 70" xmlns="http://www.w3.org/2000/svg">
                <style>
                    @keyframes colorSwapText {
                        0%, 45% { fill: #ffffff; }
                        50%, 95% { fill: #1215ca; }
                        100% { fill: #ffffff; }
                    }

                    @keyframes colorSwapX {
                        0%, 45% { fill: #1215ca; }
                        50%, 95% { fill: #ffffff; }
                        100% { fill: #1215ca; }
                    }

                    .voxel-text {
                        font-family: 'Impact', 'Arial Black', sans-serif;
                        font-size: 85px;
                        font-weight: 900;
                        font-style: italic;
                        fill: #ffffff;
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
        </div>

        <h2>Acceso Administrativo</h2>

        <!-- Caja de error -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span>⚠️ <?= $error ?></span>
            </div>
        <?php endif; ?>

        <!-- Caja de información (ej. cierre de sesión) -->
        <?php if (!empty($info_mensaje)): ?>
            <div class="alert alert-success">
                <span>✓ <?= $info_mensaje ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            
            <div class="form-group">
                <label for="usuario">Nombre de Usuario</label>
                <input type="text" id="usuario" name="usuario" class="input-control" 
                       placeholder="Ej. admin" required autocomplete="username" autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="input-control" 
                       placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">🔒 Iniciar Sesión</button>
        </form>

        <a href="../index.php" class="footer-link">⬅ Volver al portal público VOXEL</a>
    </div>
</div>

</body>
</html>
