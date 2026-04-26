-- ============================================
-- TimeTrack Pro - Script de Base de Datos
-- ============================================
-- Este script crea todas las tablas necesarias
-- para el sistema de tracking de horas
-- ============================================

-- Crear base de datos (descomentar si es necesario)
-- CREATE DATABASE IF NOT EXISTS timetrack_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE timetrack_pro;

-- ============================================
-- TABLA: roles
-- ============================================
-- Almacena los roles del sistema
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: usuarios
-- ============================================
-- Almacena la información de los usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: proyectos
-- ============================================
-- Almacena los proyectos de la empresa
CREATE TABLE IF NOT EXISTS proyectos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    horas_presupuestadas DECIMAL(10,2) DEFAULT 0,
    cliente VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: equipo_proyecto
-- ============================================
-- Relación muchos-a-muchos entre usuarios y proyectos
CREATE TABLE IF NOT EXISTS equipo_proyecto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    rol_en_proyecto VARCHAR(50) DEFAULT 'miembro',
    asignado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_proyecto (usuario_id, proyecto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: registros_fichaje
-- ============================================
-- Almacena los registros de entrada y salida
CREATE TABLE IF NOT EXISTS registros_fichaje (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    tipo_registro ENUM('entrada', 'salida') NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_origen VARCHAR(45),
    user_agent TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_fecha (usuario_id, fecha_hora),
    INDEX idx_fecha (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: registros_horas_proyecto
-- ============================================
-- Almacena las horas trabajadas por proyecto
CREATE TABLE IF NOT EXISTS registros_horas_proyecto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion_minutos INT NOT NULL,
    descripcion TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    INDEX idx_usuario_fecha (usuario_id, fecha),
    INDEX idx_proyecto_fecha (proyecto_id, fecha),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: alertas
-- ============================================
-- Almacena las alertas generadas por incumplimientos
CREATE TABLE IF NOT EXISTS alertas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    tipo_alerta ENUM('horas_insuficientes', 'llegada_tarde', 'salida_temprana', 'no_fichado') NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATE NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_fecha (usuario_id, fecha),
    INDEX idx_leida (leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERCIÓN DE DATOS DE PRUEBA
-- ============================================

-- Insertar roles
INSERT INTO roles (nombre, descripcion) VALUES
('admin', 'Administrador del sistema - Acceso completo a todas las funcionalidades'),
('manager', 'Gerente - Gestiona su equipo y proyectos asignados'),
('empleado', 'Empleado - Registra sus horas y fichajes');

-- Insertar usuarios de prueba
-- Contraseñas:
--   Admin: admin123
--   Managers: manager123
--   Empleados: empleado123
INSERT INTO usuarios (nombre, apellidos, email, password, rol_id, activo) VALUES
('Administrador', 'Sistema', 'admin@timetrack.pro', '$2y$10$YQSq2Sy7LWWN7lqQpAcoXe9NauWITEgNtb83okepuq1JOUoCWicEy', 1, TRUE),
('María', 'González Ruiz', 'maria.gonzalez@timetrack.pro', '$2y$10$RRYBaO5m8SUz9F2ZSoRv5e6KhKsLcUDxQ6kZI6ocQ1FI4L2tDlKeG', 2, TRUE),
('Carlos', 'López Martín', 'carlos.lopez@timetrack.pro', '$2y$10$RRYBaO5m8SUz9F2ZSoRv5e6KhKsLcUDxQ6kZI6ocQ1FI4L2tDlKeG', 2, TRUE),
('Ana', 'Sánchez García', 'ana.sanchez@timetrack.pro', '$2y$10$PpbfwCoiNlLKDT7mxtI3b.jSHuh918uEh4Iy9TjgrdllVcLrny0HG', 3, TRUE),
('Luis', 'Rodríguez López', 'luis.rodriguez@timetrack.pro', '$2y$10$PpbfwCoiNlLKDT7mxtI3b.jSHuh918uEh4Iy9TjgrdllVcLrny0HG', 3, TRUE),
('Elena', 'Martínez Pérez', 'elena.martinez@timetrack.pro', '$2y$10$PpbfwCoiNlLKDT7mxtI3b.jSHuh918uEh4Iy9TjgrdllVcLrny0HG', 3, TRUE),
('Pedro', 'Hernández Díaz', 'pedro.hernandez@timetrack.pro', '$2y$10$PpbfwCoiNlLKDT7mxtI3b.jSHuh918uEh4Iy9TjgrdllVcLrny0HG', 3, TRUE),
('Laura', 'Muñoz Romero', 'laura.munoz@timetrack.pro', '$2y$10$PpbfwCoiNlLKDT7mxtI3b.jSHuh918uEh4Iy9TjgrdllVcLrny0HG', 3, TRUE);

-- Insertar proyectos de prueba
INSERT INTO proyectos (nombre, descripcion, horas_presupuestadas, cliente, activo) VALUES
('Desarrollo Web E-commerce', 'Desarrollo de tienda online con carrito de compras y pasarela de pago', 160.00, 'Cliente A', TRUE),
('App Móvil Corporativa', 'Aplicación móvil para gestión interna de empleados', 120.00, 'Cliente B', TRUE),
('Migración Base de Datos', 'Migración de sistema legacy a nueva infraestructura cloud', 80.00, 'Cliente C', TRUE);

-- Asignar empleados a proyectos
INSERT INTO equipo_proyecto (usuario_id, proyecto_id, rol_en_proyecto) VALUES
-- Manager 1 (María) en proyecto 1 y 2
(2, 1, 'manager'),
(2, 2, 'manager'),
-- Manager 2 (Carlos) en proyecto 3
(3, 3, 'manager'),
-- Empleados en proyecto 1
(4, 1, 'desarrollador'),
(5, 1, 'diseñador'),
-- Empleados en proyecto 2
(6, 2, 'desarrollador'),
(7, 2, 'tester'),
-- Empleados en proyecto 3
(8, 3, 'desarrollador'),
(5, 3, 'consultor');

-- ============================================
-- VISTAS ÚTILES
-- ============================================

-- Vista para ver registros de fichaje con información de usuario
CREATE OR REPLACE VIEW vista_fichajes AS
SELECT 
    rf.id,
    rf.usuario_id,
    u.nombre,
    u.apellidos,
    u.email,
    r.nombre as rol,
    rf.tipo_registro,
    rf.fecha_hora,
    rf.ip_origen,
    rf.creado_en
FROM registros_fichaje rf
JOIN usuarios u ON rf.usuario_id = u.id
JOIN roles r ON u.rol_id = r.id
ORDER BY rf.fecha_hora DESC;

-- Vista para ver horas por proyecto
CREATE OR REPLACE VIEW vista_horas_proyecto AS
SELECT 
    rhp.id,
    rhp.usuario_id,
    u.nombre,
    u.apellidos,
    rhp.proyecto_id,
    p.nombre as proyecto_nombre,
    rhp.fecha,
    rhp.hora_inicio,
    rhp.hora_fin,
    rhp.duracion_minutos,
    rhp.descripcion
FROM registros_horas_proyecto rhp
JOIN usuarios u ON rhp.usuario_id = u.id
JOIN proyectos p ON rhp.proyecto_id = p.id
ORDER BY rhp.fecha DESC, rhp.hora_inicio DESC;

-- ============================================
-- FIN DEL SCRIPT
-- ============================================