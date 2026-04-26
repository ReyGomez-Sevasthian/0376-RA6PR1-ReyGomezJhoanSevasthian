<?php
/**
 * Header - TimeTrack Pro
 * 
 * Cabecera común para todas las páginas
 */

// Verificar autenticación
requireAuth();

// Obtener mensaje flash si existe
$flashMessage = getFlashMessage();

// Obtener información del usuario
$userName = $_SESSION['user_name'] ?? 'Usuario';
$userRole = $_SESSION['user_role'] ?? 'empleado';
$userId = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --dark-bg: #1a1a2e;
            --dark-sidebar: #16213e;
            --light-bg: #f8f9fa;
        }
        
        body {
            background-color: var(--light-bg);
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(180deg, var(--dark-sidebar) 0%, var(--dark-bg) 100%);
            padding: 0;
            z-index: 1000;
            transition: all 0.3s;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            color: white;
            font-size: 1.25rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-brand i {
            color: var(--primary-color);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar-nav .nav-item {
            margin: 0.25rem 0.5rem;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
        }
        
        .sidebar-nav .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-nav .nav-link.active {
            color: white;
            background: var(--primary-color);
        }
        
        .sidebar-nav .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background 0.2s;
        }
        
        .user-dropdown:hover {
            background: var(--light-bg);
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 1rem 1.25rem;
            font-weight: 600;
        }
        
        .stat-card {
            display: flex;
            align-items: center;
            padding: 1.5rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .stat-icon.primary {
            background: rgba(13, 110, 253, 0.1);
            color: var(--primary-color);
        }
        
        .stat-icon.success {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success-color);
        }
        
        .stat-icon.warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .stat-icon.danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: bold;
            color: #212529;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        /* Tables */
        .table-custom {
            margin: 0;
        }
        
        .table-custom th {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
            border: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Alert badges */
        .badge-alert {
            background: var(--danger-color);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-clock"></i>
            <span><?php echo APP_NAME; ?></span>
        </div>
        
        <ul class="sidebar-nav nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" 
                   href="usuarios.php">
                    <i class="fas fa-users"></i>
                    <span>Usuarios</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'proyectos.php' ? 'active' : ''; ?>" 
                   href="proyectos.php">
                    <i class="fas fa-project-diagram"></i>
                    <span>Proyectos</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alertas.php' ? 'active' : ''; ?>" 
                   href="alertas.php">
                    <i class="fas fa-bell"></i>
                    <span>Alertas</span>
                    <?php
                    // Contar alertas no leídas
                    $alertasCount = executeQuery("SELECT COUNT(*) as count FROM alertas WHERE leida = 0");
                    if ($alertasCount && $alertasCount[0]['count'] > 0):
                    ?>
                        <span class="badge badge-alert ms-auto"><?php echo $alertasCount[0]['count']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($userRole === 'empleado' || $userRole === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'fichar.php' ? 'active' : ''; ?>" 
                   href="fichar.php">
                    <i class="fas fa-clock"></i>
                    <span>Fichar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'mis-horas.php' ? 'active' : ''; ?>" 
                   href="mis-horas.php">
                    <i class="fas fa-history"></i>
                    <span>Mis Horas</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($userRole === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>" 
                   href="reportes.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button class="btn btn-link d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="d-flex align-items-center">
                <span class="me-3 text-muted">
                    <?php echo date('d/m/Y H:i'); ?>
                </span>
                
                <div class="dropdown">
                    <div class="user-dropdown" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($userName, 0, 1)); ?>
                        </div>
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="text-muted small"><?php echo ucfirst($userRole); ?></div>
                        </div>
                        <i class="fas fa-chevron-down ms-2 text-muted"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="p-4">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
                    <?php if ($flashMessage['type'] === 'success'): ?>
                        <i class="fas fa-check-circle me-2"></i>
                    <?php elseif ($flashMessage['type'] === 'error'): ?>
                        <i class="fas fa-exclamation-circle me-2"></i>
                    <?php elseif ($flashMessage['type'] === 'warning'): ?>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php else: ?>
                        <i class="fas fa-info-circle me-2"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($flashMessage['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($pageContent)) echo $pageContent; ?>