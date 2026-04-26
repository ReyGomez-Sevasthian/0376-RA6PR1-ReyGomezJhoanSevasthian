<?php
/**
 * Usuarios - TimeTrack Pro
 * 
 * Gestión de usuarios (solo Admin)
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo admin puede gestionar usuarios
if (!hasRole('admin')) {
    setFlashMessage('danger', 'No tienes permiso para acceder a esta página.');
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';
$tipoMensaje = '';

// ============================================
// PROCESAR FORMULARIOS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Crear usuario
    if (isset($_POST['crear_usuario'])) {
        $nombre = sanitizeInput($_POST['nombre']);
        $apellidos = sanitizeInput($_POST['apellidos']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $rol_id = filter_var($_POST['rol_id'], FILTER_VALIDATE_INT);
        
        if (empty($nombre) || empty($apellidos) || empty($email) || empty($password)) {
            $tipoMensaje = 'danger';
            $mensaje = 'Todos los campos son obligatorios.';
        } elseif (!isValidEmail($email)) {
            $tipoMensaje = 'danger';
            $mensaje = 'El email no es válido.';
        } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
            $tipoMensaje = 'danger';
            $mensaje = 'La contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres.';
        } else {
            // Verificar email único
            $existe = executeQuery("SELECT id FROM usuarios WHERE email = ?", [$email]);
            if ($existe && count($existe) > 0) {
                $tipoMensaje = 'danger';
                $mensaje = 'El email ya está registrado.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $resultado = executeUpdate("
                    INSERT INTO usuarios (nombre, apellidos, email, password, rol_id) 
                    VALUES (?, ?, ?, ?, ?)
                ", [$nombre, $apellidos, $email, $hashedPassword, $rol_id]);
                
                if ($resultado) {
                    $tipoMensaje = 'success';
                    $mensaje = 'Usuario creado exitosamente.';
                } else {
                    $tipoMensaje = 'danger';
                    $mensaje = 'Error al crear el usuario.';
                }
            }
        }
    }
    
    // Activar/Desactivar usuario
    if (isset($_POST['toggle_activo'])) {
        $usuarioId = filter_var($_POST['toggle_activo'], FILTER_VALIDATE_INT);
        $nuevoEstado = isset($_POST['nuevo_estado']) ? 1 : 0;
        
        if ($usuarioId && $usuarioId != $userId) {
            executeUpdate("UPDATE usuarios SET activo = ? WHERE id = ?", [$nuevoEstado, $usuarioId]);
            $tipoMensaje = 'success';
            $mensaje = 'Estado del usuario actualizado.';
        }
    }
}

// ============================================
// OBTENER USUARIOS
// ============================================
$filtroRol = isset($_GET['rol']) ? sanitizeInput($_GET['rol']) : '';

$sql = "
    SELECT 
        u.id,
        u.nombre,
        u.apellidos,
        u.email,
        u.activo,
        u.creado_en,
        r.id as rol_id,
        r.nombre as rol
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
";

$params = [];

if ($filtroRol) {
    $rolIds = ['admin' => 1, 'manager' => 2, 'empleado' => 3];
    if (isset($rolIds[$filtroRol])) {
        $sql .= " WHERE u.rol_id = ?";
        $params[] = $rolIds[$filtroRol];
    }
}

$sql .= " ORDER BY u.apellidos, u.nombre";

$usuarios = executeQuery($sql, $params);

// Incluir header
$pageTitle = 'Usuarios';
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="fas fa-users me-2"></i>Gestión de Usuarios
            </h2>
            <p class="text-muted">Administrar usuarios del sistema</p>
        </div>
    </div>

    <!-- Botón crear usuario -->
    <div class="row mb-4">
        <div class="col-12">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" name="rol">
                        <option value="">Todos los roles</option>
                        <option value="admin" <?php echo $filtroRol === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="manager" <?php echo $filtroRol === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="empleado" <?php echo $filtroRol === 'empleado' ? 'selected' : ''; ?>>Empleado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="col-md-2">
                    <a href="usuarios.php" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de usuarios -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-2"></i>Usuarios Registrados</span>
            <span class="badge bg-primary"><?php echo count($usuarios); ?></span>
        </div>
        <div class="card-body p-0">
            <?php if ($usuarios && count($usuarios) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr class="<?php echo !$usuario['activo'] ? 'table-secondary' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'secondary';
                                    if ($usuario['rol'] === 'admin') $badgeClass = 'danger';
                                    elseif ($usuario['rol'] === 'manager') $badgeClass = 'primary';
                                    elseif ($usuario['rol'] === 'empleado') $badgeClass = 'success';
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($usuario['rol']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($usuario['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($usuario['creado_en'])); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="toggle_activo" value="<?php echo $usuario['id']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?php echo $usuario['activo'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?php echo $usuario['activo'] ? 'danger' : 'success'; ?>"
                                                onclick="return confirm('¿Cambiar estado del usuario?')">
                                            <i class="fas fa-<?php echo $usuario['activo'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info m-4">
                    <i class="fas fa-info-circle me-2"></i>
                    No se encontraron usuarios.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellidos *</label>
                        <input type="text" class="form-control" name="apellidos" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" name="password" required minlength="<?php echo MIN_PASSWORD_LENGTH; ?>">
                        <div class="form-text">Mínimo <?php echo MIN_PASSWORD_LENGTH; ?> caracteres</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select class="form-select" name="rol_id" required>
                            <option value="3">Empleado</option>
                            <option value="2">Manager</option>
                            <option value="1">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_usuario" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>