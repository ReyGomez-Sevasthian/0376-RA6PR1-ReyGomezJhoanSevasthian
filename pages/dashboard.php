<?php
/**
 * Dashboard - TimeTrack Pro
 * 
 * Página principal con resumen según el rol del usuario
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Obtener información del usuario
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Variables para las estadísticas
$stats = [];

// ============================================
// DATOS PARA ADMIN
// ============================================
if ($userRole === 'admin') {
    // Total de empleados
    $totalEmpleados = executeQuery("SELECT COUNT(*) as total FROM usuarios WHERE rol_id = 3 AND activo = 1");
    $stats['empleados'] = $totalEmpleados[0]['total'] ?? 0;
    
    // Total de proyectos activos
    $totalProyectos = executeQuery("SELECT COUNT(*) as total FROM proyectos WHERE activo = 1");
    $stats['proyectos'] = $totalProyectos[0]['total'] ?? 0;
    
    // Horas trabajadas hoy
    $horasHoy = executeQuery("
        SELECT COALESCE(SUM(duracion_minutos), 0) as total 
        FROM registros_horas_proyecto 
        WHERE fecha = CURDATE()
    ");
    $stats['horas_hoy'] = round(($horasHoy[0]['total'] ?? 0) / 60, 1);
    
    // Alertas pendientes
    $alertasPendientes = executeQuery("
        SELECT COUNT(*) as total 
        FROM alertas 
        WHERE leida = 0 AND fecha >= CURDATE() - INTERVAL 7 DAY
    ");
    $stats['alertas'] = $alertasPendientes[0]['total'] ?? 0;
    
    // Empleados que no han fichado hoy
    $noFicharon = executeQuery("
        SELECT COUNT(*) as total 
        FROM usuarios u 
        WHERE u.rol_id = 3 AND u.activo = 1 
        AND u.id NOT IN (
            SELECT DISTINCT usuario_id 
            FROM registros_fichaje 
            WHERE DATE(fecha_hora) = CURDATE()
        )
    ");
    $stats['no_ficharon'] = $noFicharon[0]['total'] ?? 0;
    
    // Últimos fichajes
    $ultimosFichajes = executeQuery("
        SELECT u.nombre, u.apellidos, rf.tipo_registro, rf.fecha_hora
        FROM registros_fichaje rf
        JOIN usuarios u ON rf.usuario_id = u.id
        ORDER BY rf.fecha_hora DESC
        LIMIT 10
    ");
    
    // Horas por proyecto (para gráfico)
    $horasProyectos = executeQuery("
        SELECT p.nombre, COALESCE(SUM(rhp.duracion_minutos), 0) as horas
        FROM proyectos p
        LEFT JOIN registros_horas_proyecto rhp ON p.id = rhp.proyecto_id
        WHERE p.activo = 1
        GROUP BY p.id, p.nombre
        ORDER BY horas DESC
    ");
}

// ============================================
// DATOS PARA MANAGER
// ============================================
elseif ($userRole === 'manager') {
    // Empleados a cargo
    $empleadosCargo = executeQuery("
        SELECT COUNT(DISTINCT ep.usuario_id) as total
        FROM equipo_proyecto ep
        JOIN usuarios u ON ep.usuario_id = u.id
        WHERE ep.usuario_id != ? AND u.activo = 1
    ", [$userId]);
    $stats['empleados'] = $empleadosCargo[0]['total'] ?? 0;
    
    // Proyectos asignados
    $proyectosAsignados = executeQuery("
        SELECT COUNT(*) as total
        FROM equipo_proyecto
        WHERE usuario_id = ?
    ", [$userId]);
    $stats['proyectos'] = $proyectosAsignados[0]['total'] ?? 0;
    
    // Horas trabajadas hoy (su equipo)
    $horasHoy = executeQuery("
        SELECT COALESCE(SUM(rhp.duracion_minutos), 0) as total
        FROM registros_horas_proyecto rhp
        JOIN equipo_proyecto ep ON rhp.usuario_id = ep.usuario_id
        WHERE ep.usuario_id = ? AND rhp.fecha = CURDATE()
    ", [$userId]);
    $stats['horas_hoy'] = round(($horasHoy[0]['total'] ?? 0) / 60, 1);
    
    // Alertas de su equipo
    $alertasEquipo = executeQuery("
        SELECT COUNT(*) as total
        FROM alertas a
        JOIN equipo_proyecto ep ON a.usuario_id = ep.usuario_id
        WHERE ep.usuario_id = ? AND a.leida = 0 AND a.fecha >= CURDATE() - INTERVAL 7 DAY
    ", [$userId]);
    $stats['alertas'] = $alertasEquipo[0]['total'] ?? 0;
    
    // Últimos fichajes de su equipo
    $ultimosFichajes = executeQuery("
        SELECT u.nombre, u.apellidos, rf.tipo_registro, rf.fecha_hora
        FROM registros_fichaje rf
        JOIN usuarios u ON rf.usuario_id = u.id
        JOIN equipo_proyecto ep ON u.id = ep.usuario_id
        WHERE ep.usuario_id = ?
        ORDER BY rf.fecha_hora DESC
        LIMIT 10
    ", [$userId]);
}

// ============================================
// DATOS PARA EMPLEADO
// ============================================
else {
    // Horas trabajadas hoy
    $horasHoy = executeQuery("
        SELECT COALESCE(SUM(duracion_minutos), 0) as total
        FROM registros_horas_proyecto
        WHERE usuario_id = ? AND fecha = CURDATE()
    ", [$userId]);
    $stats['horas_hoy'] = round(($horasHoy[0]['total'] ?? 0) / 60, 1);
    
    // Horas trabajadas esta semana
    $horasSemana = executeQuery("
        SELECT COALESCE(SUM(duracion_minutos), 0) as total
        FROM registros_horas_proyecto
        WHERE usuario_id = ? AND YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)
    ", [$userId]);
    $stats['horas_semana'] = round(($horasSemana[0]['total'] ?? 0) / 60, 1);
    
    // Horas trabajadas este mes
    $horasMes = executeQuery("
        SELECT COALESCE(SUM(duracion_minutos), 0) as total
        FROM registros_horas_proyecto
        WHERE usuario_id = ? AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
    ", [$userId]);
    $stats['horas_mes'] = round(($horasMes[0]['total'] ?? 0) / 60, 1);
    
    // Verificar si ya fichó hoy
    $fichajeHoy = executeQuery("
        SELECT tipo_registro, fecha_hora
        FROM registros_fichaje
        WHERE usuario_id = ? AND DATE(fecha_hora) = CURDATE()
        ORDER BY fecha_hora DESC
        LIMIT 1
    ", [$userId]);
    $stats['ultimo_fichaje'] = $fichajeHoy[0] ?? null;
    
    // Proyectos asignados
    $proyectosUsuario = executeQuery("
        SELECT p.id, p.nombre, p.descripcion
        FROM proyectos p
        JOIN equipo_proyecto ep ON p.id = ep.proyecto_id
        WHERE ep.usuario_id = ? AND p.activo = 1
    ", [$userId]);
}

// Incluir header
$pageTitle = 'Dashboard';
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="fas fa-home me-2"></i>Dashboard
            </h2>
            <p class="text-muted">
                <?php
                $hora = date('H');
                if ($hora < 12) echo 'Buenos días';
                elseif ($hora < 19) echo 'Buenas tardes';
                else echo 'Buenas noches';
                ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </p>
        </div>
    </div>

    <!-- Tarjetas de estadísticas -->
    <div class="row">
        <?php if ($userRole === 'admin'): ?>
        <!-- Total Empleados -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['empleados']; ?></div>
                    <div class="stat-label">Empleados Activos</div>
                </div>
            </div>
        </div>
        
        <!-- Total Proyectos -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['proyectos']; ?></div>
                    <div class="stat-label">Proyectos Activos</div>
                </div>
            </div>
        </div>
        
        <!-- Horas Hoy -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['horas_hoy']; ?>h</div>
                    <div class="stat-label">Horas Trabajadas Hoy</div>
                </div>
            </div>
        </div>
        
        <!-- Alertas -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['alertas']; ?></div>
                    <div class="stat-label">Alertas Pendientes</div>
                </div>
            </div>
        </div>
        
        <?php elseif ($userRole === 'manager'): ?>
        <!-- Empleados a Cargo -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['empleados']; ?></div>
                    <div class="stat-label">Empleados a Cargo</div>
                </div>
            </div>
        </div>
        
        <!-- Proyectos -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['proyectos']; ?></div>
                    <div class="stat-label">Proyectos Asignados</div>
                </div>
            </div>
        </div>
        
        <!-- Horas Hoy Equipo -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['horas_hoy']; ?>h</div>
                    <div class="stat-label">Horas Equipo Hoy</div>
                </div>
            </div>
        </div>
        
        <!-- Alertas Equipo -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-bell"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['alertas']; ?></div>
                    <div class="stat-label">Alertas de Equipo</div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Horas Hoy -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['horas_hoy']; ?>h</div>
                    <div class="stat-label">Horas Hoy</div>
                </div>
            </div>
        </div>
        
        <!-- Horas Semana -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['horas_semana']; ?>h</div>
                    <div class="stat-label">Horas Esta Semana</div>
                </div>
            </div>
        </div>
        
        <!-- Horas Mes -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['horas_mes']; ?>h</div>
                    <div class="stat-label">Horas Este Mes</div>
                </div>
            </div>
        </div>
        
        <!-- Estado Fichaje -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon <?php echo ($stats['ultimo_fichaje'] && $stats['ultimo_fichaje']['tipo_registro'] === 'entrada') ? 'success' : 'danger'; ?>">
                    <i class="fas fa-<?php echo ($stats['ultimo_fichaje'] && $stats['ultimo_fichaje']['tipo_registro'] === 'entrada') ? 'check-circle' : 'times-circle'; ?>"></i>
                </div>
                <div>
                    <div class="stat-value">
                        <?php echo ($stats['ultimo_fichaje'] && $stats['ultimo_fichaje']['tipo_registro'] === 'entrada') ? 'Fichado' : 'No Fichado'; ?>
                    </div>
                    <div class="stat-label">
                        <?php if ($stats['ultimo_fichaje']): ?>
                            Último: <?php echo date('H:i', strtotime($stats['ultimo_fichaje']['fecha_hora'])); ?>
                        <?php else: ?>
                            Estado Actual
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Contenido específico por rol -->
    <div class="row">
        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <!-- Últimos Fichajes -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-clock me-2"></i>Últimos Fichajes</span>
                    <a href="fichajes.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Tipo</th>
                                    <th>Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($ultimosFichajes) && count($ultimosFichajes) > 0): ?>
                                    <?php foreach ($ultimosFichajes as $fichaje): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fichaje['nombre'] . ' ' . $fichaje['apellidos']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $fichaje['tipo_registro'] === 'entrada' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($fichaje['tipo_registro']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('H:i:s', strtotime($fichaje['fecha_hora'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No hay fichajes recientes</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de Horas por Proyecto (solo Admin) -->
        <?php if ($userRole === 'admin' && isset($horasProyectos) && count($horasProyectos) > 0): ?>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>Horas por Proyecto
                </div>
                <div class="card-body">
                    <canvas id="horasProyectosChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Proyectos del Empleado -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-project-diagram me-2"></i>Mis Proyectos
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if (isset($proyectosUsuario) && count($proyectosUsuario) > 0): ?>
                            <?php foreach ($proyectosUsuario as $proyecto): ?>
                            <a href="mis-horas.php?proyecto=<?php echo $proyecto['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($proyecto['nombre']); ?></h6>
                                </div>
                                <p class="mb-1 small text-muted"><?php echo htmlspecialchars(substr($proyecto['descripcion'] ?? '', 0, 100)); ?></p>
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No tienes proyectos asignados.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="fichar.php" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-clock fa-2x mb-2"></i><br>
                                Fichar Entrada/Salida
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="mis-horas.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-history fa-2x mb-2"></i><br>
                                Ver Mis Horas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Scripts específicos de la página
$pageScripts = '';
if ($userRole === 'admin' && isset($horasProyectos) && count($horasProyectos) > 0) {
    $labels = json_encode(array_column($horasProyectos, 'nombre'));
    $data = json_encode(array_column($horasProyectos, 'horas'));
    
    $pageScripts = "<script>
    // Gráfico de Horas por Proyecto
    const ctx = document.getElementById('horasProyectosChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: $labels,
                datasets: [{
                    label: 'Horas Trabajadas',
                    data: $data,
                    backgroundColor: [
                        'rgba(13, 110, 253, 0.8)',
                        'rgba(25, 135, 84, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(111, 66, 193, 0.8)'
                    ],
                    borderColor: [
                        'rgba(13, 110, 253, 1)',
                        'rgba(25, 135, 84, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(111, 66, 193, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return Math.round(value / 60) + 'h';
                            }
                        }
                    }
                }
            }
        });
    }
    </script>";
}
echo $pageScripts;
?>

<?php include '../includes/footer.php'; ?>