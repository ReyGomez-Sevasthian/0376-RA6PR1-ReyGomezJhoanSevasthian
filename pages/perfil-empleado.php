<?php
/**
 * Perfil de Empleado - TimeTrack Pro
 * 
 * Muestra el perfil detallado de un empleado (Admin/Manager)
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo admin y manager pueden ver perfiles
if (!hasRole(['admin', 'manager'])) {
    setFlashMessage('danger', 'No tienes permiso para acceder a esta página.');
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Obtener ID del empleado a visualizar
$empleadoId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$empleadoId) {
    setFlashMessage('danger', 'Empleado no especificado.');
    header('Location: alertas.php');
    exit;
}

// Obtener información del empleado
$empleado = executeQuery("
    SELECT 
        u.id,
        u.nombre,
        u.apellidos,
        u.email,
        u.activo,
        u.creado_en,
        r.nombre as rol
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
    WHERE u.id = ?
", [$empleadoId]);

if (!$empleado || count($empleado) === 0) {
    setFlashMessage('danger', 'Empleado no encontrado.');
    header('Location: alertas.php');
    exit;
}

$empleado = $empleado[0];

// Verificar que el manager solo vea empleados de su equipo
if ($userRole === 'manager') {
    $esDeSuEquipo = executeQuery("
        SELECT COUNT(*) as count 
        FROM equipo_proyecto 
        WHERE usuario_id = ?
    ", [$empleadoId]);
    
    if (!$esDeSuEquipo || $esDeSuEquipo[0]['count'] === 0) {
        setFlashMessage('danger', 'No tienes permiso para ver este empleado.');
        header('Location: alertas.php');
        exit;
    }
}

// Horas trabajadas este mes
$horasEsteMes = executeQuery("
    SELECT COALESCE(SUM(duracion_minutos), 0) as total
    FROM registros_horas_proyecto
    WHERE usuario_id = ? AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
", [$empleadoId]);
$horasMes = round(($horasEsteMes[0]['total'] ?? 0) / 60, 1);

// Horas trabajadas esta semana
$horasEstaSemana = executeQuery("
    SELECT COALESCE(SUM(duracion_minutos), 0) as total
    FROM registros_horas_proyecto
    WHERE usuario_id = ? AND YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)
", [$empleadoId]);
$horasSemana = round(($horasEstaSemana[0]['total'] ?? 0) / 60, 1);

// Últimos fichajes
$ultimosFichajes = executeQuery("
    SELECT tipo_registro, fecha_hora, ip_origen
    FROM registros_fichaje
    WHERE usuario_id = ?
    ORDER BY fecha_hora DESC
    LIMIT 10
", [$empleadoId]);

// Alertas recientes
$alertasRecientes = executeQuery("
    SELECT tipo_alerta, descripcion, fecha, leida
    FROM alertas
    WHERE usuario_id = ?
    ORDER BY fecha DESC
    LIMIT 10
", [$empleadoId]);

// Proyectos asignados
$proyectosAsignados = executeQuery("
    SELECT p.id, p.nombre, ep.rol_en_proyecto
    FROM proyectos p
    JOIN equipo_proyecto ep ON p.id = ep.proyecto_id
    WHERE ep.usuario_id = ? AND p.activo = 1
", [$empleadoId]);

// Incluir header
$pageTitle = 'Perfil: ' . $empleado['nombre'] . ' ' . $empleado['apellidos'];
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <a href="alertas.php" class="btn btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left me-1"></i> Volver a Alertas
            </a>
            <h2 class="fw-bold">
                <i class="fas fa-user me-2"></i>Perfil del Empleado
            </h2>
        </div>
    </div>

    <!-- Información Principal -->
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-id-card me-2"></i>Información Personal
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php echo strtoupper(substr($empleado['nombre'], 0, 1)); ?>
                        </div>
                        <h4><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?></h4>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($empleado['email']); ?></p>
                        <span class="badge bg-<?php echo $empleado['activo'] ? 'success' : 'danger'; ?>">
                            <?php echo $empleado['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                        <span class="badge bg-primary ms-1"><?php echo ucfirst($empleado['rol']); ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted">Fecha Registro</small>
                            <div><?php echo date('d/m/Y', strtotime($empleado['creado_en'])); ?></div>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">ID Empleado</small>
                            <div>#<?php echo $empleado['id']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <!-- Estadísticas -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo $horasSemana; ?>h</div>
                            <div class="stat-label">Horas Esta Semana</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo $horasMes; ?>h</div>
                            <div class="stat-label">Horas Este Mes</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Proyectos Asignados -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-project-diagram me-2"></i>Proyectos Asignados
                </div>
                <div class="card-body">
                    <?php if ($proyectosAsignados && count($proyectosAsignados) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($proyectosAsignados as $proyecto): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($proyecto['nombre']); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo ucfirst($proyecto['rol_en_proyecto']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No tiene proyectos asignados.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Últimos Fichajes -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-clock me-2"></i>Últimos Fichajes
                </div>
                <div class="card-body p-0">
                    <?php if ($ultimosFichajes && count($ultimosFichajes) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Fecha/Hora</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimosFichajes as $fichaje): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $fichaje['tipo_registro'] === 'entrada' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($fichaje['tipo_registro']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($fichaje['fecha_hora'])); ?></td>
                                        <td class="text-muted small"><?php echo $fichaje['ip_origen'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info m-3">
                            <i class="fas fa-info-circle me-2"></i>
                            No hay registros de fichaje.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alertas Recientes -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-bell me-2"></i>Alertas Recientes
                </div>
                <div class="card-body p-0">
                    <?php if ($alertasRecientes && count($alertasRecientes) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alertasRecientes as $alerta): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $tipoLabel = $alerta['tipo_alerta'];
                                            $clase = 'secondary';
                                            if ($tipoLabel === 'horas_insuficientes') $clase = 'warning';
                                            elseif ($tipoLabel === 'llegada_tarde') $clase = 'info';
                                            elseif ($tipoLabel === 'salida_temprana') $clase = 'warning';
                                            elseif ($tipoLabel === 'no_fichado') $clase = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $clase; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $tipoLabel)); ?>
                                            </span>
                                        </td>
                                        <td class="small"><?php echo htmlspecialchars($alerta['descripcion']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($alerta['fecha'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $alerta['leida'] ? 'success' : 'warning'; ?>">
                                                <?php echo $alerta['leida'] ? 'Leída' : 'Pendiente'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success m-3">
                            <i class="fas fa-check-circle me-2"></i>
                            ¡Sin alertas recientes!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>