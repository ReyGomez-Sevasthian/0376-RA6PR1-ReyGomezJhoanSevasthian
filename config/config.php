<?php
/**
 * Configuración General de TimeTrack Pro
 * 
 * Este archivo contiene las configuraciones globales de la aplicación
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona horaria
date_default_timezone_set('Europe/Madrid');

// Configuración de la aplicación
define('APP_NAME', 'TimeTrack Pro');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/0376-RA6PR1-ReyGomezJhoanSevasthian');

// Configuración de seguridad
define('SESSION_LIFETIME', 3600); // 1 hora en segundos
define('MIN_PASSWORD_LENGTH', 6);

// Configuración de jornada laboral
define('HORAS_JORNADA_DIARIA', 8);
define('HORA_ENTRADA_NORMAL', '09:00:00');
define('HORA_SALIDA_NORMAL', '18:00:00');

// Incluir configuración de base de datos
require_once __DIR__ . '/database.php';

/**
 * Función para verificar si el usuario está autenticado
 * 
 * @return bool True si está autenticado
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Función para redirigir al login si no está autenticado
 */
function requireAuth() {
    if (!isAuthenticated()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: pages/login.php');
        exit;
    }
}

/**
 * Función para verificar si el usuario tiene un rol específico
 * 
 * @param string|array $roles Rol o array de roles permitidos
 * @return bool True si tiene el rol requerido
 */
function hasRole($roles) {
    if (!isAuthenticated()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'];
    
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    
    return $userRole === $roles;
}

/**
 * Función para redirigir si no tiene el rol requerido
 * 
 * @param string|array $roles Rol o array de roles permitidos
 */
function requireRole($roles) {
    if (!hasRole($roles)) {
        header('Location: pages/dashboard.php');
        exit;
    }
}

/**
 * Función para sanitizar entradas de usuario
 * 
 * @param string $data Dato a sanitizar
 * @return string Dato sanitizado
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Función para validar email
 * 
 * @param string $email Email a validar
 * @return bool True si es válido
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Función para mostrar mensajes flash
 * 
 * @param string $type Tipo de mensaje (success, error, warning, info)
 * @param string $message Mensaje a mostrar
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Función para obtener y limpiar mensaje flash
 * 
 * @return array|null Mensaje flash o null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Función para obtener el nombre del rol
 * 
 * @param int $roleId ID del rol
 * @return string Nombre del rol
 */
function getRoleName($roleId) {
    $roles = [
        1 => 'admin',
        2 => 'manager',
        3 => 'empleado'
    ];
    
    return $roles[$roleId] ?? 'desconocido';
}

/**
 * Función para formatear duración en minutos a formato legible
 * 
 * @param int $minutes Minutos
 * @return string Duración formateada
 */
function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return sprintf('%dh %dm', $hours, $mins);
    }
    
    return sprintf('%dm', $mins);
}

/**
 * Función para calcular diferencia en minutos entre dos horas
 * 
 * @param string $startTime Hora de inicio (formato H:i:s)
 * @param string $endTime Hora de fin (formato H:i:s)
 * @return int Diferencia en minutos
 */
function calculateMinutesDifference($startTime, $endTime) {
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    
    $diff = $start->diff($end);
    return ($diff->h * 60) + $diff->i;
}