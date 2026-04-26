<?php
/**
 * Página de Login - TimeTrack Pro
 * 
 * Permite a los usuarios iniciar sesión en el sistema
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Si ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validaciones básicas
    if (empty($email) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } elseif (!isValidEmail($email)) {
        $error = 'El email no es válido.';
    } else {
        // Buscar usuario en la base de datos
        $sql = "SELECT u.id, u.nombre, u.apellidos, u.password, u.activo, r.nombre as rol 
                FROM usuarios u 
                JOIN roles r ON u.rol_id = r.id 
                WHERE u.email = ? AND u.activo = 1";
        
        $result = executeQuery($sql, [$email]);
        
        if ($result && count($result) === 1) {
            $user = $result[0];
            
            // Verificar contraseña
            if (password_verify($password, $user['password'])) {
                // Regenerar ID de sesión para mayor seguridad
                session_regenerate_id(true);
                
                // Guardar datos en sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellidos'];
                $_SESSION['user_role'] = $user['rol'];
                $_SESSION['user_role_id'] = array_search($user['rol'], ['admin', 'manager', 'empleado']) + 1;
                $_SESSION['login_time'] = time();
                
                // Redirigir al dashboard o a la URL original
                $redirectUrl = $_SESSION['redirect_url'] ?? APP_URL . '/pages/dashboard.php';
                unset($_SESSION['redirect_url']);
                
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                $error = 'Email o contraseña incorrectos.';
            }
        } else {
            // Mensaje genérico para no revelar si el usuario existe
            $error = 'Email o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0b5ed7 0%, #084298 100%);
            transform: translateY(-1px);
        }
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-clock"></i>
            <h2><?php echo APP_NAME; ?></h2>
            <p class="mb-0">Sistema de Control de Horas</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-1"></i> Correo Electrónico
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i> Contraseña
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                </button>
            </form>
            
            <div class="footer-text">
                <p>¿Olvidaste tu contraseña? Contacta al administrador.</p>
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></p>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
