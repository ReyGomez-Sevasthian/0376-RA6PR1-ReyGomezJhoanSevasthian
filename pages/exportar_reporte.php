<?php
/**
 * Exportar Reporte - TimeTrack Pro
 * 
 * Exporta reporte de horas a CSV/PDF
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Verificar autenticación
requireAuth();

// Solo admin puede exportar
if (!hasRole('admin')) {
    setFlashMessage('danger', 'No tienes permiso para exportar reportes.');
    header('Location: reportes.php');
    exit;
}

// Obtener filtros
$filtroPeriodo = isset($_GET['periodo']) ? sanitizeInput($_GET['periodo']) : 'mes';
$filtroProyecto = isset($_GET['proyecto']) ? filter_var($_GET['proyecto'], FILTER_VALIDATE_INT) : null;
$formato = isset($_GET['formato']) ? sanitizeInput($_GET['formato']) : 'csv';

// Calcular fechas
$fechaInicio = date('Y-m-01');
$fechaFin = date('Y-m-d');

if ($filtroPeriodo === 'semana') {
    $fechaInicio = date('Y-m-d', strtotime('monday this week'));
} elseif ($filtroPeriodo === 'trimestre') {
    $fechaInicio = date('Y-m-01', strtotime('-3 months'));
} elseif ($filtroPeriodo === 'anio') {
    $fechaInicio = date('Y-01-01');
} elseif ($filtroPeriodo === 'todo') {
    $fechaInicio = '2020-01-01';
}

// Obtener datos
$horasPorProyecto = executeQuery("
    SELECT 
        p.nombre as proyecto,
        p.horas_presupuestadas,
        COALESCE(SUM(rhp.duracion_minutos), 0) as horas_reales_minutos,
        COUNT(DISTINCT rhp.usuario_id) as empleados_asignados
    FROM proyectos p
    LEFT JOIN registros_horas_proyecto rhp ON p.id = rhp.proyecto_id
        AND rhp.fecha BETWEEN ? AND ?
        " . ($filtroProyecto ? "AND p.id = $filtroProyecto" : "") . "
    WHERE p.activo = 1
    GROUP BY p.id, p.nombre, p.horas_presupuestadas
    ORDER BY horas_reales_minutos DESC
", [$fechaInicio, $fechaFin]);

// Total general
$totalHoras = 0;
if ($horasPorProyecto) {
    foreach ($horasPorProyecto as $p) {
        $totalHoras += $p['horas_reales_minutos'];
    }
}

// Nombre del archivo
$nombreArchivo = 'reporte_horas_' . date('Y-m-d');

// ============================================
// EXPORTAR A CSV
// ============================================
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezado
    fputcsv($output, [
        'TimeTrack Pro - Reporte de Horas',
        'Período: ' . $fechaInicio . ' al ' . $fechaFin,
        'Fecha de generación: ' . date('d/m/Y H:i:s')
    ]);
    fputcsv($output, []);
    fputcsv($output, ['Proyecto', 'Horas Presupuestadas', 'Horas Reales', 'Diferencia', '% Uso', 'Empleados Asignados']);
    
    // Datos
    if ($horasPorProyecto) {
        foreach ($horasPorProyecto as $proyecto) {
            $horasReales = round($proyecto['horas_reales_minutos'] / 60, 2);
            $horasPresupuestadas = floatval($proyecto['horas_presupuestadas']);
            $diferencia = $horasReales - $horasPresupuestadas;
            $porcentaje = $horasPresupuestadas > 0 ? round(($horasReales / $horasPresupuestadas) * 100, 1) : 0;
            
            fputcsv($output, [
                $proyecto['proyecto'],
                $horasPresupuestadas,
                $horasReales,
                round($diferencia, 2),
                $porcentaje . '%',
                $proyecto['empleados_asignados']
            ]);
        }
    }
    
    // Total
    fputcsv($output, []);
    fputcsv($output, ['TOTAL', '', round($totalHoras / 60, 2), '', '', '']);
    
    fclose($output);
    exit;
}

// ============================================
// EXPORTAR A HTML (para imprimir/PDF)
// ============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Horas - <?php echo APP_NAME; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0d6efd;
            margin: 0;
        }
        .info {
            margin: 20px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #0d6efd;
            color: white;
            text-align: center;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .total {
            font-weight: bold;
            background: #e9ecef;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
        }
        .btn-print {
            display: inline-block;
            padding: 10px 20px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 5px;
        }
        @media print {
            .btn-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo APP_NAME; ?> - Reporte de Horas</h1>
    </div>
    
    <div class="info">
        <strong>Período:</strong> <?php echo $fechaInicio; ?> al <?php echo $fechaFin; ?><br>
        <strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Proyecto</th>
                <th>Horas Presupuestadas</th>
                <th>Horas Reales</th>
                <th>Diferencia</th>
                <th>% Uso</th>
                <th>Empleados</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($horasPorProyecto): ?>
                <?php foreach ($horasPorProyecto as $proyecto): ?>
                    <?php
                    $horasReales = round($proyecto['horas_reales_minutos'] / 60, 2);
                    $horasPresupuestadas = floatval($proyecto['horas_presupuestadas']);
                    $diferencia = $horasReales - $horasPresupuestadas;
                    $porcentaje = $horasPresupuestadas > 0 ? round(($horasReales / $horasPresupuestadas) * 100, 1) : 0;
                    ?>
                <tr>
                    <td><?php echo htmlspecialchars($proyecto['proyecto']); ?></td>
                    <td style="text-align: center;"><?php echo number_format($horasPresupuestadas, 1); ?></td>
                    <td style="text-align: center;"><?php echo number_format($horasReales, 1); ?></td>
                    <td style="text-align: center; color: <?php echo $diferencia > 0 ? 'red' : 'green'; ?>">
                        <?php echo ($diferencia > 0 ? '+' : '') . number_format($diferencia, 2); ?>
                    </td>
                    <td style="text-align: center;"><?php echo $porcentaje; ?>%</td>
                    <td style="text-align: center;"><?php echo $proyecto['empleados_asignados']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No hay datos disponibles</td>
                </tr>
            <?php endif; ?>
            <tr class="total">
                <td>TOTAL</td>
                <td></td>
                <td style="text-align: center;"><?php echo number_format(round($totalHoras / 60, 2), 2); ?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Reporte generado automáticamente por <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></p>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
        <a href="exportar_reporte.php?periodo=<?php echo $filtroPeriodo; ?>&proyecto=<?php echo $filtroProyecto; ?>&formato=csv" 
           class="btn-print" style="text-decoration: none;">📥 Descargar CSV</a>
        <a href="reportes.php" class="btn-print" style="background: #6c757d; text-decoration: none;">← Volver</a>
    </div>
</body>
</html>