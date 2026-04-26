<?php
/**
 * Página Principal - TimeTrack Pro
 * 
 * Redirige al usuario según su estado de autenticación
 */

// Incluir configuración
require_once __DIR__ . '/config/config.php';

// Redirigir según estado de autenticación
if (isAuthenticated()) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit;