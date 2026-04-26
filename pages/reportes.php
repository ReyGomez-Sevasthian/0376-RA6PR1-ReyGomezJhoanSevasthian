<?php
/**
 * Reportes - TimeTrack Pro
 * 
 * Página de reportes y gráficos para Admin
 * Horas totales por proyecto, comparativa presupuestado vs real
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo admin puede ver reportes
if (!hasRole('admin')) {
    setFlashMessage('danger', 'No tienes permiso para acceder a esta página.');
    header('Location: dashboard.php');
    exit;
}

// Filtros
$filtroPeriodo = isset($_GET['periodo']) ? sanitizeInput($_GET['periodo']) : 'mes';
$filtroProyecto = isset($_GET['proyecto']) ? filter_var($_GET['proyecto'], FILTER_VALIDATE_INT) : null;

// Calcular fechas según período
$fechaInicio = date('Y-m-01'); // Primer día del mes actual
$fechaFin = date('Y-m-d'); // Hoy

if ($filtroPeriodo === 'semana') {
    $fechaInicio = date('Y-m-d', strtotime('monday this week'));
    $fechaFin = date('Y-m-d');
} elseif ($filtroPeriodo === 'trimestre') {
    $fechaInicio = date('Y-m-01', strtotime('-3 months'));
    $fechaFin = date('Y-m-d');
} elseif ($filtroPeriodo === 'anio') {
    $fechaInicio = date('Y-01-01');
    $fechaFin = date('Y-m-d');
} elseif ($filtroPeriodo === 'todo') {
    $fechaInicio = '2020-01-01';
    $fechaFin = date('Y-m-d');
}

// ============================================
// DATOS PARA GRÁFICOS
// ============================================

// 1. Horas totales por proyecto
$horasPorProyecto = executeQuery("
    SELECT 
        p.id,
        p.nombre,
        p.horas_presupuestadas,
        COALESCE(SUM(rhp.duracion_minutos), 0) as horas_reales_minutos,
        COUNT(DISTINCT rhp.usuario_id) as empleados_asignados
    FROM proyectos p
    LEFT JOIN registros_horas_proyecto rhp ON p.id = rhp.proyecto_id
        AND rhp.fecha BETWEEN ? AND ?
    WHERE p.activo = 1
    GROUP BY p.id, p.nombre, p.horas_presupuestadas
    ORDER BY horas_reales_minutos DESC
", [$fechaInicio, $fechaFin]);

// 2. Horas por día (últimos 7 días)
$horasPorDia = executeQuery("
    SELECT 
        DATE(fecha) as fecha,
        COALESCE(SUM(duracion_minutos), 0) as total_minutos
    FROM registros_horas_proyecto
    WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
    GROUP BY DATE(fecha)
    ORDER BY fecha ASC
");

// 3. Top 5 empleados con más horas
$topEmpleados = executeQuery("
    SELECT 
        u.id,
        u.nombre,
        u.apellidos,
        COALESCE(SUM(rhp.duracion_minutos), 0) as total_minutos
    FROM usuarios u
    LEFT JOIN registros_horas_proyecto rhp ON u.id = rhp.usuario_id
        AND rhp.fecha BETWEEN ? AND ?
    WHERE u.rol_id = 3 AND u.activo = 1
    GROUP BY u.id, u.nombre, u.apellidos
    ORDER BY total_minutos DESC
    LIMIT 5
", [$fechaInicio, $fechaFin]);

// 4. Comparativa presupuestado vs real por proyecto
$comparativaProyectos = [];
if ($horasPorProyecto) {
    foreach ($horasPorProyecto as $proyecto) {
        $horasReales = round($proyecto['horas_reales_minutos'] / 60, 2);
        $horasPresupuestadas = floatval($proyecto['horas_presupuestadas']);
        $diferencia = $horasReales - $horasPresupuestadas;
        $porcentaje = $horasPresupuestadas > 0 ? round(($horasReales / $horasPresupuestadas) * 100, 1) : 0;
        
        $comparativaProyectos[] = [
            'id' => $proyecto['id'],
            'nombre' => $proyecto['nombre'],
            'presupuestadas' => $horasPresupuestadas,
            'reales' => $horasReales,
            'diferencia' => $diferencia,
            'porcentaje' => $porcentaje,
            'estado' => $porcentaje > 100 ? 'excedido' : ($porcentaje > 80 ? 'atencion' : 'ok')
        ];
    }
}

// 5. Total general
$totalHorasPeriodo = 0;
$totalEmpleadosActivos = 0;
if ($horasPorProyecto) {
    foreach ($horasPorProyecto as $p) {
        $totalHorasPeriodo += $p['horas_reales_minutos'];
    }
    $totalHorasPeriodo = round($totalHorasPeriodo / 60, 2);
}

// Total empleados activos
$totalEmpleadosQuery = executeQuery("SELECT COUNT(*) as total FROM usuarios WHERE rol_id = 3 AND activo = 1");
$totalEmpleadosActivos = $totalEmpleadosQuery[0]['total'] ?? 0;

// Incluir header
$pageTitle = 'Reportes';
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="fas fa-chart-bar me-2"></i>Reportes y Estadísticas
            </h2>
            <p class="text-muted">Análisis de horas trabajadas y comparativa con presupuesto</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Período</label>
                    <select class="form-select" name="periodo">
                        <option value="semana" <?php echo $filtroPeriodo === 'semana' ? 'selected' : ''; ?>>Esta Semana</option>
                        <option value="mes" <?php echo $filtroPeriodo === 'mes' ? 'selected' : ''; ?>>Este Mes</option>
                        <option value="trimestre" <?php echo $filtroPeriodo === 'trimestre' ? 'selected' : ''; ?>>Últimos 3 Meses</option>
                        <option value="anio" <?php echo $filtroPeriodo === 'anio' ? 'selected' : ''; ?>>Este Año</option>
                        <option value="todo" <?php echo $filtroPeriodo === 'todo' ? 'selected' : ''; ?>>Todo el Historial</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Proyecto</label>
                    <select class="form-select" name="proyecto">
                        <option value="">Todos los proyectos</option>
                        <?php
                        $proyectos = executeQuery("SELECT id, nombre FROM proyectos WHERE activo = 1 ORDER BY nombre");
                        if ($proyectos):
                            foreach ($proyectos as $p):
                        ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $filtroProyecto == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </option>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="exportar_reporte.php?periodo=<?php echo $filtroPeriodo; ?>&proyecto=<?php echo $filtroProyecto; ?>" 
                       class="btn btn-success w-100">
                        <i class="fas fa-download me-1"></i> Exportar
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="reportes.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-1"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo number_format($totalHorasPeriodo, 1); ?>h</div>
                    <div class="stat-label">Horas Totales (Período)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $totalEmpleadosActivos; ?></div>
                    <div class="stat-label">Empleados Activos</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo count($horasPorProyecto); ?></div>
                    <div class="stat-label">Proyectos Activos</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <div class="stat-value">
                        <?php 
                        $promedio = $totalEmpleadosActivos > 0 ? round($totalHorasPeriodo / $totalEmpleadosActivos, 1) : 0;
                        echo $promedio;
                        ?>h
                    </div>
                    <div class="stat-label">Promedio por Empleado</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Horas por Proyecto (Barras) -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>Horas por Proyecto
                </div>
                <div class="card-body">
                    <canvas id="horasProyectosChart" height="150"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Distribución (Dona) -->
        <div class="col-lg-4 mb-4">
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

    <!-- Evolución Diaria -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i>Evolución de Horas (Últimos 7 días)
                </div>
                <div class="card-body">
                    <canvas id="evolucionChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparativa Presupuestado vs Real -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-balance-scale me-2"></i>Comparativa: Horas Presupuestadas vs Horas Reales
                </div>
                <div class="card-body p-0">
                    <?php if (count($comparativaProyectos) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Proyecto</th>
                                        <th class="text-center">Presupuestadas</th>
                                        <th class="text-center">Reales</th>
                                        <th class="text-center">Diferencia</th>
                                        <th class="text-center">% Uso</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Barra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comparativaProyectos as $proyecto): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($proyecto['nombre']); ?></strong></td>
                                        <td class="text-center"><?php echo number_format($proyecto['presupuestadas'], 1); ?>h</td>
                                        <td class="text-center"><?php echo number_format($proyecto['reales'], 1); ?>h</td>
                                        <td class="text-center">
                                            <span class="<?php echo $proyecto['diferencia'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo ($proyecto['diferencia'] > 0 ? '+' : '') . number_format($proyecto['diferencia'], 1); ?>h
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <strong class="<?php echo $proyecto['porcentaje'] > 100 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo $proyecto['porcentaje']; ?>%
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($proyecto['estado'] === 'excedido'): ?>
                                                <span class="badge bg-danger">Excedido</span>
                                            <?php elseif ($proyecto['estado'] === 'atencion'): ?>
                                                <span class="badge bg-warning text-dark">Atención</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">OK</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" style="min-width: 150px;">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar 
                                                    <?php echo $proyecto['estado'] === 'excedido' ? 'bg-danger' : ($proyecto['estado'] === 'atencion' ? 'bg-warning' : 'bg-success'); ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo min($proyecto['porcentaje'], 100); ?>%;" 
                                                     aria-valuenow="<?php echo $proyecto['porcentaje']; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info m-4">
                            <i class="fas fa-info-circle me-2"></i>
                            No hay datos disponibles para el período seleccionado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Empleados -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-trophy me-2"></i>Top 5 Empleados con Más Horas
                </div>
                <div class="card-body p-0">
                    <?php if ($topEmpleados && count($topEmpleados) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Empleado</th>
                                        <th class="text-end">Horas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $posicion = 1; foreach ($topEmpleados as $empleado): ?>
                                    <tr>
                                        <td>
                                            <?php if ($posicion == 1): ?>
                                                <span class="text-warning"><i class="fas fa-medal fa-lg"></i></span>
                                            <?php elseif ($posicion == 2): ?>
                                                <span class="text-secondary"><i class="fas fa-medal fa-lg"></i></span>
                                            <?php elseif ($posicion == 3): ?>
                                                <span class="text-danger"><i class="fas fa-medal fa-lg"></i></span>
                                            <?php else: ?>
                                                <?php echo $posicion; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?></strong></td>
                                        <td class="text-end">
                                            <strong><?php echo round($empleado['total_minutos'] / 60, 1); ?>h</strong>
                                        </td>
                                    </tr>
                                    <?php $posicion++; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info m-4">
                            <i class="fas fa-info-circle me-2"></i>
                            No hay datos disponibles.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Scripts para gráficos
$labelsProyectos = json_encode(array_column($horasPorProyecto ?? [], 'nombre'));
$dataHorasProyectos = json_encode(array_map(function($p) { return round($p['horas_reales_minutos'] / 60, 2); }, $horasPorProyecto ?? []));

$labelsDistribucion = $labelsProyectos;
$dataDistribucion = $dataHorasProyectos;

$labelsEvolucion = json_encode(array_map(function($d) { return date('d/m', strtotime($d['fecha'])); }, $horasPorDia ?? []));
$dataEvolucion = json_encode(array_map(function($d) { return round($d['total_minutos'] / 60, 2); }, $horasPorDia ?? []));

$pageScripts = "<script>
// Colores
const colors = [
    'rgba(13, 110, 253, 0.8)',
    'rgba(25, 135, 84, 0.8)',
    'rgba(255, 193, 7, 0.8)',
    'rgba(220, 53, 69, 0.8)',
    'rgba(111, 66, 193, 0.8)',
    'rgba(253, 126, 20, 0.8)'
];

// Gráfico de Barras - Horas por Proyecto
const ctx1 = document.getElementById('horasProyectosChart');
if (ctx1 && $labelsProyectos) {
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: $labelsProyectos,
            datasets: [{
                label: 'Horas Trabajadas',
                data: $dataHorasProyectos,
                backgroundColor: colors,
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
                y: { 
                    beginAtZero: true,
                    ticks: { callback: function(value) { return value + 'h'; } }
                }
            }
        }
    });
}

// Gráfico de Dona - Distribución
const ctx2 = document.getElementById('distribucionChart');
if (ctx2 && $labelsDistribucion) {
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: $labelsDistribucion,
            datasets: [{
                data: $dataDistribucion,
                backgroundColor: colors,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
}

// Gráfico de Línea - Evolución
const ctx3 = document.getElementById('evolucionChart');
if (ctx3 && $labelsEvolucion) {
    new Chart(ctx3, {
        type: 'line',
        data: {
            labels: $labelsEvolucion,
            datasets: [{
                label: 'Horas',
                data: $dataEvolucion,
                borderColor: 'rgba(13, 110, 253, 1)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: { callback: function(value) { return value + 'h'; } }
                }
            }
        }
    });
}
</script>";
echo $pageScripts;
?>

<?php include '../includes/footer.php'; ?>