<?php
/**
 * Mis Horas - TimeTrack Pro
 * 
 * Vista del historial de horas del empleado
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo empleados y admin pueden ver sus horas
if (!hasRole(['empleado', 'admin'])) {
    setFlashMessage('danger', 'No tienes permiso para acceder a esta página.');
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Filtros
$filtroProyecto = isset($_GET['proyecto']) ? filter_var($_GET['proyecto'], FILTER_VALIDATE_INT) : null;
$filtroFechaInicio = isset($_GET['fecha_inicio']) ? sanitizeInput($_GET['fecha_inicio']) : date('Y-m-01');
$filtroFechaFin = isset($_GET['fecha_fin']) ? sanitizeInput($_GET['fecha_fin']) : date('Y-m-d');

// Validar fechas
if (!$filtroFechaInicio) $filtroFechaInicio = date('Y-m-01');
if (!$filtroFechaFin) $filtroFechaFin = date('Y-m-d');

// Obtener historial de horas
$sql = "
    SELECT 
        rhp.id,
        p.nombre as proyecto,
        rhp.fecha,
        rhp.hora_inicio,
        rhp.hora_fin,
        rhp.duracion_minutos,
        rhp.descripcion
    FROM registros_horas_proyecto rhp
    JOIN proyectos p ON rhp.proyecto_id = p.id
    WHERE rhp.usuario_id = ?
        AND rhp.fecha BETWEEN ? AND ?
";

$params = [$userId, $filtroFechaInicio, $filtroFechaFin];

if ($filtroProyecto) {
    $sql .= " AND rhp.proyecto_id = ?";
    $params[] = $filtroProyecto;
}

$sql .= " ORDER BY rhp.fecha DESC, rhp.hora_inicio DESC";

$registros = executeQuery($sql, $params);

// Total de horas en el período
$totalHoras = 0;
if ($registros) {
    foreach ($registros as $registro) {
        $totalHoras += $registro['duracion_minutos'];
    }
}

// Proyectos del usuario (para el filtro)
$proyectosUsuario = executeQuery("
    SELECT p.id, p.nombre
    FROM proyectos p
    JOIN equipo_proyecto ep ON p.id = ep.proyecto_id
    WHERE ep.usuario_id = ? AND p.activo = 1
    ORDER BY p.nombre
", [$userId]);

// Horas por proyecto en el período
$horasPorProyecto = executeQuery("
    SELECT 
        p.nombre,
        COALESCE(SUM(rhp.duracion_minutos), 0) as total_minutos
    FROM proyectos p
    LEFT JOIN registros_horas_proyecto rhp ON p.id = rhp.proyecto_id
        AND rhp.usuario_id = ?
        AND rhp.fecha BETWEEN ? AND ?
    JOIN equipo_proyecto ep ON p.id = ep.proyecto_id AND ep.usuario_id = ?
    WHERE p.activo = 1
    GROUP BY p.id, p.nombre
    ORDER BY total_minutos DESC
", [$userId, $filtroFechaInicio, $filtroFechaFin, $userId]);

// Incluir header
$pageTitle = 'Mis Horas';
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="fas fa-history me-2"></i>Mis Horas
            </h2>
            <p class="text-muted">Consulta tu historial de horas trabajadas por proyecto</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Proyecto</label>
                    <select class="form-select" name="proyecto">
                        <option value="">Todos los proyectos</option>
                        <?php if ($proyectosUsuario): ?>
                            <?php foreach ($proyectosUsuario as $proyecto): ?>
                                <option value="<?php echo $proyecto['id']; ?>" 
                                    <?php echo $filtroProyecto == $proyecto['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($proyecto['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo $filtroFechaInicio; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo $filtroFechaFin; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                    <a href="mis-horas.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Estadísticas del período -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo formatDuration($totalHoras); ?></div>
                    <div class="stat-label">Horas Totales</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo count($registros); ?></div>
                    <div class="stat-label">Registros</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo count($proyectosUsuario); ?></div>
                    <div class="stat-label">Proyectos</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de horas por proyecto -->
    <?php if ($horasPorProyecto && count($horasPorProyecto) > 0): ?>
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>Horas por Proyecto
                </div>
                <div class="card-body">
                    <canvas id="horasProyectoChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2"></i>Distribución
                </div>
                <div class="card-body">
                    <canvas id="distribucionChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabla de registros -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-table me-2"></i>Detalle de Horas</span>
            <span class="badge bg-primary"><?php echo count($registros); ?> registros</span>
        </div>
        <div class="card-body p-0">
            <?php if ($registros && count($registros) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Proyecto</th>
                                <th>Horario</th>
                                <th>Duración</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($registro['fecha'])); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($registro['proyecto']); ?></span>
                                </td>
                                <td>
                                    <?php echo substr($registro['hora_inicio'], 0, 5) . ' - ' . substr($registro['hora_fin'], 0, 5); ?>
                                </td>
                                <td><strong><?php echo formatDuration($registro['duracion_minutos']); ?></strong></td>
                                <td class="text-muted small">
                                    <?php echo htmlspecialchars(substr($registro['descripcion'] ?? '', 0, 50)); ?>
                                    <?php if (strlen($registro['descripcion'] ?? '') > 50) echo '...'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info m-4">
                    <i class="fas fa-info-circle me-2"></i>
                    No se encontraron registros para el período seleccionado.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Scripts para gráficos
$pageScripts = '';
if ($horasPorProyecto && count($horasPorProyecto) > 0) {
    $labels = json_encode(array_column($horasPorProyecto, 'nombre'));
    $dataMinutos = array_column($horasPorProyecto, 'total_minutos');
    $dataHoras = array_map(function($min) { return round($min / 60, 1); }, $dataMinutos);
    $dataHoras = json_encode($dataHoras);
    
    $colors = [
        'rgba(13, 110, 253, 0.8)',
        'rgba(25, 135, 84, 0.8)',
        'rgba(255, 193, 7, 0.8)',
        'rgba(220, 53, 69, 0.8)',
        'rgba(111, 66, 193, 0.8)',
        'rgba(253, 126, 20, 0.8)'
    ];
    
    $pageScripts = "<script>
    // Gráfico de Barras
    const ctx1 = document.getElementById('horasProyectoChart');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: $labels,
                datasets: [{
                    label: 'Horas',
                    data: $dataHoras,
                    backgroundColor: " . json_encode($colors) . ",
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    // Gráfico de Dona
    const ctx2 = document.getElementById('distribucionChart');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: $labels,
                datasets: [{
                    data: $dataHoras,
                    backgroundColor: " . json_encode($colors) . ",
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
    </script>";
}
echo $pageScripts;
?>

<?php include '../includes/footer.php'; ?>