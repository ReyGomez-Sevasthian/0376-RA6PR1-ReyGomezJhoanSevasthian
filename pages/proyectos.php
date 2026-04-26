<?php
/**
 * Proyectos - TimeTrack Pro
 * 
 * Gestión de proyectos (Admin y Manager)
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo admin y manager pueden ver proyectos
if (!hasRole(['admin', 'manager'])) {
    setFlashMessage('danger', 'No tienes permiso para acceder a esta página.');
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

$mensaje = '';
$tipoMensaje = '';

// ============================================
// PROCESAR FORMULARIOS (solo admin)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userRole === 'admin') {
    // Crear proyecto
    if (isset($_POST['crear_proyecto'])) {
        $nombre = sanitizeInput($_POST['nombre']);
        $descripcion = sanitizeInput($_POST['descripcion'] ?? '');
        $horas_presupuestadas = floatval($_POST['horas_presupuestadas'] ?? 0);
        $cliente = sanitizeInput($_POST['cliente'] ?? '');
        
        if (empty($nombre)) {
            $tipoMensaje = 'danger';
            $mensaje = 'El nombre del proyecto es obligatorio.';
        } else {
            $resultado = executeUpdate("
                INSERT INTO proyectos (nombre, descripcion, horas_presupuestadas, cliente) 
                VALUES (?, ?, ?, ?)
            ", [$nombre, $descripcion, $horas_presupuestadas, $cliente]);
            
            if ($resultado) {
                $tipoMensaje = 'success';
                $mensaje = 'Proyecto creado exitosamente.';
            } else {
                $tipoMensaje = 'danger';
                $mensaje = 'Error al crear el proyecto.';
            }
        }
    }
    
    // Activar/Desactivar proyecto
    if (isset($_POST['toggle_activo'])) {
        $proyectoId = filter_var($_POST['toggle_activo'], FILTER_VALIDATE_INT);
        $nuevoEstado = isset($_POST['nuevo_estado']) ? 1 : 0;
        
        executeUpdate("UPDATE proyectos SET activo = ? WHERE id = ?", [$nuevoEstado, $proyectoId]);
        $tipoMensaje = 'success';
        $mensaje = 'Estado del proyecto actualizado.';
    }
    
    // Asignar empleado a proyecto
    if (isset($_POST['asignar_empleado'])) {
        $proyectoId = filter_var($_POST['proyecto_id'], FILTER_VALIDATE_INT);
        $usuarioId = filter_var($_POST['usuario_id'], FILTER_VALIDATE_INT);
        
        if ($proyectoId && $usuarioId) {
            executeUpdate("
                INSERT IGNORE INTO equipo_proyecto (usuario_id, proyecto_id) 
                VALUES (?, ?)
            ", [$usuarioId, $proyectoId]);
            $tipoMensaje = 'success';
            $mensaje = 'Empleado asignado al proyecto.';
        }
    }
}

// ============================================
// OBTENER PROYECTOS
// ============================================
$sql = "
    SELECT 
        p.id,
        p.nombre,
        p.descripcion,
        p.horas_presupuestadas,
        p.cliente,
        p.activo,
        p.creado_en,
        COUNT(DISTINCT ep.usuario_id) as equipo_count,
        COALESCE(SUM(rhp.duracion_minutos), 0) as horas_trabajadas
    FROM proyectos p
    LEFT JOIN equipo_proyecto ep ON p.id = ep.proyecto_id
    LEFT JOIN registros_horas_proyecto rhp ON p.id = rhp.proyecto_id
        AND rhp.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
";

if ($userRole === 'manager') {
    $sql .= " WHERE p.id IN (SELECT proyecto_id FROM equipo_proyecto WHERE usuario_id = ?)";
    $params = [$userId];
} else {
    $params = [];
}

$sql .= " GROUP BY p.id, p.nombre, p.descripcion, p.horas_presupuestadas, p.cliente, p.activo, p.creado_en
          ORDER BY p.nombre";

$proyectos = executeQuery($sql, $params);

// Obtener empleados para asignación (solo admin)
$empleados = [];
if ($userRole === 'admin') {
    $empleados = executeQuery("
        SELECT id, nombre, apellidos, email 
        FROM usuarios 
        WHERE rol_id = 3 AND activo = 1 
        ORDER BY apellidos, nombre
    ");
}

// Incluir header
$pageTitle = 'Proyectos';
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="fas fa-project-diagram me-2"></i>Proyectos
            </h2>
            <p class="text-muted">Gestión de proyectos y equipos de trabajo</p>
        </div>
    </div>

    <!-- Botón crear proyecto (solo admin) -->
    <?php if ($userRole === 'admin'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearProyecto">
                <i class="fas fa-plus-circle me-2"></i>Nuevo Proyecto
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tarjetas de proyectos -->
    <div class="row">
        <?php if ($proyectos && count($proyectos) > 0): ?>
            <?php foreach ($proyectos as $proyecto): ?>
                <?php
                $horasTrabajadas = round($proyecto['horas_trabajadas'] / 60, 2);
                $horasPresupuestadas = floatval($proyecto['horas_presupuestadas']);
                $porcentaje = $horasPresupuestadas > 0 ? round(($horasTrabajadas / $horasPresupuestadas) * 100, 1) : 0;
                $progresoColor = $porcentaje > 100 ? 'bg-danger' : ($porcentaje > 80 ? 'bg-warning' : 'bg-success');
                ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100 <?php echo !$proyecto['activo'] ? 'opacity-50' : ''; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><?php echo htmlspecialchars($proyecto['nombre']); ?></span>
                        <?php if ($proyecto['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            <?php echo htmlspecialchars(substr($proyecto['descripcion'] ?? '', 0, 100)); ?>
                            <?php if (strlen($proyecto['descripcion'] ?? '') > 100) echo '...'; ?>
                        </p>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-building me-1"></i>
                                <?php echo htmlspecialchars($proyecto['cliente'] ?? 'Sin cliente'); ?>
                            </small>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-users me-1"></i>
                                <?php echo $proyecto['equipo_count']; ?> miembro<?php echo $proyecto['equipo_count'] != 1 ? 's' : ''; ?>
                            </small>
                        </div>
                        
                        <hr>
                        
                        <!-- Progreso -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <small>Horas trabajadas: <strong><?php echo $horasTrabajadas; ?>h</strong></small>
                                <small>Presupuesto: <strong><?php echo $horasPresupuestadas; ?>h</strong></small>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar <?php echo $progresoColor; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min($porcentaje, 100); ?>%;" 
                                     aria-valuenow="<?php echo $porcentaje; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted"><?php echo $porcentaje; ?>% del presupuesto utilizado</small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Creado: <?php echo date('d/m/Y', strtotime($proyecto['creado_en'])); ?>
                        </small>
                        <?php if ($userRole === 'admin'): ?>
                        <form method="POST" class="d-inline float-end">
                            <input type="hidden" name="toggle_activo" value="<?php echo $proyecto['id']; ?>">
                            <input type="hidden" name="nuevo_estado" value="<?php echo $proyecto['activo'] ? 0 : 1; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-<?php echo $proyecto['activo'] ? 'danger' : 'success'; ?>"
                                    onclick="return confirm('¿Cambiar estado del proyecto?')">
                                <i class="fas fa-<?php echo $proyecto['activo'] ? 'ban' : 'check'; ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay proyectos disponibles.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Crear Proyecto (solo admin) -->
<?php if ($userRole === 'admin'): ?>
<div class="modal fade" id="modalCrearProyecto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Nuevo Proyecto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Proyecto *</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Horas Presupuestadas</label>
                            <input type="number" class="form-control" name="horas_presupuestadas" value="0" min="0" step="0.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente</label>
                            <input type="text" class="form-control" name="cliente">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_proyecto" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>