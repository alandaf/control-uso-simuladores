<?php
// Disable displaying PHP errors to the client to avoid information disclosure
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// Secure Session Configuration
$cookie_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $cookie_secure,
    'cookie_samesite' => 'Strict',
    'use_only_cookies' => true
]);

// Set Chilean Timezone for consistent calculations
date_default_timezone_set('America/Santiago');

header('Content-Type: application/json');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $http_origin = $_SERVER['HTTP_ORIGIN'];
    $allowed_origins = [
        'http://localhost',
        'http://localhost:8080',
        'https://ippilotopardo.cl',
        'https://www.ippilotopardo.cl'
    ];
    if (in_array($http_origin, $allowed_origins, true)) {
        header("Access-Control-Allow-Origin: $http_origin");
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configurable Admin Credentials (overridable via .env)
define('ADMIN_USER', $_ENV['ADMIN_USER'] ?? 'admin');
// BCRYPT hash of the password (default hash is for 'admin123')
define('ADMIN_PASSWORD_HASH', $_ENV['ADMIN_PASSWORD_HASH'] ?? '$2y$10$MAn6J13mTDw8f3kU9N9Tyu.e0JPDc3OpMRM0fGN14jFzeUL9/vS8S');

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper to check if user is logged in and CSRF token is valid
function checkAuth() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendResponse(['success' => false, 'error' => 'No autorizado. Inicie sesión.'], 401);
    }
    
    // Validate CSRF token
    $headers = getallheaders();
    // Support case-insensitive headers (X-Csrf-Token vs X-CSRF-Token)
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        sendResponse(['success' => false, 'error' => 'Petición no válida (CSRF inválido).'], 403);
    }
}

// Whitelist validation for dynamic database table names to prevent SQL Injection
function validateTableName($table) {
    $allowed = ['uso_simulador', 'asistencia_voluntaria', 'entrenamiento_externo', 'cursos', 'asignaturas'];
    if (!in_array($table, $allowed)) {
        sendResponse(['success' => false, 'error' => 'Acción no permitida (Tabla no válida).'], 400);
    }
}

// Helper to fetch and cache Chilean holidays for a specific year
function fetchAndCacheHolidays($year) {
    global $db;
    
    // Check if we already have holidays for this year in the database
    $stmt = $db->prepare("SELECT COUNT(*) FROM feriados WHERE DATE_FORMAT(fecha, '%Y') = ?");
    $stmt->execute([$year]);
    $count = intval($stmt->fetchColumn());
    
    if ($count > 0) {
        return; // Already cached
    }
    
    // Attempt to fetch from official government API
    $url = "https://apis.digital.gob.cl/fl/feriados/" . $year;
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 3, // 3s timeout
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    if ($response) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            $stmt = $db->prepare("INSERT IGNORE INTO feriados (fecha, nombre) VALUES (?, ?)");
            foreach ($data as $item) {
                if (isset($item['fecha']) && isset($item['nombre'])) {
                    $stmt->execute([$item['fecha'], $item['nombre']]);
                }
            }
            return;
        }
    }
    
    // Fallback: Populate baseline if API fails
    $baseline = [
        '2024' => [
            '2024-01-01' => 'Año Nuevo', '2024-03-29' => 'Viernes Santo', '2024-03-30' => 'Sábado Santo',
            '2024-05-01' => 'Día del Trabajo', '2024-05-21' => 'Glorias Navales', '2024-06-09' => 'Elecciones Primarias',
            '2024-06-20' => 'Pueblos Indígenas', '2024-06-29' => 'San Pedro y San Pablo', '2024-07-16' => 'Virgen del Carmen',
            '2024-08-15' => 'Asunción de la Virgen', '2024-09-18' => 'Fiestas Patrias', '2024-09-19' => 'Glorias del Ejército',
            '2024-09-20' => 'Fiestas Patrias', '2024-10-12' => 'Encuentro Dos Mundos', '2024-10-27' => 'Elecciones Municipales',
            '2024-10-31' => 'Iglesias Evangélicas', '2024-11-01' => 'Todos los Santos', '2024-12-08' => 'Inmaculada Concepción',
            '2024-12-25' => 'Navidad'
        ],
        '2025' => [
            '2025-01-01' => 'Año Nuevo', '2025-04-18' => 'Viernes Santo', '2025-04-19' => 'Sábado Santo',
            '2025-05-01' => 'Día del Trabajo', '2025-05-21' => 'Glorias Navales', '2025-06-20' => 'Pueblos Indígenas',
            '2025-06-29' => 'San Pedro y San Pablo', '2025-07-16' => 'Virgen del Carmen', '2025-08-15' => 'Asunción de la Virgen',
            '2025-09-18' => 'Fiestas Patrias', '2025-09-19' => 'Glorias del Ejército', '2025-10-12' => 'Encuentro Dos Mundos',
            '2025-10-31' => 'Iglesias Evangélicas', '2025-11-01' => 'Todos los Santos', '2025-11-23' => 'Segunda Vuelta Presidencial',
            '2025-12-08' => 'Inmaculada Concepción', '2025-12-25' => 'Navidad'
        ],
        '2026' => [
            '2026-01-01' => 'Año Nuevo', '2026-04-03' => 'Viernes Santo', '2026-04-04' => 'Sábado Santo',
            '2026-05-01' => 'Día del Trabajo', '2026-05-21' => 'Glorias Navales', '2026-06-21' => 'Pueblos Indígenas',
            '2026-06-29' => 'San Pedro y San Pablo', '2026-07-16' => 'Virgen del Carmen', '2026-08-15' => 'Asunción de la Virgen',
            '2026-09-18' => 'Fiestas Patrias', '2026-09-19' => 'Glorias del Ejército', '2026-10-12' => 'Encuentro Dos Mundos',
            '2026-10-31' => 'Iglesias Evangélicas', '2026-11-01' => 'Todos los Santos', '2026-12-08' => 'Inmaculada Concepción',
            '2026-12-25' => 'Navidad'
        ]
    ];
    
    if (isset($baseline[strval($year)])) {
        $stmt = $db->prepare("INSERT IGNORE INTO feriados (fecha, nombre) VALUES (?, ?)");
        foreach ($baseline[strval($year)] as $date => $name) {
            $stmt->execute([$date, $name]);
        }
    }
}

// Load environment variables from .env file if it exists (for production credentials security)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Strip inline comments if they exist and are not inside quotes
            if (strpos($value, '#') !== false) {
                if (!preg_match('/^([\'"]).*\1$/', $value)) {
                    $val_parts = explode('#', $value, 2);
                    $value = trim($val_parts[0]);
                }
            }
            // Strip outer quotes if present
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            $_ENV[$key] = $value;
        }
    }
}

// Database Connection Configuration (MariaDB / MySQL)
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'ippilotopardo_control_simulador';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';

$db = null;
try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("SET NAMES utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS configuracion_anual (
        year INT PRIMARY KEY,
        fecha_inicio VARCHAR(10) NOT NULL,
        fecha_fin VARCHAR(10) NOT NULL,
        horas_diarias DECIMAL(10,2) NOT NULL,
        descuento_fines_semana INT DEFAULT 0,
        descuento_feriados INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
     // Auto-migration columns check
     try {
         $db->exec("ALTER TABLE configuracion_anual ADD COLUMN descuento_fines_semana INT DEFAULT 0");
     } catch (PDOException $e) {}
     try {
         $db->exec("ALTER TABLE configuracion_anual ADD COLUMN descuento_feriados INT DEFAULT 0");
     } catch (PDOException $e) {}

     $db->exec("CREATE TABLE IF NOT EXISTS feriados (
        fecha DATE PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create administrador table to store credentials securely
    $db->exec("CREATE TABLE IF NOT EXISTS administrador (
        username VARCHAR(255) PRIMARY KEY,
        password_hash VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Seed default administrator if table is empty
    $stmt_chk = $db->query("SELECT COUNT(*) FROM administrador");
    if (intval($stmt_chk->fetchColumn()) === 0) {
        $db->prepare("INSERT INTO administrador (username, password_hash) VALUES (?, ?)")
           ->execute(['admin', '$2y$10$MAn6J13mTDw8f3kU9N9Tyu.e0JPDc3OpMRM0fGN14jFzeUL9/vS8S']);
    }
} catch (PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno de conexión a la base de datos.']);
    exit();
}

$action = $_GET['action'] ?? '';

// Set Cache-Control header depending on action to allow caching of static-like endpoints
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (in_array($action, ['get_config', 'get_feriados', 'get_areas', 'get_params'])) {
        header('Cache-Control: public, max-age=3600');
    } else {
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
}

// Helper to return JSON and exit
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Helper to log errors internally and return a secure message to the client
function sendErrorResponse($exception, $message = 'Ha ocurrido un error interno en el servidor.') {
    error_log('Database/API Error: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    sendResponse(['success' => false, 'error' => $message], 500);
}

// Helper to validate area
function validateArea($area) {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM areas WHERE id = ?");
    $stmt->execute([$area]);
    return $stmt->fetchColumn() > 0;
}

switch ($action) {
    case 'login':
        // Rate limiting: Check if account is temporarily locked out
        if (isset($_SESSION['lockout_until']) && time() < $_SESSION['lockout_until']) {
            $remaining = $_SESSION['lockout_until'] - time();
            sendResponse(['success' => false, 'error' => "Demasiados intentos fallidos. Intente de nuevo en $remaining segundos."], 429);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        
        $stmt_admin = $db->prepare("SELECT password_hash FROM administrador WHERE username = ?");
        $stmt_admin->execute([$username]);
        $db_hash = $stmt_admin->fetchColumn();
        
        $auth_success = false;
        if ($db_hash && password_verify($password, $db_hash)) {
            $auth_success = true;
        } elseif ($username === ADMIN_USER && password_verify($password, ADMIN_PASSWORD_HASH)) {
            $auth_success = true;
        }

        if ($auth_success) {
            // Success: Reset rate limit counters and regenerate session
            unset($_SESSION['login_attempts']);
            unset($_SESSION['lockout_until']);
            
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            sendResponse(['success' => true]);
        } else {
            // Failure: Increment rate limit counter
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['lockout_until'] = time() + 300; // 5 minute lock
                $_SESSION['login_attempts'] = 0;
                sendResponse(['success' => false, 'error' => 'Demasiados intentos fallidos. Cuenta bloqueada por 5 minutos.'], 429);
            }
            sendResponse(['success' => false, 'error' => 'Usuario o contraseña incorrectos']);
        }
        break;

    case 'logout':
        $_SESSION['logged_in'] = false;
        session_destroy();
        sendResponse(['success' => true]);
        break;

    case 'check_session':
        $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        sendResponse([
            'success' => true,
            'logged_in' => $isLoggedIn,
            'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);
        break;

    case 'change_admin_credentials':
        checkAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $new_username = trim($input['username'] ?? '');
        $new_password = $input['password'] ?? '';

        if ($new_username === '' || $new_password === '') {
            sendResponse(['success' => false, 'error' => 'El usuario y la contraseña no pueden estar vacíos'], 400);
        }

        if (strlen($new_password) < 6) {
            sendResponse(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres'], 400);
        }

        try {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            // Clear current admin table and insert the new admin credentials
            $db->exec("DELETE FROM administrador");
            $stmt = $db->prepare("INSERT INTO administrador (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$new_username, $new_hash]);
            
            // Log out the user to force logging in with the new credentials
            $_SESSION['logged_in'] = false;
            session_destroy();
            
            sendResponse(['success' => true]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'get_config':
        try {
            $stmt = $db->query("SELECT * FROM configuracion_anual ORDER BY year DESC");
            $configs = $stmt->fetchAll();
            sendResponse(['success' => true, 'configs' => $configs]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'save_config':
        checkAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $year = intval($input['year'] ?? 0);
        $fecha_inicio = trim($input['fecha_inicio'] ?? '03-01');
        $fecha_fin = trim($input['fecha_fin'] ?? '12-01');
        $horas_diarias = floatval($input['horas_diarias'] ?? 8.00);
        $descuento_fines_semana = intval($input['descuento_fines_semana'] ?? 0);
        $descuento_feriados = intval($input['descuento_feriados'] ?? 0);

        if ($year < 2000 || $year > 2100) {
            sendResponse(['success' => false, 'error' => 'Año no válido'], 400);
        }
        if (!preg_match('/^\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{2}-\d{2}$/', $fecha_fin)) {
            sendResponse(['success' => false, 'error' => 'Formato de fecha no válido (debe ser MM-DD)'], 400);
        }

        try {
            // ON DUPLICATE KEY UPDATE or replace into
            $stmt = $db->prepare("
                REPLACE INTO configuracion_anual (year, fecha_inicio, fecha_fin, horas_diarias, descuento_fines_semana, descuento_feriados) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$year, $fecha_inicio, $fecha_fin, $horas_diarias, $descuento_fines_semana, $descuento_feriados]);
            sendResponse(['success' => true]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'get_feriados':
        try {
            $year = intval($_GET['year'] ?? date('Y'));
            fetchAndCacheHolidays($year);
            fetchAndCacheHolidays($year - 1);
            fetchAndCacheHolidays($year + 1);
            
            $stmt = $db->query("SELECT fecha, nombre FROM feriados");
            $rows = $stmt->fetchAll();
            $feriados = [];
            foreach ($rows as $r) {
                $feriados[$r['fecha']] = $r['nombre'];
            }
            sendResponse(['success' => true, 'feriados' => $feriados]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'get_areas':
        try {
            $stmt = $db->query("SELECT * FROM areas");
            $areas = $stmt->fetchAll();
            sendResponse(['success' => true, 'areas' => $areas]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'get_params':
        $area = $_GET['area'] ?? '';
        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }
        try {
            $stmt = $db->prepare("SELECT * FROM cursos WHERE area_id = ? ORDER BY nombre ASC");
            $stmt->execute([$area]);
            $cursos = $stmt->fetchAll();

            $stmt = $db->prepare("SELECT * FROM asignaturas WHERE area_id = ? ORDER BY nombre ASC");
            $stmt->execute([$area]);
            $asignaturas = $stmt->fetchAll();

            // Fetch unique student names for autocomplete/suggestions
            $stmt = $db->prepare("SELECT DISTINCT nombre_estudiante FROM asistencia_voluntaria WHERE area_id = ? AND nombre_estudiante IS NOT NULL AND nombre_estudiante != '' ORDER BY nombre_estudiante ASC");
            $stmt->execute([$area]);
            $estudiantes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            sendResponse([
                'success' => true,
                'cursos' => $cursos,
                'asignaturas' => $asignaturas,
                'estudiantes' => $estudiantes
            ]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'create_param':
        checkAuth();
        $area = $_GET['area'] ?? '';
        $type = $_GET['type'] ?? '';
        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $nombre = trim($input['nombre'] ?? '');
        if ($nombre === '') {
            sendResponse(['success' => false, 'error' => 'El nombre no puede estar vacío'], 400);
        }

        try {
            $table = ($type === 'curso') ? 'cursos' : (($type === 'asignatura') ? 'asignaturas' : '');
            if ($table === '') {
                sendResponse(['success' => false, 'error' => 'Tipo no válido'], 400);
            }

            validateTableName($table);
            $stmt = $db->prepare("INSERT INTO $table (area_id, nombre) VALUES (?, ?)");
            $stmt->execute([$area, $nombre]);
            sendResponse(['success' => true, 'id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'delete_param':
        checkAuth();
        $area = $_GET['area'] ?? '';
        $type = $_GET['type'] ?? '';
        $id = intval($_GET['id'] ?? 0);

        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }
        if ($id <= 0) {
            sendResponse(['success' => false, 'error' => 'ID no válido'], 400);
        }

        try {
            $table = ($type === 'curso') ? 'cursos' : (($type === 'asignatura') ? 'asignaturas' : '');
            if ($table === '') {
                sendResponse(['success' => false, 'error' => 'Tipo no válido'], 400);
            }

            validateTableName($table);
            $stmt = $db->prepare("DELETE FROM $table WHERE id = ? AND area_id = ?");
            $stmt->execute([$id, $area]);
            sendResponse(['success' => true]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'get_stats':
        $area = $_GET['area'] ?? '';
        $year = intval($_GET['year'] ?? date('Y'));
        $year_str = strval($year);
        
        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }

        fetchAndCacheHolidays($year);
        $stmt_h = $db->query("SELECT fecha FROM feriados");
        $chilean_holidays = $stmt_h->fetchAll(PDO::FETCH_COLUMN);

        // Helper function for network days (workdays Monday-Friday, excluding Chilean holidays)
        $getNetworkDays = function($startDate, $endDate) use ($chilean_holidays) {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            if ($start > $end) return 0;
            
            $days = 0;
            while ($start <= $end) {
                $w = intval($start->format('w')); // 0 = Sunday, 6 = Saturday
                $dateStr = $start->format('Y-m-d');
                if ($w !== 0 && $w !== 6 && !in_array($dateStr, $chilean_holidays)) {
                    $days++;
                }
                $start->modify('+1 day');
            }
            return $days;
        };

        try {
            $stats = [];

            // 1. Horas por mes y año (Uso general) filtrado por año
            $stmt = $db->prepare("
                SELECT DATE_FORMAT(fecha, '%Y') as year, DATE_FORMAT(fecha, '%m') as month, SUM(cantidad_horas) as total_hours 
                FROM uso_simulador 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') = :year
                GROUP BY year, month 
                ORDER BY year DESC, month DESC
            ");
            $stmt->execute([':area' => $area, ':year' => $year_str]);
            $stats['uso_mensual'] = $stmt->fetchAll();

            // 2. Horas voluntarias por mes y año
            $stmt = $db->prepare("
                SELECT DATE_FORMAT(fecha, '%Y') as year, DATE_FORMAT(fecha, '%m') as month, SUM(horas) as total_hours 
                FROM asistencia_voluntaria 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') = :year
                GROUP BY year, month 
                ORDER BY year DESC, month DESC
            ");
            $stmt->execute([':area' => $area, ':year' => $year_str]);
            $stats['voluntarios_mensual'] = $stmt->fetchAll();

            // 3. Top estudiantes voluntarios
            $stmt = $db->prepare("
                SELECT nombre_estudiante, SUM(horas) as total_hours, COUNT(*) as sesiones 
                FROM asistencia_voluntaria 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') = :year
                GROUP BY nombre_estudiante 
                ORDER BY total_hours DESC 
                LIMIT 15
            ");
            $stmt->execute([':area' => $area, ':year' => $year_str]);
            $stats['top_estudiantes'] = $stmt->fetchAll();

            // 4. Horas por asignatura (Uso general)
            $stmt = $db->prepare("
                SELECT asignatura, SUM(cantidad_horas) as total_hours 
                FROM uso_simulador 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') = :year AND asignatura IS NOT NULL AND asignatura != '' 
                GROUP BY asignatura 
                ORDER BY total_hours DESC
                LIMIT 15
            ");
            $stmt->execute([':area' => $area, ':year' => $year_str]);
            $stats['horas_asignatura'] = $stmt->fetchAll();

            // 5. Horas por categoria (Uso general)
            $stmt = $db->prepare("
                SELECT categoria, SUM(cantidad_horas) as total_hours 
                FROM uso_simulador 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') = :year AND categoria IS NOT NULL AND categoria != '' 
                GROUP BY categoria 
                ORDER BY total_hours DESC
            ");
            $stmt->execute([':area' => $area, ':year' => $year_str]);
            $stats['horas_categoria'] = $stmt->fetchAll();

            // 6. Ingresos e información de entrenamiento externo
            $stmt = $db->prepare("
                SELECT SUM(monto_cancelado) as total_revenue, SUM(cantidad_horas) as total_hours, COUNT(*) as total_students 
                FROM entrenamiento_externo 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') = :year
            ");
            $stmt->execute([':area' => $area, ':year' => $year_str]);
            $stats['externo_totales'] = $stmt->fetch();

            $stmt = $db->prepare("
                SELECT DATE_FORMAT(fecha, '%Y') as year, DATE_FORMAT(fecha, '%m') as month, SUM(monto_cancelado) as total_revenue, SUM(cantidad_horas) as total_hours 
                FROM entrenamiento_externo 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') = :year
                GROUP BY year, month 
                ORDER BY year DESC, month DESC
            ");
            $stmt->execute([':area' => $area, ':year' => $year_str]);
            $stats['externo_mensual'] = $stmt->fetchAll();

            // 7. Resumen de KPIs Generales (Filtrados por año)
            $stmt = $db->prepare("SELECT SUM(cantidad_horas) FROM uso_simulador WHERE area_id = ? AND DATE_FORMAT(fecha, '%Y') = ?");
            $stmt->execute([$area, $year_str]);
            $stats['kpi_total_uso'] = floatval($stmt->fetchColumn());

            $stmt = $db->prepare("SELECT SUM(cantidad_alumnos) FROM uso_simulador WHERE area_id = ? AND DATE_FORMAT(fecha, '%Y') = ?");
            $stmt->execute([$area, $year_str]);
            $total_alumnos = intval($stmt->fetchColumn());
            $stats['kpi_alumnos_por_hora'] = $stats['kpi_total_uso'] > 0 ? round($total_alumnos / $stats['kpi_total_uso'], 2) : 0.00;

            $stmt = $db->prepare("SELECT SUM(horas) FROM asistencia_voluntaria WHERE area_id = ? AND DATE_FORMAT(fecha, '%Y') = ?");
            $stmt->execute([$area, $year_str]);
            $stats['kpi_total_voluntario'] = floatval($stmt->fetchColumn());

            $stmt = $db->prepare("SELECT SUM(monto_cancelado) FROM entrenamiento_externo WHERE area_id = ? AND DATE_FORMAT(fecha, '%Y') = ?");
            $stmt->execute([$area, $year_str]);
            $stats['kpi_total_externo_ingresos'] = floatval($stmt->fetchColumn());

            // 8. Calcular porcentaje de utilización basado en las variables anuales configuradas
            $stmt_cfg = $db->prepare("SELECT * FROM configuracion_anual WHERE year = ?");
            $stmt_cfg->execute([$year]);
            $config = $stmt_cfg->fetch();

            $start_md = $config ? $config['fecha_inicio'] : '03-01';
            $end_md = $config ? $config['fecha_fin'] : '12-01';
            $hours_per_day = $config ? floatval($config['horas_diarias']) : 8.00;

            $start_date_str = "$year-$start_md";
            $end_date_str = "$year-$end_md";
            
            $current_year = intval(date('Y'));
            $today_str = date('Y-m-d');
            
            $desc_w = $config ? intval($config['descuento_fines_semana']) : 0;
            $desc_h = $config ? intval($config['descuento_feriados']) : 0;

            if ($desc_w > 0 || $desc_h > 0) {
                // Manual overrides logic
                $start_dt = new DateTime($start_date_str);
                $end_dt = new DateTime($end_date_str);
                $total_days = intval($start_dt->diff($end_dt)->format('%a')) + 1;

                if ($year === $current_year) {
                    if ($today_str < $start_date_str) {
                        $available_days = 0;
                    } elseif ($today_str > $end_date_str) {
                        $available_days = $total_days - $desc_w - $desc_h;
                    } else {
                        $today_dt = new DateTime($today_str);
                        $days_passed = intval($start_dt->diff($today_dt)->format('%a')) + 1;
                        $factor = $days_passed / $total_days;
                        $available_days = $days_passed - (($desc_w + $desc_h) * $factor);
                    }
                } else {
                    $available_days = $total_days - $desc_w - $desc_h;
                }
            } else {
                // Automatic logic
                if ($year === $current_year) {
                    if ($today_str < $start_date_str) {
                        $available_days = 0;
                    } elseif ($today_str > $end_date_str) {
                        $available_days = $getNetworkDays($start_date_str, $end_date_str);
                    } else {
                        $available_days = $getNetworkDays($start_date_str, $today_str);
                    }
                } else {
                    $available_days = $getNetworkDays($start_date_str, $end_date_str);
                }
            }
            
            $available_hours = $available_days * $hours_per_day;
            if ($available_hours < 0) $available_hours = 0;
            
            $utilization_percentage = ($available_hours > 0) ? ($stats['kpi_total_uso'] / $available_hours) * 100 : 0;
            
            $stats['utilizacion'] = [
                'horas_disponibles' => round($available_hours, 1),
                'porcentaje' => round($utilization_percentage, 2),
                'rango' => ($year === $current_year && $today_str >= $start_date_str && $today_str <= $end_date_str) ? 'Proporcional al día de hoy' : 'Período Completo'
            ];

            // 8.1. Comparativa anual (Total horas por año para el simulador activo)
            $stmt_comp = $db->prepare("
                SELECT DATE_FORMAT(fecha, '%Y') as year, SUM(cantidad_horas) as total_hours 
                FROM uso_simulador 
                WHERE area_id = :area AND DATE_FORMAT(fecha, '%Y') IS NOT NULL
                GROUP BY year 
                ORDER BY year ASC
            ");
            $stmt_comp->execute([':area' => $area]);
            $stats['comparativa_anual'] = $stmt_comp->fetchAll();

            // 9. Obtener lista de todos los años disponibles para el selector
            $stmt = $db->query("
                SELECT DISTINCT DATE_FORMAT(fecha, '%Y') as yr FROM uso_simulador WHERE DATE_FORMAT(fecha, '%Y') IS NOT NULL
                UNION 
                SELECT DISTINCT DATE_FORMAT(fecha, '%Y') as yr FROM asistencia_voluntaria WHERE DATE_FORMAT(fecha, '%Y') IS NOT NULL
                ORDER BY yr DESC
            ");
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
            // Asegurar que al menos el año actual y los años mínimos existan
            if (!in_array(date('Y'), $years)) {
                $years[] = date('Y');
            }
            sort($years);
            $stats['years'] = array_reverse($years);

            sendResponse(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'list_data':
        $area = $_GET['area'] ?? '';
        $type = $_GET['type'] ?? ''; // 'uso', 'voluntario', 'externo'
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 50);
        $search = $_GET['search'] ?? '';

        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;

        try {
            $table = '';
            $search_col = '';
            $today = date('Y-m-d');
            
            $where_clause = " WHERE area_id = :area ";
            $params = [':area' => $area, ':today' => $today];

            $year = intval($_GET['year'] ?? 0);
            if ($year > 0) {
                $where_clause .= " AND DATE_FORMAT(fecha, '%Y') = :year ";
                $params[':year'] = strval($year);
            }

            if ($type === 'uso') {
                $table = 'uso_simulador';
                $search_query = " (curso LIKE :search OR asignatura LIKE :search OR observaciones LIKE :search OR categoria LIKE :search) ";
                $where_clause .= " AND (fecha <= :today OR cantidad_horas > 0 OR (curso IS NOT NULL AND curso != '')) ";
            } elseif ($type === 'voluntario') {
                $table = 'asistencia_voluntaria';
                $search_query = " (curso LIKE :search OR asignatura LIKE :search OR tema LIKE :search OR nombre_estudiante LIKE :search) ";
                $where_clause .= " AND (fecha <= :today OR horas > 0 OR (nombre_estudiante IS NOT NULL AND nombre_estudiante != '')) ";
            } elseif ($type === 'externo') {
                $table = 'entrenamiento_externo';
                $search_query = " (nombre_alumno LIKE :search OR run LIKE :search OR objeto_entrenamiento LIKE :search OR procedencia LIKE :search) ";
                $where_clause .= " AND (fecha <= :today OR cantidad_horas > 0 OR (nombre_alumno IS NOT NULL AND nombre_alumno != '')) ";
            } else {
                sendResponse(['success' => false, 'error' => 'Tipo de datos no válido'], 400);
            }

            if ($search !== '') {
                $where_clause .= " AND " . $search_query;
                $params[':search'] = '%' . $search . '%';
            }

            // Get total count
            validateTableName($table);
            $count_stmt = $db->prepare("SELECT COUNT(*) FROM $table $where_clause");
            $count_stmt->execute($params);
            $total_records = intval($count_stmt->fetchColumn());

            // Get records
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
            
            // In SQLite, LIMIT and OFFSET need to be handled carefully in prepared statements or passed as parameters
            validateTableName($table);
            $records_stmt = $db->prepare("SELECT * FROM $table $where_clause ORDER BY fecha DESC, id DESC LIMIT :limit OFFSET :offset");
            // Bind value types explicitly for limit and offset
            $records_stmt->bindValue(':area', $area, PDO::PARAM_STR);
            $records_stmt->bindValue(':today', $today, PDO::PARAM_STR);
            if ($year > 0) {
                $records_stmt->bindValue(':year', strval($year), PDO::PARAM_STR);
            }
            if ($search !== '') {
                $records_stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
            }
            $records_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $records_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $records_stmt->execute();
            $records = $records_stmt->fetchAll();

            sendResponse([
                'success' => true,
                'records' => $records,
                'total' => $total_records,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total_records / $limit)
            ]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'create':
        checkAuth();
        $area = $_GET['area'] ?? '';
        $type = $_GET['type'] ?? '';
        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            sendResponse(['success' => false, 'error' => 'Datos de entrada no válidos'], 400);
        }

        try {
            if ($type === 'uso') {
                $stmt = $db->prepare("
                    INSERT INTO uso_simulador 
                    (area_id, fecha, cantidad_horas, curso, asignatura, cantidad_alumnos, observaciones, categoria, horas_pedagogicas, comex, finex)
                    VALUES (:area, :fecha, :cantidad_horas, :curso, :asignatura, :cantidad_alumnos, :observaciones, :categoria, :horas_pedagogicas, :comex, :finex)
                ");
                $stmt->execute([
                    ':area' => $area,
                    ':fecha' => $input['fecha'] ?? date('Y-m-d'),
                    ':cantidad_horas' => floatval($input['cantidad_horas'] ?? 0.0),
                    ':curso' => $input['curso'] ?? null,
                    ':asignatura' => $input['asignatura'] ?? null,
                    ':cantidad_alumnos' => intval($input['cantidad_alumnos'] ?? 0),
                    ':observaciones' => $input['observaciones'] ?? null,
                    ':categoria' => $input['categoria'] ?? null,
                    ':horas_pedagogicas' => floatval($input['horas_pedagogicas'] ?? 0.0),
                    ':comex' => $input['comex'] ?? null,
                    ':finex' => $input['finex'] ?? null
                ]);
            } elseif ($type === 'voluntario') {
                $stmt = $db->prepare("
                    INSERT INTO asistencia_voluntaria 
                    (area_id, fecha, comex, finex, curso, asignatura, tema, nombre_estudiante, horas)
                    VALUES (:area, :fecha, :comex, :finex, :curso, :asignatura, :tema, :nombre_estudiante, :horas)
                ");
                $stmt->execute([
                    ':area' => $area,
                    ':fecha' => $input['fecha'] ?? date('Y-m-d'),
                    ':comex' => $input['comex'] ?? null,
                    ':finex' => $input['finex'] ?? null,
                    ':curso' => $input['curso'] ?? null,
                    ':asignatura' => $input['asignatura'] ?? null,
                    ':tema' => $input['tema'] ?? null,
                    ':nombre_estudiante' => $input['nombre_estudiante'] ?? '',
                    ':horas' => floatval($input['horas'] ?? 0.0)
                ]);
            } elseif ($type === 'externo') {
                $stmt = $db->prepare("
                    INSERT INTO entrenamiento_externo 
                    (area_id, fecha, nombre_alumno, run, telefono, email, cantidad_horas, objeto_entrenamiento, procedencia, monto_cancelado, boleta, examen_cimar)
                    VALUES (:area, :fecha, :nombre_alumno, :run, :telefono, :email, :cantidad_horas, :objeto_entrenamiento, :procedencia, :monto_cancelado, :boleta, :examen_cimar)
                ");
                $stmt->execute([
                    ':area' => $area,
                    ':fecha' => $input['fecha'] ?? date('Y-m-d'),
                    ':nombre_alumno' => $input['nombre_alumno'] ?? '',
                    ':run' => $input['run'] ?? null,
                    ':telefono' => $input['telefono'] ?? null,
                    ':email' => $input['email'] ?? null,
                    ':cantidad_horas' => floatval($input['cantidad_horas'] ?? 0.0),
                    ':objeto_entrenamiento' => $input['objeto_entrenamiento'] ?? null,
                    ':procedencia' => $input['procedencia'] ?? null,
                    ':monto_cancelado' => floatval($input['monto_cancelado'] ?? 0.0),
                    ':boleta' => $input['boleta'] ?? 'No',
                    ':examen_cimar' => $input['examen_cimar'] ?? 'Pendiente'
                ]);
            } else {
                sendResponse(['success' => false, 'error' => 'Tipo de datos no válido'], 400);
            }

            sendResponse(['success' => true, 'id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'update':
        checkAuth();
        $area = $_GET['area'] ?? '';
        $type = $_GET['type'] ?? '';
        $id = intval($_GET['id'] ?? 0);

        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }
        if ($id <= 0) {
            sendResponse(['success' => false, 'error' => 'ID no válido'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            sendResponse(['success' => false, 'error' => 'Datos de entrada no válidos'], 400);
        }

        try {
            if ($type === 'uso') {
                $stmt = $db->prepare("
                    UPDATE uso_simulador 
                    SET fecha = :fecha, cantidad_horas = :cantidad_horas, curso = :curso, asignatura = :asignatura, 
                        cantidad_alumnos = :cantidad_alumnos, observaciones = :observaciones, categoria = :categoria, 
                        horas_pedagogicas = :horas_pedagogicas, comex = :comex, finex = :finex
                    WHERE id = :id AND area_id = :area
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':area' => $area,
                    ':fecha' => $input['fecha'],
                    ':cantidad_horas' => floatval($input['cantidad_horas']),
                    ':curso' => $input['curso'],
                    ':asignatura' => $input['asignatura'],
                    ':cantidad_alumnos' => intval($input['cantidad_alumnos']),
                    ':observaciones' => $input['observaciones'],
                    ':categoria' => $input['categoria'],
                    ':horas_pedagogicas' => floatval($input['horas_pedagogicas']),
                    ':comex' => $input['comex'],
                    ':finex' => $input['finex']
                ]);
            } elseif ($type === 'voluntario') {
                $stmt = $db->prepare("
                    UPDATE asistencia_voluntaria 
                    SET fecha = :fecha, comex = :comex, finex = :finex, curso = :curso, asignatura = :asignatura, 
                        tema = :tema, nombre_estudiante = :nombre_estudiante, horas = :horas
                    WHERE id = :id AND area_id = :area
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':area' => $area,
                    ':fecha' => $input['fecha'],
                    ':comex' => $input['comex'],
                    ':finex' => $input['finex'],
                    ':curso' => $input['curso'],
                    ':asignatura' => $input['asignatura'],
                    ':tema' => $input['tema'],
                    ':nombre_estudiante' => $input['nombre_estudiante'],
                    ':horas' => floatval($input['horas'])
                ]);
            } elseif ($type === 'externo') {
                $stmt = $db->prepare("
                    UPDATE entrenamiento_externo 
                    SET fecha = :fecha, nombre_alumno = :nombre_alumno, run = :run, telefono = :telefono, email = :email, 
                        cantidad_horas = :cantidad_horas, objeto_entrenamiento = :objeto_entrenamiento, procedencia = :procedencia, 
                        monto_cancelado = :monto_cancelado, boleta = :boleta, examen_cimar = :examen_cimar
                    WHERE id = :id AND area_id = :area
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':area' => $area,
                    ':fecha' => $input['fecha'],
                    ':nombre_alumno' => $input['nombre_alumno'],
                    ':run' => $input['run'],
                    ':telefono' => $input['telefono'],
                    ':email' => $input['email'],
                    ':cantidad_horas' => floatval($input['cantidad_horas']),
                    ':objeto_entrenamiento' => $input['objeto_entrenamiento'],
                    ':procedencia' => $input['procedencia'],
                    ':monto_cancelado' => floatval($input['monto_cancelado']),
                    ':boleta' => $input['boleta'],
                    ':examen_cimar' => $input['examen_cimar']
                ]);
            } else {
                sendResponse(['success' => false, 'error' => 'Tipo de datos no válido'], 400);
            }

            sendResponse(['success' => true]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    case 'delete':
        checkAuth();
        $area = $_GET['area'] ?? '';
        $type = $_GET['type'] ?? '';
        $id = intval($_GET['id'] ?? 0);

        if (!validateArea($area)) {
            sendResponse(['success' => false, 'error' => 'Área no válida'], 400);
        }
        if ($id <= 0) {
            sendResponse(['success' => false, 'error' => 'ID no válido'], 400);
        }

        try {
            $table = '';
            if ($type === 'uso') {
                $table = 'uso_simulador';
            } elseif ($type === 'voluntario') {
                $table = 'asistencia_voluntaria';
            } elseif ($type === 'externo') {
                $table = 'entrenamiento_externo';
            } else {
                sendResponse(['success' => false, 'error' => 'Tipo de datos no válido'], 400);
            }

            validateTableName($table);
            $stmt = $db->prepare("DELETE FROM $table WHERE id = :id AND area_id = :area");
            $stmt->execute([':id' => $id, ':area' => $area]);

            sendResponse(['success' => true]);
        } catch (Exception $e) {
            sendErrorResponse($e);
        }
        break;

    default:
        sendResponse(['success' => false, 'error' => 'Acción no válida'], 400);
        break;
}
