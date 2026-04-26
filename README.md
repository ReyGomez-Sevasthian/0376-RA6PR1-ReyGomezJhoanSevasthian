# TimeTrack Pro - Sistema de Control de Horas Laborales

![Versión](https://img.shields.io/badge/versión-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-blue)

## 📋 Descripción

**TimeTrack Pro** es un sistema web completo para el control de horas laborales por proyecto, diseñado para empresas de hasta 400 empleados. Permite el registro de entrada/salida, asignación de horas por proyecto, generación de alertas por incumplimiento y reportes detallados.

## ✨ Características

### 🔐 Sistema de Autenticación
- Login seguro con `password_hash()` y `password_verify()`
- 3 niveles de usuario: Admin, Manager, Empleado
- Sesiones protegidas con regeneración de ID
- Protección contra SQL Injection con PDO Prepared Statements

### ⏰ Control de Asistencia
- Fichaje de entrada/salida con un clic
- Registro de IP y user agent
- Historial de fichajes por día

### 📊 Gestión de Horas por Proyecto
- Asignación de horas a proyectos específicos
- Cálculo automático de duración
- Vista de historial con filtros por fecha y proyecto

### 🚨 Sistema de Alertas
- **Lista Roja**: Empleados con múltiples incumplimientos
- Detección automática de:
  - Horas insuficientes (< 8h diarias)
  - Llegadas tarde (> 9:00 AM)
  - Salidas tempranas (< 6:00 PM)
  - No fichado del día

### 📈 Reportes y Gráficos
- Gráficos de barras y dona con Chart.js
- Comparativa horas presupuestadas vs reales
- Exportación a CSV y HTML imprimible
- Evolución diaria de horas trabajadas

### 📱 Diseño Responsive
- Interfaz adaptable a móvil y escritorio
- Menú lateral colapsable en móvil
- Bootstrap 5 con diseño profesional oscuro

## 🏗️ Estructura del Proyecto

```
timetrack-pro/
├── assets/
│   ├── css/          # Estilos personalizados (opcional)
│   ├── js/           # Scripts personalizados (opcional)
│   └── images/       # Imágenes y logos
├── config/
│   ├── config.php    # Configuración general
│   └── database.php  # Conexión PDO a MySQL
├── database/
│   └── timetrack_pro.sql  # Script de base de datos
├── includes/
│   ├── header.php    # Cabecera común
│   └── footer.php    # Pie de página común
├── pages/
│   ├── login.php           # Página de login
│   ├── logout.php          # Cierre de sesión
│   ├── dashboard.php       # Panel principal
│   ├── fichar.php          # Fichaje de entrada/salida
│   ├── mis-horas.php       # Historial del empleado
│   ├── alertas.php         # Panel de alertas (Admin/Manager)
│   ├── usuarios.php        # Gestión de usuarios (Admin)
│   ├── proyectos.php       # Gestión de proyectos
│   ├── reportes.php        # Reportes y gráficos (Admin)
│   └── exportar_reporte.php # Exportación CSV/HTML
├── index.php         # Redireccionamiento inicial
├── .htaccess         # Configuración Apache
└── README.md         # Este archivo
```

## 🚀 Instalación

### Requisitos Previos
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache con mod_rewrite habilitado
- Extensiones PHP: PDO, PDO_MySQL

### Pasos de Instalación

1. **Clonar o descargar el proyecto**
   ```bash
   git clone https://github.com/tu-usuario/timetrack-pro.git
   cd timetrack-pro
   ```

2. **Configurar la base de datos**
   
   a. Crear una base de datos en MySQL:
   ```sql
   CREATE DATABASE timetrack_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   
   b. Importar el script SQL:
   ```bash
   mysql -u root -p timetrack_pro < database/timetrack_pro.sql
   ```
   
   O usar phpMyAdmin para importar `database/timetrack_pro.sql`

3. **Configurar la conexión a la base de datos**
   
   Editar `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'timetrack_pro');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contraseña');
   define('DB_CHARSET', 'utf8mb4');
   ```

4. **Configurar la URL de la aplicación**
   
   Editar `config/config.php`:
   ```php
   define('APP_URL', 'http://localhost/timetrack-pro');
   ```

5. **Configurar Apache**
   
   Asegurarse de que `.htaccess` esté presente y que `mod_rewrite` esté habilitado:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

6. **Acceder a la aplicación**
   
   Abrir el navegador en `http://localhost/timetrack-pro`

## 👥 Usuarios de Prueba

| Rol | Email | Contraseña |
|-----|-------|------------|
| Admin | admin@timetrack.pro | admin123 |
| Manager | maria.gonzalez@timetrack.pro | manager123 |
| Manager | carlos.lopez@timetrack.pro | manager123 |
| Empleado | ana.sanchez@timetrack.pro | empleado123 |
| Empleado | luis.rodriguez@timetrack.pro | empleado123 |
| Empleado | elena.martinez@timetrack.pro | empleado123 |
| Empleado | pedro.hernandez@timetrack.pro | empleado123 |
| Empleado | laura.munoz@timetrack.pro | empleado123 |

## 🔧 Configuración Adicional

### Zona Horaria
Editar en `config/config.php`:
```php
date_default_timezone_set('Europe/Madrid'); // Cambiar según tu país
```

### Jornada Laboral
Editar en `config/config.php`:
```php
define('HORAS_JORNADA_DIARIA', 8);
define('HORA_ENTRADA_NORMAL', '09:00:00');
define('HORA_SALIDA_NORMAL', '18:00:00');
```

## 🛡️ Seguridad

- **PDO con Prepared Statements**: Todas las consultas usan prepared statements para prevenir SQL Injection
- **Password Hashing**: Contraseñas hasheadas con `password_hash()` de PHP
- **XSS Protection**: Todas las salidas usan `htmlspecialchars()`
- **CSRF Protection**: Se recomienda implementar tokens CSRF en formularios
- **Session Security**: Regeneración de ID de sesión en login
- **Input Validation**: Validación y sanitización de todas las entradas

## 📱 Uso por Rol

### 👤 Empleado
- Fichar entrada/salida
- Registrar horas por proyecto
- Ver su historial de horas
- Ver sus proyectos asignados

### 👨‍💼 Manager
- Ver dashboard de su equipo
- Ver alertas de su equipo
- Ver proyectos asignados
- Ver últimos fichajes de su equipo

### 👑 Admin
- Acceso completo a todas las funciones
- Gestionar usuarios (crear, activar/desactivar)
- Gestionar proyectos
- Ver todas las alertas
- Generar y exportar reportes
- Ver lista roja de empleados

## 🔍 Pruebas de Funcionamiento

### Iteración 1 - Autenticación
1. Acceder a `http://localhost/timetrack-pro`
2. Iniciar sesión con admin@timetrack.pro / admin123
3. Verificar redirección al dashboard
4. Cerrar sesión y verificar redirección al login

### Iteración 2 - Fichaje
1. Iniciar sesión como empleado
2. Ir a "Fichar" y registrar entrada
3. Registrar horas en un proyecto
4. Verificar que aparece en el historial

### Iteración 3 - Alertas
1. Iniciar sesión como admin
2. Ir a "Alertas" y verificar la generación automática
3. Marcar alertas como leídas
4. Verificar lista roja

### Iteración 4 - Reportes
1. Iniciar sesión como admin
2. Ir a "Reportes"
3. Ver gráficos de Chart.js
4. Probar exportación a CSV

### Iteración 5 - Responsive
1. Abrir la aplicación en móvil
2. Verificar menú colapsable
3. Probar fichaje desde móvil

## 📄 Licencia

Este proyecto es de código abierto para fines educativos.

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor, seguir las buenas prácticas de PHP y mantener la estructura del proyecto.

## 📞 Soporte

Para problemas o consultas, crear un issue en el repositorio.

---

**TimeTrack Pro v1.0.0** - Sistema de Control de Horas Laborales