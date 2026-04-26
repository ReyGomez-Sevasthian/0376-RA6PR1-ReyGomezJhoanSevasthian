<?php
require_once __DIR__ . '/config/database.php';
$pdo = getDBConnection();
if ($pdo) {
    echo "Conexión OK";
    $result = $pdo->query("SELECT email, password FROM usuarios WHERE email = 'admin@timetrack.pro'")->fetch();
    echo "<br>Email: " . $result['email'];
    echo "<br>Hash: " . $result['password'];
} else {
    echo "Error de conexión";
}