<?php
/**
 * Alertas - TimeTrack Pro
 * 
 * Panel de alertas para Admin y Manager
 * Muestra empleados con incumplimientos de jornada
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo admin y manager pueden ver alertas
if (!hasRole(['admin', 'manager'])) {
    setFlashMessage('danger', 'No tienes permiso para acceder a esta página.');
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// ============================================
// PROCESAR ACCIONES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['marcar_leida'])) {
        $alertaId = filter_var($_POST['marcar_leida'], FILTER_VALIDATE_INT);
        if ($alertaId) {
            executeUpdate("UPDATE alertas SET leida = 1 WHERE id = ?", [$alertaId]);
            setFlashMessage('success', 'Alerta marcada como leída.');
        }
    } elseif (isset($_POST['marcar_todas_leidas'])) {
        if ($userRole === 'admin') {
            executeUpdate("UPDATE alertas SET leida = 1 WHERE leida = 0", []);
        } else {
            // Manager solo marca las de su equipo
            executeUpdate("
                UPDATE alertas a 
                JOIN equipo_proyecto ep ON a.usuario_id = ep.usuario_id 
                SET a.leida = 1 
                WHERE ep.usuario_id = ? AND a.leida = 0
            ", [$userId]);
        }
        setFlashMessage('success', 'Todas las alertas marcadas como leídas.');
    }
}

// ============================================
// GENERAR ALERTAS AUTOMÁTICAS (diarias)
// ============================================
// Esta función debería ejecutarse vía cron, pero la ejecutamos al cargar la página
function generarAlertasAutomaticas() {
    $pdo = getDBConnection();
    if (!$pdo) return;
    
    try {
        // 1. Alertas por no haber fichado hoy
        $sqlNoFicharon = "
            INSERT INTO alertas (usuario_id, tipo_alerta, descripcion, fecha)
            SELECT 
                u.id,
                'no_fichado',
                CONCAT('No registró entrada el ', CURDATE()),
                CURDATE()
            FROM usuarios u
            WHERE u.rol_id = 3 
                AND u.activo = 1
                AND u.id NOT IN (
                    SELECT DISTINCT usuario_id 
                    FROM registros_fichaje 
                    WHERE DATE(fecha_hora) = CURDATE() 
                        AND tipo_registro = 'entrada'
                )
                AND u.id NOT IN (
                    SELECT usuario_id FROM alertas 
                    WHERE tipo_alerta = 'no_fichado' AND fecha = CURDATE()
                )
        ";
        $pdo->exec($sqlNoFicharon);
        
        // 2. Alertas por llegada tarde (después de las 9:00)
        $sqlLlegadasTarde = "
            INSERT INTO alertas (usuario_id, tipo_alerta, descripcion, fecha)
            SELECT 
                u.id,
                'llegada_tarde',
                CONCAT('Llegó tarde: ', TIME(rf.fecha_hora)),
                CURDATE()
            FROM usuarios u
            JOIN registros_fichaje rf ON u.id = rf.usuario_id
            WHERE u.rol_id = 3 
                AND u.activo = 1
                AND rf.tipo_registro = 'entrada'
                AND DATE(rf.fecha_hora) = CURDATE()
                AND TIME(rf.fecha_hora) > '09:00:00'
                AND u.id NOT IN (
                    SELECT usuario_id FROM alertas 
                    WHERE tipo_alerta = 'llegada_tarde' AND fecha = CURDATE()
                )
        ";
        $pdo->exec($sqlLlegadasTarde);
        
        // 3. Alertas por horas insuficientes del día anterior
        $sqlHorasInsuficientes = "
            INSERT INTO alertas (usuario_id, tipo_alerta, descripcion, fecha)
            SELECT 
                u.id,
                'horas_insuficientes',
                CONCAT('Solo trabajó ', COALESCE(ROUND(SUM(rhp.duracion_minutos)/60, 1), 0), ' horas'),
                CURDATE()
            FROM usuarios u
            LEFT JOIN registros_horas_proyecto rhp ON u.id = rhp.usuario_id 
                AND rhp.fecha = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            WHERE u.rol_id = 3 
                AND u.activo = 1
                AND (
                    SELECT COALESCE(SUM(duracion_minutos), 0) 
                    FROM registros_horas_proyecto 
                    WHERE usuario_id = u.id AND fecha = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                ) < 480  -- Menos de 8 horas (480 minutos)
                AND u.id NOT IN (
                    SELECT usuario_id FROM alertas 
                    WHERE tipo_alerta = 'horas_insuficientes' AND fecha = CURDATE()
                )
            GROUP BY u.id
        ";
        $pdo->exec($sqlHorasInsuficientes);
        
    } catch (PDOException $e) {
        error_log("Error generando alertas: " . $e->getMessage());
    }
}

// Ejecutar generación de alertas
generarAlertasAutomaticas();

// ============================================
// OBTENER ALERTAS
// ============================================
$filtroTipo = isset($_GET['tipo']) ? sanitizeInput($_GET['tipo']) : '';
$filtroEstado = isset($_GET['estado']) ? sanitizeInput($_GET['estado']) : 'pendientes';

$sql = "
    SELECT 
        a.id,
        a.tipo_alerta,
        a.descripcion,
        a.fecha,
        a.leida,
        a.creado_en,
        u.id as usuario_id,
        u.nombre,
        u.apellidos,
        u.email
    FROM alertas a
    JOIN usuarios u ON a.usuario_id = u.id
";

$params = [];

if ($userRole === 'manager') {
    // Manager solo ve alertas de su equipo
    $sql .= " JOIN equipo_proyecto ep ON a.usuario_id = ep.usuario_id WHERE ep.usuario_id = ?";
    $params[] = $userId;
    
    if ($filtroTipo) {
        $sql .= " AND a.tipo_alerta = ?";
        $params[] = $filtroTipo;
    }
} else {
    if ($filtroTipo) {
        $sql .= " WHERE a.tipo_alerta = ?";
        $params[] = $filtroTipo;
    }
}

if ($filtroEstado === 'pendientes') {
    $sql .= " AND a.leida = 0";
} elseif ($filtroEstado === 'leidas') {
    $sql .= " AND a.leida = 1";
}

$sql .= " ORDER BY a.creado_en DESC LIMIT 100";

$alertas = executeQuery($sql, $params);

// Contadores
$totalAlertas = executeQuery("
    SELECT COUNT(*) as total FROM alertas a
    " . ($userRole === 'manager' ? "JOIN equipo_proyecto ep ON a.usuario_id = ep.usuario_id WHERE ep.usuario_id = ?" : "")
, $userRole === 'manager' ? [$userId] : []);
$totalPendientes = executeQuery("
    SELECT COUNT(*) as total FROM alertas a WHERE a.leida = 0
    " . ($userRole === 'manager' ? "AND a.usuario_id IN (SELECT usuario_id FROM equipo_proyecto WHERE usuario_id = ?)" : "")
, $userRole === 'manager' ? [$userId] : []);

// Lista roja: empleados con múltiples alertas esta semana
$listaRoja = executeQuery("
    SELECT 
        u.id,
        u.nombre,
        u.apellidos,
        u.email,
        COUNT(a.id) as total_alertas,
        GROUP_CONCAT(DISTINCT a.tipo_alerta) as tipos
    FROM usuarios u
    JOIN alertas a ON u.id = a.usuario_id
    WHERE a.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND u.rol_id = 3 AND u.activo = 1
    " . ($userRole === 'manager' ? "AND u.id IN (SELECT usuario_id FROM equipo_proyecto WHERE usuario_id = ?)" : "") . "
    GROUP BY u.id
    HAVING total_alertas >= 2
    ORDER BY total_alertas DESC
", $userRole === 'manager' ? [$userId] : []);

// Incluir header
$pageTitle = 'Alertas';
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="fas fa-bell me-2"></i>Panel de Alertas
            </h2>
            <p class="text-muted">Monitoreo de incumplimientos de jornada laboral</p>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $totalAlertas[0]['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Alertas</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $totalPendientes[0]['total'] ?? 0; ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo count($listaRoja); ?></div>
                    <div class="stat-label">Lista Roja</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value">
                        <?php echo ($totalAlertas[0]['total'] ?? 0) - ($totalPendientes[0]['total'] ?? 0); ?>
                    </div>
                    <div class="stat-label">Atendidas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista Roja -->
    <?php if (count($listaRoja) > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ⚠️ LISTA ROJA - Empleados con Múltiples Incumplimientos (Últimos 7 días)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Email</th>
                                    <th>Total Alertas</th>
                                    <th>Tipos de Alerta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaRoja as $empleado): ?>
                                <tr class="table-danger">
                                    <td>
                                        <strong><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($empleado['email']); ?></td>
                                    <td>
                                        <span class="badge bg-danger fs-6"><?php echo $empleado['total_alertas']; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $tipos = explode(',', $empleado['tipos']);
                                        foreach ($tipos as $tipo):
                                            $tipoLabel = trim($tipo);
                                            $clase = 'secondary';
                                            if ($tipoLabel === 'horas_insuficientes') $clase = 'warning';
                                            elseif ($tipoLabel === 'llegada_tarde') $clase = 'info';
                                            elseif ($tipoLabel === 'salida_temprana') $clase = 'warning';
                                            elseif ($tipoLabel === 'no_fichado') $clase = 'danger';
                                        ?>
                                            <span class="badge bg-<?php echo $clase; ?>"><?php echo ucfirst(str_replace('_', ' ', $tipoLabel)); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <a href="perfil-empleado.php?id=<?php echo $empleado['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros y Acciones -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" class="row g-2">
                <div class="col-auto">
                    <select class="form-select" name="tipo">
                        <option value="">Todos los tipos</option>
                        <option value="horas_insuficientes" <?php echo $filtroTipo === 'horas_insuficientes' ? 'selected' : ''; ?>>Horas Insuficientes</option>
                        <option value="llegada_tarde" <?php echo $filtroTipo === 'llegada_tarde' ? 'selected' : ''; ?>>Llegada Tarde</option>
                        <option value="salida_temprana" <?php echo $filtroTipo === 'salida_temprana' ? 'selected' : ''; ?>>Salida Temprana</option>
                        <option value="no_fichado" <?php echo $filtroTipo === 'no_fichado' ? 'selected' : ''; ?>>No Fichado</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select class="form-select" name="estado">
                        <option value="pendientes" <?php echo $filtroEstado === 'pendientes' ? 'selected' : ''; ?>>Pendientes</option>
                        <option value="leidas" <?php echo $filtroEstado === 'leidas' ? 'selected' : ''; ?>>Leídas</option>
                        <option value="todas" <?php echo $filtroEstado === 'todas' ? 'selected' : ''; ?>>Todas</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <?php if ($totalPendientes[0]['total'] > 0): ?>
            <form method="POST" class="d-inline">
                <button type="submit" name="marcar_todas_leidas" class="btn btn-success" 
                        onclick="return confirm('¿Marcar todas las alertas como leídas?')">
                    <i class="fas fa-check-double me-1"></i> Marcar todas como leídas
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabla de Alertas -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list me-2"></i>
            <?php echo $filtroEstado === 'leidas' ? 'Alertas Leídas' : ($filtroEstado === 'todas' ? 'Todas las Alertas' : 'Alertas Pendientes'); ?>
        </div>
        <div class="card-body p-0">
            <?php if ($alertas && count($alertas) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>Empleado</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alertas as $alerta): ?>
                            <tr class="<?php echo $alerta['leida'] ? 'table-secondary' : ''; ?>">
                                <td>
                                    <?php if ($alerta['leida']): ?>
                                        <span class="badge bg-success">Leída</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($alerta['nombre'] . ' ' . $alerta['apellidos']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($alerta['email']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $tipoLabel = $alerta['tipo_alerta'];
                                    $clase = 'secondary';
                                    $icono = 'fa-bell';
                                    if ($tipoLabel === 'horas_insuficientes') { $clase = 'warning'; $icono = 'fa-clock'; }
                                    elseif ($tipoLabel === 'llegada_tarde') { $clase = 'info'; $icono = 'fa-user-clock'; }
                                    elseif ($tipoLabel === 'salida_temprana') { $clase = 'warning'; $icono = 'fa-door-open'; }
                                    elseif ($tipoLabel === 'no_fichado') { $clase = 'danger'; $icono = 'fa-user-times'; }
                                    ?>
                                    <span class="badge bg-<?php echo $clase; ?>">
                                        <i class="fas <?php echo $icono; ?> me-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $tipoLabel)); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($alerta['descripcion']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($alerta['fecha'])); ?></td>
                                <td>
                                    <?php if (!$alerta['leida']): ?>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="marcar_leida" value="<?php echo $alerta['id']; ?>" 
                                                class="btn btn-sm btn-outline-success" 
                                                onclick="return confirm('¿Marcar como leída?')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="perfil-empleado.php?id=<?php echo $alerta['usuario_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-user"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info m-4">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay alertas que mostrar con los filtros seleccionados.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>