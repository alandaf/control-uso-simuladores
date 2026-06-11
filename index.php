<?php
// Secure Session Configuration
$cookie_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $cookie_secure,
    'cookie_samesite' => 'Strict',
    'use_only_cookies' => true
]);
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas y Control de Simuladores</title>
    <meta name="description" content="Dashboard premium de control de horas y estadísticas para simuladores y talleres.">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="style.css">
    <!-- Chart.js CDN (with SRI) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js" integrity="sha384-e6cc9LaIG7xZ3XD5B+jtr1NhTWPQGQdRCh6xiZ+ZFUtWCpg4ycv3Sh+SkZoopvUY" crossorigin="anonymous"></script>
    <!-- Lucide Icons CDN (with SRI) -->
    <script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" integrity="sha384-hJnF5AwidE18GSWTAGHv3ByzzvfNZ1Tcx5y1UUV3WkauuMCEzBJBMSwSt/PUPXnM" crossorigin="anonymous"></script>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside>
        <div class="brand">
            <i aria-hidden="true" data-lucide="compass"></i>
            <h1>Control Simuladores</h1>
        </div>
        
        <p class="nav-label">Instalaciones</p>
        <ul class="nav-list">
            <li class="nav-item active" data-area="puente">
                <button><i aria-hidden="true" data-lucide="anchor"></i>Simulador de Puente</button>
            </li>
            <li class="nav-item" data-area="maquinas">
                <button><i aria-hidden="true" data-lucide="cpu"></i>Simulador de Máquinas</button>
            </li>
            <li class="nav-item" data-area="taller_maquinas">
                <button><i aria-hidden="true" data-lucide="wrench"></i>Taller de Máquinas</button>
            </li>
            <li class="nav-item" data-area="electronica">
                <button><i aria-hidden="true" data-lucide="zap"></i>Lab de Electrónica</button>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <p>Sistema de Control v1.0</p>
            <p>&copy; 2026</p>
        </div>
    </aside>

    <!-- Main Content Panel -->
    <main>
        <!-- Top App Bar -->
        <header style="display: flex; flex-direction: column; align-items: stretch; gap: 1.25rem;">
            <!-- Row 1: Title & Actions -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; width: 100%;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="hamburger"><i aria-hidden="true" data-lucide="menu"></i></button>
                    <div class="header-title">
                        <h2>Simulador de Puente</h2>
                        <p>Control de horas de navegación y entrenamientos</p>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <!-- Filtro de Año -->
                    <div style="display: flex; align-items: center; gap: 0.5rem; background-color: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 0.35rem 0.75rem; border-radius: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); white-space: nowrap;">Año:</label>
                        <select id="year-filter" style="background: none; border: none; color: var(--text-primary); font-size: 0.85rem; font-weight: 600; outline: none; cursor: pointer; padding-right: 0.5rem;">
                            <!-- Dynamically populated -->
                        </select>
                    </div>

                    <!-- Selector de Tamaño de Letra -->
                    <div style="display: flex; align-items: center; gap: 0.25rem; background-color: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 0.35rem 0.6rem; border-radius: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); white-space: nowrap; margin-right: 0.25rem;">Texto:</label>
                        <button id="btn-font-decrease" class="action-icon-btn" style="width:24px; height:24px; display:flex; align-items:center; justify-content:center; padding:0;" title="Disminuir letra"><i aria-hidden="true" data-lucide="minus" style="width:14px; height:14px;"></i></button>
                        <button id="btn-font-increase" class="action-icon-btn" style="width:24px; height:24px; display:flex; align-items:center; justify-content:center; padding:0;" title="Aumentar letra"><i aria-hidden="true" data-lucide="plus" style="width:14px; height:14px;"></i></button>
                    </div>

                    <!-- Botón de Autenticación -->
                    <?php if ($logged_in): ?>
                    <button id="btn-logout" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; height: 35px; line-height: 1.2;">
                        <i aria-hidden="true" data-lucide="log-out" style="width:14px; height:14px;"></i> Salir
                    </button>
                    <?php else: ?>
                    <button id="btn-login-open" class="btn btn-primary" style="padding: 0.35rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; height: 35px; line-height: 1.2;">
                        <i aria-hidden="true" data-lucide="log-in" style="width:14px; height:14px;"></i> Ingresar
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 2: Navigation Tabs -->
            <div class="tabs-container" style="width: 100%;">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="dashboard"><i aria-hidden="true" data-lucide="layout-dashboard"></i>Estadísticas</button>
                    <?php if ($logged_in): ?>
                    <button class="tab-btn" data-tab="uso"><i aria-hidden="true" data-lucide="clock"></i>Uso General</button>
                    <button class="tab-btn" data-tab="voluntario"><i aria-hidden="true" data-lucide="users"></i>Horas de asistencia voluntaria de estudiantes</button>
                    <button class="tab-btn" data-tab="externo"><i aria-hidden="true" data-lucide="award"></i>Externos</button>
                    <button class="tab-btn" data-tab="config"><i aria-hidden="true" data-lucide="settings"></i>Parámetros</button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- VIEW: DASHBOARD (Estadísticas) -->
        <div id="view-dashboard" class="view-container active">
            <!-- KPI Summary Cards -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-info">
                        <h4>Horas de Uso Total</h4>
                        <div class="kpi-value" id="kpi-total-uso">0 hrs</div>
                    </div>
                    <div class="kpi-icon blue">
                        <i aria-hidden="true" data-lucide="monitor"></i>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-info">
                        <h4>Porcentaje de Utilización</h4>
                        <div class="kpi-value" id="kpi-val-utilizacion" style="color: var(--warning); text-shadow: 0 0 10px rgba(245, 158, 11, 0.2);">0%</div>
                        <p id="kpi-utilizacion-subtext" style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Cargando...</p>
                    </div>
                    <div class="kpi-icon orange">
                        <i aria-hidden="true" data-lucide="percent"></i>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-info">
                        <h4>Horas de asistencia voluntaria de estudiantes</h4>
                        <div class="kpi-value" id="kpi-total-voluntario">0 hrs</div>
                    </div>
                    <div class="kpi-icon blue" style="color:#a5b4fc; background-color:rgba(165, 180, 252, 0.12);">
                        <i aria-hidden="true" data-lucide="heart-handshake"></i>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-info">
                        <h4>Horas de Entrenamiento Externo</h4>
                        <div class="kpi-value" id="kpi-total-externo-horas">0 hrs</div>
                        <p id="kpi-externo-horas-subtext" style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Cargando...</p>
                    </div>
                    <div class="kpi-icon green" style="color:#38bdf8; background-color:var(--success-glow);">
                        <i aria-hidden="true" data-lucide="award"></i>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-info">
                        <h4>Monto Recaudado</h4>
                        <div class="kpi-value" id="kpi-total-externo">$0</div>
                        <p id="kpi-externo-subtext" style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Cargando...</p>
                    </div>
                    <div class="kpi-icon green">
                        <i aria-hidden="true" data-lucide="dollar-sign"></i>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-info">
                        <h4>Promedio Alumnos/Hora</h4>
                        <div class="kpi-value" id="kpi-alumnos-hora">0.0</div>
                        <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Uso general anual</p>
                    </div>
                    <div class="kpi-icon orange" style="color:#a78bfa; background-color:rgba(167, 139, 250, 0.12);">
                        <i aria-hidden="true" data-lucide="users"></i>
                    </div>
                </div>
            </div>

            <!-- Charts and Rankings Grid -->
            <div class="dashboard-grid">
                <div class="chart-card" id="card-uso-mensual-container">
                    <h3><i aria-hidden="true" data-lucide="bar-chart-3"></i> Horas de Uso por Meses y Años</h3>
                    <div class="chart-container">
                        <canvas id="chart-uso-mensual"></canvas>
                    </div>
                </div>
                
                <div class="list-card" id="card-ranking-voluntarios-container">
                    <h3><i aria-hidden="true" data-lucide="star"></i> Ranking de Asistencia Voluntaria</h3>
                    <ul class="ranking-list" id="ranking-list">
                        <!-- Populated dynamically -->
                    </ul>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="chart-card">
                    <h3><i aria-hidden="true" data-lucide="pie-chart"></i> Horas Utilizadas por Asignatura</h3>
                    <div class="chart-container">
                        <canvas id="chart-asignaturas"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3><i aria-hidden="true" data-lucide="trending-up"></i> Ingresos Recaudados (Entrenamiento Externo)</h3>
                    <div class="chart-container">
                        <canvas id="chart-ingresos"></canvas>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="chart-card" id="card-voluntarios-mensual-container">
                    <h3><i aria-hidden="true" data-lucide="line-chart"></i> Asistencia Voluntaria de Estudiantes Mensual</h3>
                    <div class="chart-container">
                        <canvas id="chart-voluntarios"></canvas>
                    </div>
                </div>
                <div class="chart-card" id="card-comparativa-anual-container">
                    <h3><i aria-hidden="true" data-lucide="bar-chart-2"></i> Comparación de Horas de Utilización por Año</h3>
                    <div class="chart-container">
                        <canvas id="chart-comparativa-anual"></canvas>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="chart-card">
                    <h3><i aria-hidden="true" data-lucide="award"></i> Rendimiento de Exámenes CIMAR (Entrenamiento Externo)</h3>
                    <div class="chart-container" style="display: flex; align-items: center; justify-content: center; height: 280px; position: relative;">
                        <canvas id="chart-cimar" style="max-width: 500px;"></canvas>
                    </div>
                </div>
                <div class="chart-card" id="card-cimar-comparativa-container">
                    <h3><i aria-hidden="true" data-lucide="bar-chart-3"></i> Comparativa de Exámenes CIMAR por Año</h3>
                    <div class="chart-container">
                        <canvas id="chart-cimar-comparativa"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($logged_in): ?>
        <!-- VIEW: USO GENERAL (Uso del simulador diario) -->
        <div id="view-uso" class="view-container">
            <div class="table-toolbar">
                <div class="search-box">
                    <i aria-hidden="true" data-lucide="search"></i>
                    <input type="text" id="search-uso" placeholder="Buscar por curso, asignatura, categoría...">
                </div>
                <div class="action-buttons" style="display: flex; gap: 0.5rem;">
                    <button class="btn" style="background-color: var(--success-color, #10b981); color: white;" onclick="exportData('uso')">
                        <i aria-hidden="true" data-lucide="download"></i> Exportar Excel
                    </button>
                    <button class="btn btn-primary" onclick="openCreateModal('uso')">
                        <i aria-hidden="true" data-lucide="plus"></i> Añadir Registro
                    </button>
                </div>
            </div>
            
            <div class="table-card">
                <div class="table-responsive">
                    <table id="table-uso">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Curso</th>
                                <th>Asignatura</th>
                                <th>Alumnos</th>
                                <th>Categoría</th>
                                <th>Inicio</th>
                                <th>Término</th>
                                <th>Hrs Pedagógicas</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic -->
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-uso"></div>
            </div>
        </div>

        <!-- VIEW: VOLUNTARIOS (Asistencia voluntaria de estudiantes) -->
        <div id="view-voluntario" class="view-container">
            <div class="table-toolbar">
                <div class="search-box">
                    <i aria-hidden="true" data-lucide="search"></i>
                    <input type="text" id="search-voluntario" placeholder="Buscar por estudiante, curso, tema...">
                </div>
                <div class="action-buttons" style="display: flex; gap: 0.5rem;">
                    <button class="btn" style="background-color: var(--success-color, #10b981); color: white;" onclick="exportData('voluntario')">
                        <i aria-hidden="true" data-lucide="download"></i> Exportar Excel
                    </button>
                    <button class="btn btn-primary" onclick="openCreateModal('voluntario')">
                        <i aria-hidden="true" data-lucide="plus"></i> Añadir Registro
                    </button>
                </div>
            </div>
            
            <div class="table-card">
                <div class="table-responsive">
                    <table id="table-voluntario">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estudiante</th>
                                <th>Curso</th>
                                <th>Asignatura</th>
                                <th>Tema Entrenado</th>
                                <th>Inicio</th>
                                <th>Término</th>
                                <th>Hrs Pedagógicas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic -->
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-voluntario"></div>
            </div>
        </div>

        <!-- VIEW: EXTERNOS (Entrenamiento pagado personas externas) -->
        <div id="view-externo" class="view-container">
            <div class="table-toolbar">
                <div class="search-box">
                    <i aria-hidden="true" data-lucide="search"></i>
                    <input type="text" id="search-externo" placeholder="Buscar por alumno, RUN, procedencia...">
                </div>
                <div class="action-buttons" style="display: flex; gap: 0.5rem;">
                    <button class="btn" style="background-color: var(--success-color, #10b981); color: white;" onclick="exportData('externo')">
                        <i aria-hidden="true" data-lucide="download"></i> Exportar Excel
                    </button>
                    <button class="btn btn-primary" onclick="openCreateModal('externo')">
                        <i aria-hidden="true" data-lucide="plus"></i> Añadir Registro
                    </button>
                </div>
            </div>
            
            <div class="table-card">
                <div class="table-responsive">
                    <table id="table-externo">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Alumno</th>
                                <th>RUN</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <th>Hrs Pedagógicas</th>
                                <th>Objeto Entr.</th>
                                <th>Procedencia</th>
                                <th>Monto</th>
                                <th>Examen CIMAR</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic -->
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-externo"></div>
            </div>
        </div>

        <!-- VIEW: CONFIG (Parámetros/Configuración) -->
        <div id="view-config" class="view-container">
            <div class="dashboard-grid">
                <!-- Panel Cursos -->
                <div class="chart-card">
                    <h3><i aria-hidden="true" data-lucide="book-open"></i> Gestión de Cursos</h3>
                    <form id="form-add-curso" style="display: flex; gap: 0.5rem;">
                        <input type="text" id="new-curso-name" placeholder="Nombre del nuevo curso..." required style="flex-grow: 1;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1rem;"><i aria-hidden="true" data-lucide="plus"></i> Agregar</button>
                    </form>
                    <div style="overflow-y: auto; max-height: 280px; margin-top: 1rem;">
                        <ul class="ranking-list" id="config-cursos-list">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>
                </div>

                <!-- Panel Asignaturas -->
                <div class="chart-card">
                    <h3><i aria-hidden="true" data-lucide="graduation-cap"></i> Gestión de Asignaturas</h3>
                    <form id="form-add-asignatura" style="display: flex; gap: 0.5rem;">
                        <input type="text" id="new-asignatura-name" placeholder="Nombre de la nueva asignatura..." required style="flex-grow: 1;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1rem;"><i aria-hidden="true" data-lucide="plus"></i> Agregar</button>
                    </form>
                    <div style="overflow-y: auto; max-height: 280px; margin-top: 1rem;">
                        <ul class="ranking-list" id="config-asignaturas-list">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid" style="margin-top: 2rem;">
                <!-- Panel Configuración de Disponibilidad Anual -->
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3><i aria-hidden="true" data-lucide="calendar"></i> Disponibilidad Anual del Simulador</h3>
                    <form id="form-config-anual" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Año</label>
                            <input type="number" id="cfg-year" placeholder="Ej: 2026" required min="2020" max="2100" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Fecha Inicio (MM-DD)</label>
                            <input type="text" id="cfg-fecha-inicio" placeholder="03-01" required pattern="^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$" title="Formato MM-DD, ej: 03-01" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Fecha Fin (MM-DD)</label>
                            <input type="text" id="cfg-fecha-fin" placeholder="12-01" required pattern="^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$" title="Formato MM-DD, ej: 12-01" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Horas Diarias</label>
                            <input type="number" id="cfg-horas-diarias" placeholder="8" required min="1" max="24" step="0.5" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Días Sin Clases</label>
                            <input type="number" id="cfg-dias-sin-clases" placeholder="Ej: 10 (vacaciones, etc.)" min="0" max="150" style="width: 100%;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; height: 42px;"><i aria-hidden="true" data-lucide="save" style="width:16px; height:16px; margin-right:5px;"></i> Guardar</button>
                    </form>
                    
                    <div style="margin-top: 1.5rem; overflow-x: auto;">
                        <table style="width:100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">
                                    <th style="padding: 0.75rem;">Año</th>
                                    <th style="padding: 0.75rem;">Fecha Inicio</th>
                                    <th style="padding: 0.75rem;">Fecha Fin</th>
                                    <th style="padding: 0.75rem;">Horas Disponibilidad</th>
                                    <th style="padding: 0.75rem;">Días Hábiles Sin Clases</th>
                                </tr>
                            </thead>
                            <tbody id="config-anual-list">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid" style="margin-top: 2rem;">
                <!-- Panel Gestión de Acceso (Cambiar contraseña) -->
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3><i aria-hidden="true" data-lucide="shield-alert"></i> Gestión de Acceso Administrativo</h3>
                    <form id="form-change-admin" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Nuevo Usuario</label>
                            <input type="text" id="admin-new-username" placeholder="admin" required style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Nueva Contraseña</label>
                            <input type="password" id="admin-new-password" placeholder="Mínimo 6 caracteres" required minlength="6" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.85rem; font-weight: 500;">Confirmar Contraseña</label>
                            <input type="password" id="admin-confirm-password" placeholder="Repita la contraseña" required minlength="6" style="width: 100%;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; height: 42px;"><i aria-hidden="true" data-lucide="key" style="width:16px; height:16px; margin-right:5px;"></i> Actualizar Accesos</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <?php if ($logged_in): ?>
    <!-- MODAL: USO GENERAL -->
    <div id="modal-uso" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nuevo Registro de Uso</h3>
                <button class="modal-close" onclick="closeModal('uso')"><i aria-hidden="true" data-lucide="x"></i></button>
            </div>
            <form id="form-uso">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Fecha</label>
                            <input type="date" name="fecha" required>
                        </div>
                        <div class="form-group">
                            <label>Horas Pedagógicas</label>
                            <input type="number" name="cantidad_horas" step="0.1" min="0" required placeholder="Ej: 4">
                        </div>
                        <div class="form-group">
                            <label>Curso</label>
                            <select name="curso" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Asignatura</label>
                            <select name="asignatura" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cantidad Alumnos</label>
                            <input type="number" name="cantidad_alumnos" min="0" required placeholder="Ej: 5">
                        </div>
                        <div class="form-group">
                            <label>Categoría</label>
                            <select name="categoria" required>
                                <option value="">Seleccione...</option>
                                <option value="Academia" selected>Academia</option>
                                <option value="Ex Estudiante">Ex Estudiante</option>
                                <option value="Ex Estudiante 2025">Ex Estudiante 2025</option>
                                <option value="Personas externas">Personas externas</option>
                                <option value="Maritimos">Marítimos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Comex (Inicio)</label>
                            <input type="time" name="comex" required>
                        </div>
                        <div class="form-group">
                            <label>Finex (Fin)</label>
                            <input type="time" name="finex" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Observaciones</label>
                            <textarea name="observaciones" rows="3" required placeholder="Detalles de la sesión..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('uso')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: VOLUNTARIOS -->
    <div id="modal-voluntario" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nuevo Registro de Asistencia Voluntaria de Estudiantes</h3>
                <button class="modal-close" onclick="closeModal('voluntario')"><i aria-hidden="true" data-lucide="x"></i></button>
            </div>
            <form id="form-voluntario">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nombre y Apellido del Estudiante</label>
                            <input type="text" name="nombre_estudiante" required placeholder="Ej: Juan Pérez" list="student-names">
                             <datalist id="student-names"></datalist>
                             <datalist id="externo-names"></datalist>
                        </div>
                        <div class="form-group">
                            <label>Fecha</label>
                            <input type="date" name="fecha" required>
                        </div>
                        <div class="form-group">
                            <label>Horas Pedagógicas</label>
                            <input type="number" name="horas" step="0.1" min="0" required placeholder="Ej: 2.0">
                        </div>
                        <div class="form-group">
                            <label>Curso</label>
                            <select name="curso" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Asignatura</label>
                            <select name="asignatura" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Tema Entrenado</label>
                            <input type="text" name="tema" placeholder="Ej: Órdenes al timonel y fondeo">
                        </div>
                        <div class="form-group">
                            <label>Comex (Inicio)</label>
                            <input type="time" name="comex">
                        </div>
                        <div class="form-group">
                            <label>Finex (Fin)</label>
                            <input type="time" name="finex">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('voluntario')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EXTERNOS -->
    <div id="modal-externo" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nuevo Registro Entrenamiento Externo</h3>
                <button class="modal-close" onclick="closeModal('externo')"><i aria-hidden="true" data-lucide="x"></i></button>
            </div>
            <form id="form-externo">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nombre Completo Alumno</label>
                             <input type="text" name="nombre_alumno" required placeholder="Ej: Carlos Silva" list="externo-names">
                        </div>
                        <div class="form-group">
                            <label>Fecha</label>
                            <input type="date" name="fecha" required>
                        </div>
                        <div class="form-group">
                            <label>RUN</label>
                            <input type="text" name="run" placeholder="Ej: 12345678-9">
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" placeholder="Ej: 9 87654321">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="Ej: carlos@gmail.com">
                        </div>
                        <div class="form-group">
                            <label>Horas Pedagógicas</label>
                            <input type="number" name="cantidad_horas" step="0.1" min="0" required placeholder="Ej: 4">
                        </div>
                        <div class="form-group">
                            <label>Objeto del Entrenamiento</label>
                            <input type="text" name="objeto_entrenamiento" placeholder="Ej: Piloto Costero / 3° Piloto">
                        </div>
                        <div class="form-group">
                            <label>Procedencia</label>
                            <input type="text" name="procedencia" placeholder="Ej: Ex alumno / Particular">
                        </div>
                        <div class="form-group">
                            <label>Monto Cancelado ($)</label>
                            <input type="number" name="monto_cancelado" min="0" required placeholder="Ej: 40000">
                        </div>

                        <div class="form-group">
                            <label>Examen CIMAR</label>
                            <select name="examen_cimar">
                                <option value="Pendiente">Pendiente</option>
                                <option value="Aprobado">Aprobado</option>
                                <option value="Reprobado">Reprobado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('externo')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- MODAL: LOGIN -->
    <div id="modal-login" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Acceso de Administrador</h3>
                <button class="modal-close" id="btn-login-close"><i aria-hidden="true" data-lucide="x"></i></button>
            </div>
            <form id="form-login">
                <div class="modal-body">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div class="form-group" style="width: 100%;">
                            <label>Usuario</label>
                            <input type="text" name="username" required placeholder="Ej: admin" style="width: 100%;">
                        </div>
                        <div class="form-group" style="width: 100%;">
                            <label>Contraseña</label>
                            <input type="password" name="password" required placeholder="••••••••" style="width: 100%;">
                        </div>
                        <div id="login-error-msg" style="color: var(--danger); font-size: 0.85rem; display: none; text-align: center; font-weight: 500;">
                            Credenciales incorrectas. Intente de nuevo.
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" id="btn-login-cancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Entrar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Application Script Logic -->
    <script src="app.js"></script>
</body>
</html>
