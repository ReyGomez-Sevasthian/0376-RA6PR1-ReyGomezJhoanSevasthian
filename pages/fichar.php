<?php
/**
 * Fichar - TimeTrack Pro
 * 
 * Página para registrar entrada y salida de empleados
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo empleados y admin pueden fichar
if (!hasRole(['empleado', 'admin'])) {
    setFlashMessage('danger', 'No tienes permiso para acceder a esta página.');
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$mensaje = '';
$tipoMensaje = '';

// ============================================
// PROCESAR FICHAJE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = sanitizeInput($_POST['accion']);
    
    if (in_array($accion, ['entrada', 'salida'])) {
        // Obtener IP y user agent
        $ipOrigen = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        
        // Insertar registro de fichaje
        $sql = "INSERT INTO registros_fichaje (usuario_id, tipo_registro, fecha_hora, ip_origen, user_agent) 
                VALUES (?, ?, NOW(), ?, ?)";
        
        $resultado = executeUpdate($sql, [$userId, $accion, $ipOrigen, $userAgent]);
        
        if ($resultado) {
            $tipoMensaje = 'success';
            if ($accion === 'entrada') {
                $mensaje = '✅ Registro de entrada guardado exitosamente.';
            } else {
                $mensaje = '✅ Registro de salida guardado exitosamente.';
            }
            
            // Crear alerta si es salida temprana (antes de las 18:00)
            if ($accion === 'salida') {
                $horaActual = date('H:i:s');
                if ($horaActual < HORA_SALIDA_NORMAL) {
                    $sqlAlerta = "INSERT INTO alertas (usuario_id, tipo_alerta, descripcion, fecha) 
                                  VALUES (?, 'salida_temprana', ?, CURDATE())";
                    executeUpdate($sqlAlerta, [$userId, 'Salida temprana detectada: ' . $horaActual]);
                }
            }
        } else {
            $tipoMensaje = 'danger';
            $mensaje = '❌ Error al registrar el fichaje. Intente nuevamente.';
        }
    }
}

// ============================================
// OBTENER ESTADO ACTUAL
// ============================================
// Último fichaje del día
$ultimoFichaje = executeQuery("
    SELECT tipo_registro, fecha_hora 
    FROM registros_fichaje 
    WHERE usuario_id = ? AND DATE(fecha_hora) = CURDATE()
    ORDER BY fecha_hora DESC 
    LIMIT 1
", [$userId]);

$estadoActual = 'no_fichado';
$puedeFichar = 'entrada';
$horaUltimoFichaje = null;

if ($ultimoFichaje && count($ultimoFichaje) > 0) {
    $horaUltimoFichaje = $ultimoFichaje[0]['fecha_hora'];
    if ($ultimoFichaje[0]['tipo_registro'] === 'entrada') {
        $estadoActual = 'entrada';
        $puedeFichar = 'salida';
    } else {
        $estadoActual = 'salida';
        $puedeFichar = 'entrada'; // Puede volver a entrar si ya salió
    }
}

// Historial de fichajes de hoy
$fichajesHoy = executeQuery("
    SELECT tipo_registro, fecha_hora 
    FROM registros_fichaje 
    WHERE usuario_id = ? AND DATE(fecha_hora) = CURDATE()
    ORDER BY fecha_hora DESC
", [$userId]);

// Horas trabajadas hoy por proyecto
$horasHoyPorProyecto = executeQuery("
    SELECT p.nombre as proyecto, rhp.hora_inicio, rhp.hora_fin, rhp.duracion_minutos, rhp.descripcion
    FROM registros_horas_proyecto rhp
    JOIN proyectos p ON rhp.proyecto_id = p.id
    WHERE rhp.usuario_id = ? AND rhp.fecha = CURDATE()
    ORDER BY rhp.hora_inicio DESC
", [$userId]);

// Proyectos asignados al usuario
$proyectosUsuario = executeQuery("
    SELECT p.id, p.nombre
    FROM proyectos p
    JOIN equipo_proyecto ep ON p.id = ep.proyecto_id
    WHERE ep.usuario_id = ? AND p.activo = 1
    ORDER BY p.nombre
", [$userId]);

// ============================================
// PROCESAR REGISTRO DE HORAS POR PROYECTO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_horas'])) {
    $proyectoId = filter_var($_POST['proyecto_id'], FILTER_VALIDATE_INT);
    $fecha = sanitizeInput($_POST['fecha']);
    $horaInicio = sanitizeInput($_POST['hora_inicio']);
    $horaFin = sanitizeInput($_POST['hora_fin']);
    $descripcion = sanitizeInput($_POST['descripcion'] ?? '');
    
    // Validaciones
    if (!$proyectoId || !$fecha || !$horaInicio || !$horaFin) {
        $tipoMensaje = 'warning';
        $mensaje = '⚠️ Por favor complete todos los campos obligatorios.';
    } else {
        // Calcular duración
        $duracionMinutos = calculateMinutesDifference($horaInicio, $horaFin);
        
        if ($duracionMinutos <= 0) {
            $tipoMensaje = 'warning';
            $mensaje = '⚠️ La hora de fin debe ser posterior a la hora de inicio.';
        } else {
            // Verificar que el usuario está asignado al proyecto
            $verificarProyecto = executeQuery("
                SELECT COUNT(*) as count 
                FROM equipo_proyecto 
                WHERE usuario_id = ? AND proyecto_id = ?
            ", [$userId, $proyectoId]);
            
            if (!$verificarProyecto || $verificarProyecto[0]['count'] == 0) {
                $tipoMensaje = 'danger';
                $mensaje = '❌ No estás asignado a este proyecto.';
            } else {
                // Insertar registro
                $sql = "INSERT INTO registros_horas_proyecto 
                        (usuario_id, proyecto_id, fecha, hora_inicio, hora_fin, duracion_minutos, descripcion) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $resultado = executeUpdate($sql, [$userId, $proyectoId, $fecha, $horaInicio, $horaFin, $duracionMinutos, $descripcion]);
                
                if ($resultado) {
                    $tipoMensaje = 'success';
                    $mensaje = '✅ Horas registradas exitosamente. Total: ' . formatDuration($duracionMinutos);
                    
                    // Recargar horas del día
                    $horasHoyPorProyecto = executeQuery("
                        SELECT p.nombre as proyecto, rhp.hora_inicio, rhp.hora_fin, rhp.duracion_minutos, rhp.descripcion
                        FROM registros_horas_proyecto rhp
                        JOIN proyectos p ON rhp.proyecto_id = p.id
                        WHERE rhp.usuario_id = ? AND rhp.fecha = CURDATE()
                        ORDER BY rhp.hora_inicio DESC
                    ", [$userId]);
                } else {
                    $tipoMensaje = 'danger';
                    $mensaje = '❌ Error al registrar las horas. Intente nuevamente.';
                }
            }
        }
    }
}

// Calcular total de horas hoy
$totalHorasHoy = 0;
if ($horasHoyPorProyecto) {
    foreach ($horasHoyPorProyecto as $registro) {
        $totalHorasHoy += $registro['duracion_minutos'];
    }
}

// Incluir header
$pageTitle = 'Fichar';
ob_start();
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="fas fa-clock me-2"></i>Registro de Fichaje
            </h2>
            <p class="text-muted">Registra tu entrada, salida y horas por proyecto</p>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Fichaje -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-id-card me-2"></i>Control de Asistencia
                </div>
                <div class="card-body">
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show" role="alert">
                            <?php echo $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Estado Actual -->
                    <div class="text-center mb-4">
                        <div class="display-4 mb-2">
                            <?php if ($estadoActual === 'entrada'): ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i></span>
                            <?php elseif ($estadoActual === 'salida'): ?>
                                <span class="text-danger"><i class="fas fa-door-open"></i></span>
                            <?php else: ?>
                                <span class="text-muted"><i class="fas fa-user-clock"></i></span>
                            <?php endif; ?>
                        </div>
                        <h4>
                            <?php if ($estadoActual === 'entrada'): ?>
                                <span class="text-success">Has registrado entrada</span>
                            <?php elseif ($estadoActual === 'salida'): ?>
                                <span class="text-danger">Has registrado salida</span>
                            <?php else: ?>
                                <span class="text-muted">No has fichado hoy</span>
                            <?php endif; ?>
                        </h4>
                        <?php if ($horaUltimoFichaje): ?>
                            <p class="text-muted">
                                Último registro: <?php echo date('H:i:s', strtotime($horaUltimoFichaje)); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Botón de Fichaje -->
                    <div class="d-grid gap-2">
                        <?php if ($puedeFichar === 'entrada'): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="accion" value="entrada" class="btn btn-success btn-lg py-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Registrar ENTRADA
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="accion" value="salida" class="btn btn-warning btn-lg py-3">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Registrar SALIDA
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Instrucciones -->
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instrucciones:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Registra tu entrada al comenzar la jornada</li>
                            <li>Registra tu salida al finalizar la jornada</li>
                            <li>Puedes registrar múltiples entradas/salidas si sales y regresas</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Horas por Proyecto -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-project-diagram me-2"></i>Horas por Proyecto (Hoy)</span>
                    <span class="badge bg-primary"><?php echo formatDuration($totalHorasHoy); ?></span>
                </div>
                <div class="card-body">
                    <!-- Formulario para registrar horas -->
                    <h6 class="mb-3">Registrar horas trabajadas:</h6>
                    <form method="POST" class="mb-4">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Proyecto *</label>
                                <select class="form-select" name="proyecto_id" required>
                                    <option value="">Seleccionar proyecto...</option>
                                    <?php if ($proyectosUsuario): ?>
                                        <?php foreach ($proyectosUsuario as $proyecto): ?>
                                            <option value="<?php echo $proyecto['id']; ?>">
                                                <?php echo htmlspecialchars($proyecto['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha *</label>
                                <input type="date" class="form-control" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora Inicio *</label>
                                <input type="time" class="form-control" name="hora_inicio" value="09:00" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora Fin *</label>
                                <input type="time" class="form-control" name="hora_fin" value="18:00" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción (opcional)</label>
                                <textarea class="form-control" name="descripcion" rows="2" placeholder="Descripción de las tareas realizadas..."></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="registrar_horas" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Registrar Horas
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Lista de horas registradas hoy -->
                    <h6 class="mb-3">Registradas hoy:</h6>
                    <?php if ($horasHoyPorProyecto && count($horasHoyPorProyecto) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Proyecto</th>
                                        <th>Horario</th>
                                        <th>Duración</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($horasHoyPorProyecto as $registro): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($registro['proyecto']); ?></td>
                                        <td><?php echo substr($registro['hora_inicio'], 0, 5) . ' - ' . substr($registro['hora_fin'], 0, 5); ?></td>
                                        <td><strong><?php echo formatDuration($registro['duracion_minutos']); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No has registrado horas por proyecto hoy.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de fichajes de hoy -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history me-2"></i>Historial de Fichajes de Hoy
                </div>
                <div class="card-body">
                    <?php if ($fichajesHoy && count($fichajesHoy) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Hora</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fichajesHoy as $fichaje): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $fichaje['tipo_registro'] === 'entrada' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($fichaje['tipo_registro']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('H:i:s', strtotime($fichaje['fecha_hora'])); ?></td>
                                        <td class="text-muted"><?php echo $fichaje['ip_origen'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0">No hay registros de fichaje para hoy.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>