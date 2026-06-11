// Global Application State
const state = {
    currentArea: 'puente',
    currentTab: 'dashboard',
    currentYear: new Date().getFullYear(),
    charts: {},
    pagination: {
        uso: { page: 1, limit: 15, pages: 1 },
        voluntario: { page: 1, limit: 15, pages: 1 },
        externo: { page: 1, limit: 15, pages: 1 }
    },
    search: {
        uso: '',
        voluntario: '',
        externo: ''
    }
};

// Global Parameters Cache
const areaParams = {
    cursos: [],
    asignaturas: [],
    alumnos_externos: []
};

// Helper to escape HTML characters (Anti-XSS Protection)
function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>'"]/g, tag => {
        const chars = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        };
        return chars[tag] || tag;
    });
}

// Global Fetch Interceptor to Automatically Append CSRF Tokens on POST / DELETE requests
const originalFetch = window.fetch;
window.fetch = function (url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (method === 'POST' || method === 'DELETE') {
        options.headers = options.headers || {};
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            if (options.headers instanceof Headers) {
                options.headers.set('X-CSRF-Token', token);
            } else {
                options.headers['X-CSRF-Token'] = token;
            }
            // Fallback: Append token to URL query params to bypass server custom header stripping
            const separator = url.includes('?') ? '&' : '?';
            url = `${url}${separator}csrf_token=${encodeURIComponent(token)}`;
        }
    }
    return originalFetch(url, options);
};

// Available Spaces Config
const areasConfig = {
    puente: { name: 'Simulador de Puente', desc: 'Control de horas de navegación y entrenamientos' },
    maquinas: { name: 'Simulador de Máquinas', desc: 'Registro y control del simulador de propulsión' },
    taller_maquinas: { name: 'Taller de Máquinas', desc: 'Uso de máquinas herramientas y soldadura' },
    electronica: { name: 'Laboratorio de Electrónica', desc: 'Uso de instrumentación y desarrollo de circuitos' }
};

// Initialize Application
document.addEventListener('DOMContentLoaded', async () => {
    // Render Lucide Icons
    lucide.createIcons();
    
    // Bind Event Listeners
    initEventListeners();
    
    // Load holidays and initial load
    await loadChileanHolidays();
    switchArea(state.currentArea);
});

// Setup Event Listeners
function initEventListeners() {
    // Sidebar Area Buttons
    document.querySelectorAll('.nav-item button').forEach(button => {
        button.addEventListener('click', (e) => {
            const navItem = e.target.closest('.nav-item');
            const area = navItem.dataset.area;
            
            // Remove active classes
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            navItem.classList.add('active');
            
            // Toggle sidebar on mobile
            document.querySelector('aside').classList.remove('active');
            
            switchArea(area);
        });
    });

    // Mobile Hamburger
    const hamburger = document.querySelector('.hamburger');
    if (hamburger) {
        hamburger.addEventListener('click', () => {
            document.querySelector('aside').classList.toggle('active');
        });
    }

    // Tabs Navigation
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            switchTab(btn.dataset.tab);
        });
    });

    // Search Input Listeners with Debounce
    const searchInputs = {
        uso: document.getElementById('search-uso'),
        voluntario: document.getElementById('search-voluntario'),
        externo: document.getElementById('search-externo')
    };

    Object.keys(searchInputs).forEach(type => {
        if (searchInputs[type]) {
            let timeout = null;
            searchInputs[type].addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    state.search[type] = e.target.value;
                    state.pagination[type].page = 1;
                    loadTableData(type);
                }, 400);
            });
        }
    });

    // Setup Modals Forms Submission
    setupFormSubmit('form-uso', 'uso');
    setupFormSubmit('form-voluntario', 'voluntario');
    setupFormSubmit('form-externo', 'externo');

    // Config View Parameter Add Forms
    const formAddCurso = document.getElementById('form-add-curso');
    if (formAddCurso) {
        formAddCurso.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nameEl = document.getElementById('new-curso-name');
            const name = nameEl.value.trim();
            if (!name) return;
            await addParameter('curso', name);
            nameEl.value = '';
        });
    }

    const formAddAsignatura = document.getElementById('form-add-asignatura');
    if (formAddAsignatura) {
        formAddAsignatura.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nameEl = document.getElementById('new-asignatura-name');
            const name = nameEl.value.trim();
            if (!name) return;
            await addParameter('asignatura', name);
            nameEl.value = '';
        });
    }

    const formConfigAnual = document.getElementById('form-config-anual');
    if (formConfigAnual) {
        formConfigAnual.addEventListener('submit', async (e) => {
            e.preventDefault();
            const year = parseInt(document.getElementById('cfg-year').value);
            const fecha_inicio = document.getElementById('cfg-fecha-inicio').value.trim();
            const fecha_fin = document.getElementById('cfg-fecha-fin').value.trim();
            const horas_diarias = parseFloat(document.getElementById('cfg-horas-diarias').value);
            const dias_sin_clases = parseInt(document.getElementById('cfg-dias-sin-clases').value) || 0;

            try {
                const res = await fetch('api.php?action=save_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ year, fecha_inicio, fecha_fin, horas_diarias, dias_sin_clases })
                });
                const result = await handleFetchResponse(res);
                if (result.success) {
                    showToast('Configuración anual guardada con éxito', 'success');
                    renderConfigParams();
                } else {
                    showToast(result.error || 'Error al guardar configuración', 'danger');
                }
            } catch (err) {
                if (err.message !== 'No autorizado') {
                    showToast('Error al guardar configuración', 'danger');
                    console.error(err);
                }
            }
        });

        const cfgYearInput = document.getElementById('cfg-year');
        if (cfgYearInput) {
            cfgYearInput.addEventListener('input', () => {
                const yr = parseInt(cfgYearInput.value);
                if (!yr) return;
                const config = state.yearlyConfigs?.find(c => parseInt(c.year) === yr);
                if (config) {
                    document.getElementById('cfg-fecha-inicio').value = config.fecha_inicio;
                    document.getElementById('cfg-fecha-fin').value = config.fecha_fin;
                    document.getElementById('cfg-horas-diarias').value = config.horas_diarias;
                    document.getElementById('cfg-dias-sin-clases').value = config.dias_sin_clases;
                } else {
                    document.getElementById('cfg-fecha-inicio').value = '03-01';
                    document.getElementById('cfg-fecha-fin').value = '12-01';
                    document.getElementById('cfg-horas-diarias').value = '8';
                    document.getElementById('cfg-dias-sin-clases').value = '0';
                }
            });
        }
    }

    // Auto-populate external training form fields when selecting a trainee
    const formExterno = document.getElementById('form-externo');
    if (formExterno) {
        const nombreInput = formExterno.querySelector('[name="nombre_alumno"]');
        if (nombreInput) {
            nombreInput.addEventListener('input', () => {
                const nombre = nombreInput.value.trim().toLowerCase();
                if (!nombre) return;
                const match = areaParams.alumnos_externos?.find(a => a.nombre_alumno.toLowerCase() === nombre);
                if (match) {
                    formExterno.querySelector('[name="run"]').value = match.run || '';
                    formExterno.querySelector('[name="telefono"]').value = match.telefono || '';
                    formExterno.querySelector('[name="email"]').value = match.email || '';
                    formExterno.querySelector('[name="objeto_entrenamiento"]').value = match.objeto_entrenamiento || '';
                    formExterno.querySelector('[name="procedencia"]').value = match.procedencia || '';
                }
            });
        }
    }

    // Change Admin Credentials Form
    const formChangeAdmin = document.getElementById('form-change-admin');
    if (formChangeAdmin) {
        formChangeAdmin.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('admin-new-username').value.trim();
            const password = document.getElementById('admin-new-password').value;
            const confirmPassword = document.getElementById('admin-confirm-password').value;

            if (password !== confirmPassword) {
                showToast('Las contraseñas no coinciden', 'danger');
                return;
            }

            try {
                const res = await fetch('api.php?action=change_admin_credentials', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ username, password })
                });
                const result = await handleFetchResponse(res);
                if (result.success) {
                    showToast('Credenciales actualizadas. Cerrando sesión...', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(result.error || 'Error al actualizar credenciales', 'danger');
                }
            } catch (err) {
                if (err.message !== 'No autorizado') {
                    showToast('Error al actualizar credenciales', 'danger');
                    console.error(err);
                }
            }
        });
    }

    // Year Filter Selector Listener
    const yearFilter = document.getElementById('year-filter');
    if (yearFilter) {
        yearFilter.addEventListener('change', async (e) => {
            state.currentYear = parseInt(e.target.value);
            await loadChileanHolidays();
            refreshCurrentView();
        });
    }

    // Font Resizing Logic
    let baseFontSize = 100; // percent
    const btnIncrease = document.getElementById('btn-font-increase');
    const btnDecrease = document.getElementById('btn-font-decrease');
    if (btnIncrease && btnDecrease) {
        btnIncrease.addEventListener('click', () => {
            baseFontSize += 5;
            if (baseFontSize > 130) baseFontSize = 130;
            document.documentElement.style.fontSize = `${baseFontSize}%`;
        });
        btnDecrease.addEventListener('click', () => {
            baseFontSize -= 5;
            if (baseFontSize < 85) baseFontSize = 85;
            document.documentElement.style.fontSize = `${baseFontSize}%`;
        });
    }

    // Auth Event Listeners
    const btnLoginOpen = document.getElementById('btn-login-open');
    const btnLoginClose = document.getElementById('btn-login-close');
    const btnLoginCancel = document.getElementById('btn-login-cancel');
    const modalLogin = document.getElementById('modal-login');
    const formLogin = document.getElementById('form-login');
    const btnLogout = document.getElementById('btn-logout');

    if (btnLoginOpen && modalLogin) {
        btnLoginOpen.addEventListener('click', () => {
            const errEl = document.getElementById('login-error-msg');
            if (errEl) errEl.style.display = 'none';
            formLogin.reset();
            modalLogin.classList.add('active');
        });
    }

    if (btnLoginClose && modalLogin) {
        btnLoginClose.addEventListener('click', () => {
            modalLogin.classList.remove('active');
        });
    }

    if (btnLoginCancel && modalLogin) {
        btnLoginCancel.addEventListener('click', () => {
            modalLogin.classList.remove('active');
        });
    }

    if (formLogin) {
        formLogin.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formLogin);
            const data = {};
            formData.forEach((value, key) => data[key] = value);
            
            try {
                const res = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    showToast('Inicio de sesión exitoso', 'success');
                    location.reload();
                } else {
                    const errEl = document.getElementById('login-error-msg');
                    if (errEl) {
                        errEl.textContent = result.error || 'Credenciales incorrectas';
                        errEl.style.display = 'block';
                    }
                }
            } catch (err) {
                console.error(err);
                showToast('Error de red al intentar iniciar sesión', 'danger');
            }
        });
    }

    if (btnLogout) {
        btnLogout.addEventListener('click', async () => {
            if (!confirm('¿Está seguro de que desea cerrar sesión?')) return;
            try {
                const res = await fetch('api.php?action=logout', { method: 'POST' });
                const result = await res.json();
                if (result.success) {
                    showToast('Sesión cerrada con éxito', 'success');
                    location.reload();
                }
            } catch (err) {
                console.error(err);
                showToast('Error de red al intentar cerrar sesión', 'danger');
            }
        });
    }

    // Press Escape to Close Active Modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.active');
            if (activeModal) {
                const type = activeModal.id.replace('modal-', '');
                closeModal(type);
            }
            const loginModal = document.getElementById('modal-login');
            if (loginModal && loginModal.classList.contains('active')) {
                loginModal.classList.remove('active');
            }
        }
    });

    // Auto-calculate Pedagogical Hours from Comex/Finex
    initTimeCalculationListeners();
}

// Auto-calculate pedagogical hours on time change
function initTimeCalculationListeners() {
    const forms = [
        { id: 'form-uso', comexName: 'comex', finexName: 'finex', hoursName: 'cantidad_horas' },
        { id: 'form-voluntario', comexName: 'comex', finexName: 'finex', hoursName: 'horas' }
    ];

    forms.forEach(cfg => {
        const form = document.getElementById(cfg.id);
        if (!form) return;
        const comexInput = form.querySelector(`[name="${cfg.comexName}"]`);
        const finexInput = form.querySelector(`[name="${cfg.finexName}"]`);
        const hoursInput = form.querySelector(`[name="${cfg.hoursName}"]`);

        if (comexInput && finexInput && hoursInput) {
            const updateHours = () => {
                const t1 = comexInput.value;
                const t2 = finexInput.value;
                if (t1 && t2) {
                    const [h1, m1] = t1.split(':').map(Number);
                    const [h2, m2] = t2.split(':').map(Number);
                    let diff = (h2 * 60 + m2) - (h1 * 60 + m1);
                    if (diff < 0) diff = 0;
                    const pedHours = parseFloat((diff / 45).toFixed(2));
                    hoursInput.value = pedHours;
                }
            };
            comexInput.addEventListener('change', updateHours);
            finexInput.addEventListener('change', updateHours);
        }
    });
}

// Switch Area Logic
async function switchArea(area) {
    state.currentArea = area;
    
    // Update Title and Subtitle
    const titleEl = document.querySelector('.header-title h2');
    const descEl = document.querySelector('.header-title p');
    if (titleEl && descEl) {
        titleEl.textContent = areasConfig[area].name;
        descEl.textContent = areasConfig[area].desc;
    }

    // Reset tables pagination
    Object.keys(state.pagination).forEach(k => state.pagination[k].page = 1);

    // Load parameters first, then refresh view
    await loadAreaParameters();
    refreshCurrentView();
}

// Switch Tab Logic
function switchTab(tab) {
    state.currentTab = tab;
    
    // Show/Hide relevant views
    document.querySelectorAll('.view-container').forEach(view => {
        view.classList.remove('active');
    });
    
    const targetView = document.getElementById(`view-${tab}`);
    if (targetView) {
        targetView.classList.add('active');
    }
    
    refreshCurrentView();
}

// Refresh Current View
function refreshCurrentView() {
    if (state.currentTab === 'dashboard') {
        loadDashboard();
    } else if (state.currentTab === 'config') {
        renderConfigParams();
    } else {
        loadTableData(state.currentTab);
    }
}

// Format currency
function formatCLP(val) {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(val);
}

// Format date from YYYY-MM-DD to DD-MMM-YYYY (e.g. 05-JUN-2026)
function formatDateDMY(dateStr) {
    if (!dateStr) return '-';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const months = ["ENE", "FEB", "MAR", "ABR", "MAY", "JUN", "JUL", "AGO", "SEP", "OCT", "NOV", "DIC"];
    const m = parseInt(parts[1], 10);
    const monthLetter = (m >= 1 && m <= 12) ? months[m - 1] : parts[1];
    return `${parts[2]}-${monthLetter}-${parts[0]}`;
}

// Format month value to letters + year (e.g., "Ene 2026")
function formatMonthLetters(monthVal, yearVal) {
    const months = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
    const m = parseInt(monthVal, 10);
    if (isNaN(m) || m < 1 || m > 12) return `${monthVal}/${yearVal}`;
    return `${months[m - 1]} ${yearVal}`;
}

// Intercepts unauthorized responses
async function handleFetchResponse(res) {
    if (res.status === 401) {
        showToast('Sesión expirada o no autorizada. Iniciando login...', 'danger');
        setTimeout(() => location.reload(), 1500);
        throw new Error('No autorizado');
    }
    return res.json();
}

// Chilean Holidays List loaded dynamically from DB/API
let CHILEAN_HOLIDAYS = {};

async function loadChileanHolidays() {
    try {
        const res = await fetch(`api.php?action=get_feriados&year=${state.currentYear}`);
        const data = await res.json();
        if (data.success) {
            CHILEAN_HOLIDAYS = data.feriados || {};
        }
    } catch (err) {
        console.error("Error al cargar feriados dinámicos:", err);
    }
}

// Check if date is weekend or holiday
function getDateType(dateStr) {
    if (!dateStr) return null;
    if (CHILEAN_HOLIDAYS[dateStr]) {
        return { type: 'holiday', label: CHILEAN_HOLIDAYS[dateStr] };
    }
    const dateObj = new Date(dateStr + 'T00:00:00');
    const day = dateObj.getDay();
    if (day === 6) {
        return { type: 'weekend', label: 'Sábado' };
    } else if (day === 0) {
        return { type: 'weekend', label: 'Domingo' };
    }
    return null;
}

// Toast Notifications
function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container') || (() => {
        const c = document.createElement('div');
        c.className = 'toast-container';
        document.body.appendChild(c);
        return c;
    })();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = 'check-circle';
    if (type === 'danger') icon = 'x-circle';
    if (type === 'warning') icon = 'alert-triangle';

    toast.innerHTML = `
        <i aria-hidden="true" data-lucide="${icon}"></i>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    lucide.createIcons();

    setTimeout(() => {
        toast.remove();
    }, 4000);
}

// Populate the year filter selector dynamically
function populateYearFilter(years) {
    const select = document.getElementById('year-filter');
    if (!select) return;
    
    const currentVal = select.value || state.currentYear.toString();
    select.innerHTML = '';
    years.forEach(y => {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        select.appendChild(opt);
    });
    
    // Set to currentVal if it exists in list, otherwise select first
    select.value = currentVal;
    if (!select.value && select.options.length > 0) {
        select.selectedIndex = 0;
        state.currentYear = parseInt(select.value);
    }
}

// Load Dashboard Data & Charts
async function loadDashboard() {
    try {
        const res = await fetch(`api.php?action=get_stats&area=${state.currentArea}&year=${state.currentYear}`);
        const data = await res.json();
        
        if (!data.success) {
            showToast(data.error || 'Error al cargar estadísticas', 'danger');
            return;
        }

        const stats = data.stats;

        // Render Years Selector options
        if (stats.years) {
            populateYearFilter(stats.years);
        }

        // Render KPIs
        document.getElementById('kpi-total-uso').textContent = Math.round(stats.kpi_total_uso || 0) + ' hrs';
        document.getElementById('kpi-total-voluntario').textContent = Math.round(stats.kpi_total_voluntario || 0) + ' hrs';
        
        const alumnosHoraEl = document.getElementById('kpi-alumnos-hora');
        if (alumnosHoraEl) {
            alumnosHoraEl.textContent = parseFloat(stats.kpi_alumnos_por_hora || 0).toFixed(1);
        }
        
        const ext = stats.externo_totales;
        const extValEl = document.getElementById('kpi-total-externo');
        const extSubtextEl = document.getElementById('kpi-externo-subtext');
        if (extValEl && extSubtextEl) {
            extValEl.textContent = formatCLP(stats.kpi_total_externo_ingresos || 0);
            if (ext) {
                const hrs = Math.round(parseFloat(ext.total_hours || 0));
                const students = ext.total_students || 0;
                extSubtextEl.innerHTML = `de <strong>${hrs}</strong> hrs / <strong>${students}</strong> alumnos`;
            } else {
                extSubtextEl.textContent = 'de 0 hrs / 0 alumnos';
            }
        }

        const extHoursValEl = document.getElementById('kpi-total-externo-horas');
        const extHoursSubtextEl = document.getElementById('kpi-externo-horas-subtext');
        if (extHoursValEl && extHoursSubtextEl) {
            if (ext) {
                const hrs = Math.round(parseFloat(ext.total_hours || 0));
                const students = ext.total_students || 0;
                extHoursValEl.textContent = `${hrs} hrs`;
                extHoursSubtextEl.innerHTML = `por <strong>${students}</strong> alumnos / <strong>${ext.total_approved || 0}</strong> aprobados`;
            } else {
                extHoursValEl.textContent = '0 hrs';
                extHoursSubtextEl.textContent = 'por 0 alumnos';
            }
        }

        // Render Utilization Percentage KPI Card
        const util = stats.utilizacion;
        const utilValEl = document.getElementById('kpi-val-utilizacion');
        const utilSubtextEl = document.getElementById('kpi-utilizacion-subtext');
        if (util && utilValEl && utilSubtextEl) {
            utilValEl.textContent = `${util.porcentaje}%`;
            utilSubtextEl.innerHTML = `de <strong>${util.horas_disponibles}</strong> hrs disp.<br><span style="font-size: 0.65rem; opacity: 0.7;">(${util.rango})</span>`;
        }

        // Render Top Students List
        const rankingCard = document.getElementById('card-ranking-voluntarios-container');
        const usoCard = document.getElementById('card-uso-mensual-container');
        
        if (state.currentYear === 2024 || state.currentYear === 2025) {
            if (rankingCard) rankingCard.style.display = 'none';
            if (usoCard) usoCard.style.gridColumn = '1 / -1';
        } else {
            if (rankingCard) rankingCard.style.display = 'flex';
            if (usoCard) usoCard.style.gridColumn = 'auto';
        }

        const rankingListEl = document.getElementById('ranking-list');
        if (rankingListEl) {
            rankingListEl.innerHTML = '';
            if (stats.top_estudiantes && stats.top_estudiantes.length > 0) {
                stats.top_estudiantes.forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'ranking-item';
                    li.innerHTML = `
                        <span class="ranking-name" title="${escapeHTML(item.nombre_estudiante)}">${escapeHTML(item.nombre_estudiante)}</span>
                        <span class="ranking-badge">${parseFloat(item.total_hours).toFixed(1)} hrs</span>
                    `;
                    rankingListEl.appendChild(li);
                });
            } else {
                rankingListEl.innerHTML = '<li class="ranking-item"><span class="ranking-name">Sin registros</span></li>';
            }
        }

        // Render Charts
        renderUsoMensualChart(stats.uso_mensual, stats.voluntarios_mensual, stats.externo_mensual);
        renderVoluntariosMensualChart(stats.voluntarios_mensual);
        renderAsignaturasChart(stats.horas_asignatura);
        renderExternosIngresosChart(stats.externo_mensual);
        renderComparativaAnualChart(stats.comparativa_anual);

    } catch (e) {
        showToast('Error de conexión con la API', 'danger');
        console.error(e);
    }
}

// Reusable Chart Helper to Destroy Existing Instance Before Re-initializing
function prepareChartCanvas(name) {
    if (state.charts[name]) {
        state.charts[name].destroy();
        state.charts[name] = null;
    }
}

// Chart 1: Uso Mensual
function renderUsoMensualChart(usoData = [], voluntarioData = [], externoData = []) {
    prepareChartCanvas('uso_mensual');
    const ctx = document.getElementById('chart-uso-mensual').getContext('2d');
    
    // Group all months across the datasets to align them
    const monthlyMap = {};
    
    // Helper to add data to monthlyMap
    const addData = (arr, key) => {
        if (!arr || !Array.isArray(arr)) return;
        arr.forEach(d => {
            const monthStr = `${d.year}-${String(d.month).padStart(2, '0')}`;
            if (!monthlyMap[monthStr]) {
                monthlyMap[monthStr] = { uso: 0, voluntario: 0, externo: 0, month: d.month, year: d.year };
            }
            monthlyMap[monthStr][key] += parseFloat(d.total_hours || 0);
        });
    };
    
    addData(usoData, 'uso');
    addData(voluntarioData, 'voluntario');
    addData(externoData, 'externo');
    
    // Sort keys chronologically
    const sortedKeys = Object.keys(monthlyMap).sort();
    
    const labels = sortedKeys.map(k => formatMonthLetters(monthlyMap[k].month, monthlyMap[k].year));
    const usoValues = sortedKeys.map(k => monthlyMap[k].uso);
    const voluntarioValues = sortedKeys.map(k => monthlyMap[k].voluntario);
    const externoValues = sortedKeys.map(k => monthlyMap[k].externo);

    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['Sin datos'],
            datasets: [
                {
                    label: 'Uso General',
                    data: usoValues.length ? usoValues : [0],
                    backgroundColor: 'rgba(226, 110, 58, 0.75)',
                    borderColor: '#e26e3a',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Asistencia Voluntaria',
                    data: voluntarioValues.length ? voluntarioValues : [0],
                    backgroundColor: 'rgba(56, 189, 248, 0.75)',
                    borderColor: '#38bdf8',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Entrenamiento Externo',
                    data: externoValues.length ? externoValues : [0],
                    backgroundColor: 'rgba(165, 180, 252, 0.75)',
                    borderColor: '#a5b4fc',
                    borderWidth: 1.5,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { color: '#eef1f6', font: { size: 10 } }
                }
            },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#9ca3af' } },
                x: { grid: { display: false }, ticks: { color: '#9ca3af' } }
            }
        }
    });
    state.charts['uso_mensual'] = chart;
}

// Chart 2: Asistencia Voluntaria
function renderVoluntariosMensualChart(data = []) {
    prepareChartCanvas('voluntarios');
    const ctx = document.getElementById('chart-voluntarios').getContext('2d');
    
    const sorted = [...data].reverse();
    const labels = sorted.map(d => formatMonthLetters(d.month, d.year));
    const values = sorted.map(d => d.total_hours);

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['Sin datos'],
            datasets: [{
                label: 'Horas de asistencia voluntaria de estudiantes',
                data: values.length ? values : [0],
                borderColor: '#38bdf8',
                backgroundColor: 'rgba(56, 189, 248, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#38bdf8'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#9ca3af' } },
                x: { grid: { display: false }, ticks: { color: '#9ca3af' } }
            }
        }
    });
    state.charts['voluntarios'] = chart;
}

// Chart 2.5: Comparación de Horas de Utilización por Año
function renderComparativaAnualChart(data = []) {
    prepareChartCanvas('comparativa_anual');
    const ctx = document.getElementById('chart-comparativa-anual').getContext('2d');
    
    const sorted = [...data].sort((a, b) => parseInt(a.year) - parseInt(b.year));
    const labels = sorted.map(d => d.year);
    const values = sorted.map(d => parseFloat(d.total_hours));

    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['Sin datos'],
            datasets: [{
                label: 'Horas de navegación',
                data: values.length ? values : [0],
                backgroundColor: 'rgba(56, 189, 248, 0.6)',
                borderColor: '#38bdf8',
                borderWidth: 1.5,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#9ca3af' } },
                x: { grid: { display: false }, ticks: { color: '#9ca3af' } }
            }
        }
    });
    state.charts['comparativa_anual'] = chart;
}


// Chart 3: Asignaturas
function renderAsignaturasChart(data = []) {
    prepareChartCanvas('asignaturas');
    const ctx = document.getElementById('chart-asignaturas').getContext('2d');
    
    const labels = data.map(d => d.asignatura);
    const values = data.map(d => d.total_hours);

    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels.length ? labels : ['Sin datos'],
            datasets: [{
                data: values.length ? values : [1],
                backgroundColor: [
                    '#e26e3a', '#38bdf8', '#c85725', '#5b708b', '#facc15',
                    '#1e293b', '#8ab4f8', '#f87171', '#4b5563', '#9ca3af'
                ],
                borderWidth: 1,
                borderColor: '#121620'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: '#9ca3af', font: { size: 10 } }
                }
            }
        }
    });
    state.charts['asignaturas'] = chart;
}

// Chart 4: Externo Ingresos
function renderExternosIngresosChart(data = []) {
    prepareChartCanvas('ingresos');
    const ctx = document.getElementById('chart-ingresos').getContext('2d');
    
    const sorted = [...data].reverse();
    const labels = sorted.map(d => formatMonthLetters(d.month, d.year));
    const values = sorted.map(d => d.total_revenue);

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['Sin datos'],
            datasets: [{
                label: 'Ingresos ($)',
                data: values.length ? values : [0],
                borderColor: '#38bdf8',
                backgroundColor: 'rgba(56, 189, 248, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#38bdf8'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#9ca3af', callback: val => formatCLP(val) } },
                x: { grid: { display: false }, ticks: { color: '#9ca3af' } }
            }
        }
    });
    state.charts['ingresos'] = chart;
}

// Load List/Table Data
async function loadTableData(type) {
    const p = state.pagination[type];
    const s = state.search[type];
    
    try {
        const res = await fetch(`api.php?action=list_data&area=${state.currentArea}&type=${type}&page=${p.page}&limit=${p.limit}&search=${encodeURIComponent(s)}&year=${state.currentYear}`);
        const data = await res.json();
        
        if (!data.success) {
            showToast(data.error || 'Error al listar datos', 'danger');
            return;
        }

        // Render Table Body
        renderTableBody(type, data.records);

        // Update Pagination Controls
        p.pages = data.pages;
        renderPagination(type, data.total, p.page, data.pages);

    } catch (e) {
        showToast('Error de conexión al cargar la tabla', 'danger');
        console.error(e);
    }
}

// Render Table Rows Dynamic
function renderTableBody(type, records = []) {
    const tbody = document.querySelector(`#table-${type} tbody`);
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (records.length === 0) {
        const colCount = tbody.closest('table').querySelectorAll('th').length;
        tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align: center; color: var(--text-muted);">No hay registros disponibles.</td></tr>`;
        return;
    }

    records.forEach(r => {
        const tr = document.createElement('tr');
        tr.dataset.id = r.id;
        
        // Highlight weekends and holidays
        const dateInfo = getDateType(r.fecha);
        if (dateInfo) {
            if (dateInfo.type === 'holiday') {
                tr.classList.add('row-holiday');
            } else if (dateInfo.type === 'weekend') {
                tr.classList.add('row-weekend');
            }
        }
        
        let dateBadgeHtml = '';
        if (dateInfo) {
            const badgeClass = dateInfo.type === 'holiday' ? 'badge-holiday' : 'badge-weekend';
            dateBadgeHtml = `<br><span class="date-badge ${badgeClass}" title="${dateInfo.label}">${dateInfo.type === 'holiday' ? 'Festivo' : dateInfo.label}</span>`;
        }
        
        let colsHtml = '';
        if (type === 'uso') {
            colsHtml = `
                <td data-fecha="${r.fecha}">${formatDateDMY(r.fecha)}${dateBadgeHtml}</td>
                <td>${escapeHTML(r.curso) || '-'}</td>
                <td>${escapeHTML(r.asignatura) || '-'}</td>
                <td>${r.cantidad_alumnos || 0}</td>
                <td><span class="badge badge-neutral">${escapeHTML(r.categoria) || '-'}</span></td>
                <td>${escapeHTML(r.comex) || '-'}</td>
                <td>${escapeHTML(r.finex) || '-'}</td>
                <td>${r.cantidad_horas} hrs</td>
                <td>${escapeHTML(r.observaciones) || ''}</td>
            `;
        } else if (type === 'voluntario') {
            colsHtml = `
                <td data-fecha="${r.fecha}">${formatDateDMY(r.fecha)}${dateBadgeHtml}</td>
                <td>${escapeHTML(r.nombre_estudiante)}</td>
                <td>${escapeHTML(r.curso) || '-'}</td>
                <td>${escapeHTML(r.asignatura) || '-'}</td>
                <td>${escapeHTML(r.tema) || '-'}</td>
                <td>${escapeHTML(r.comex) || '-'}</td>
                <td>${escapeHTML(r.finex) || '-'}</td>
                <td>${r.horas} hrs</td>
            `;
        } else if (type === 'externo') {
            const badgeClass = r.examen_cimar === 'Aprobado' ? 'badge-success' : (r.examen_cimar === 'Reprobado' ? 'badge-danger' : 'badge-warning');
            colsHtml = `
                <td data-fecha="${r.fecha}">${formatDateDMY(r.fecha)}${dateBadgeHtml}</td>
                <td>${escapeHTML(r.nombre_alumno)}</td>
                <td>${escapeHTML(r.run) || '-'}</td>
                <td>${escapeHTML(r.telefono) || '-'}</td>
                <td>${escapeHTML(r.email) || '-'}</td>
                <td>${r.cantidad_horas} hrs</td>
                <td>${escapeHTML(r.objeto_entrenamiento) || '-'}</td>
                <td>${escapeHTML(r.procedencia) || '-'}</td>
                <td>${formatCLP(r.monto_cancelado || 0)}</td>
                <td><span class="badge ${badgeClass}">${escapeHTML(r.examen_cimar) || 'Pendiente'}</span></td>
            `;
        }

        // Action buttons (Edit & Delete)
        colsHtml += `
            <td class="td-actions">
                <button class="action-icon-btn edit" onclick="openEditModal('${type}', ${r.id})">
                    <i aria-hidden="true" data-lucide="edit-2"></i>
                </button>
                <button class="action-icon-btn delete" onclick="deleteRecord('${type}', ${r.id})">
                    <i aria-hidden="true" data-lucide="trash-2"></i>
                </button>
            </td>
        `;

        tr.innerHTML = colsHtml;
        tbody.appendChild(tr);
    });

    // Create Lucide Icons for action buttons
    lucide.createIcons();
}

// Render Pagination
function renderPagination(type, total, currentPage, totalPages) {
    const el = document.getElementById(`pagination-${type}`);
    if (!el) return;

    const p = state.pagination[type];
    const start = total === 0 ? 0 : (currentPage - 1) * p.limit + 1;
    const end = Math.min(currentPage * p.limit, total);

    el.innerHTML = `
        <div>Mostrando ${start} - ${end} de ${total} registros</div>
        <div class="pagination-controls">
            <button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage('${type}', ${currentPage - 1})">
                <i aria-hidden="true" data-lucide="chevron-left"></i>
            </button>
            <span style="display: flex; align-items: center; padding: 0 0.5rem; font-weight: 500;">Pág ${currentPage} de ${totalPages || 1}</span>
            <button class="pagination-btn" ${currentPage >= totalPages ? 'disabled' : ''} onclick="changePage('${type}', ${currentPage + 1})">
                <i aria-hidden="true" data-lucide="chevron-right"></i>
            </button>
        </div>
    `;
    lucide.createIcons();
}

// Helper to safely set select value (and append option dynamically if it does not exist)
function setSelectValueSafely(selectEl, value) {
    if (!selectEl) return;
    let exists = false;
    for (let i = 0; i < selectEl.options.length; i++) {
        if (selectEl.options[i].value === value) {
            exists = true;
            break;
        }
    }
    if (!exists && value) {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = value;
        selectEl.appendChild(opt);
    }
    selectEl.value = value || '';
}

// Load Cursos and Asignaturas list for current area
async function loadAreaParameters() {
    try {
        const res = await fetch(`api.php?action=get_params&area=${state.currentArea}`);
        const data = await res.json();
        if (data.success) {
            areaParams.cursos = data.cursos || [];
            areaParams.asignaturas = data.asignaturas || [];
            areaParams.alumnos_externos = data.alumnos_externos || [];
            
            // Populate config view
            renderConfigParams();
            // Populate forms select dropdowns
            populateModalDropdowns();
            // Populate student datalist for autocomplete
            populateStudentDatalist(data.estudiantes || []);
            // Populate external student datalist for autocomplete
            populateExternoDatalist(areaParams.alumnos_externos);
        }
    } catch (e) {
        console.error("Error al cargar parámetros del área", e);
    }
}

// Render Configuration Lists
async function renderConfigParams() {
    const cursosList = document.getElementById('config-cursos-list');
    const asignaturasList = document.getElementById('config-asignaturas-list');
    if (!cursosList || !asignaturasList) return;

    cursosList.innerHTML = '';
    if (areaParams.cursos.length === 0) {
        cursosList.innerHTML = '<li class="ranking-item"><span class="ranking-name" style="color: var(--text-muted);">Sin cursos registrados</span></li>';
    } else {
        areaParams.cursos.forEach(c => {
            const li = document.createElement('li');
            li.className = 'ranking-item';
            li.innerHTML = `
                <span class="ranking-name">${escapeHTML(c.nombre)}</span>
                <button class="action-icon-btn delete" onclick="deleteParameter('curso', ${c.id})">
                    <i aria-hidden="true" data-lucide="trash-2"></i>
                </button>
            `;
            cursosList.appendChild(li);
        });
    }

    asignaturasList.innerHTML = '';
    if (areaParams.asignaturas.length === 0) {
        asignaturasList.innerHTML = '<li class="ranking-item"><span class="ranking-name" style="color: var(--text-muted);">Sin asignaturas registradas</span></li>';
    } else {
        areaParams.asignaturas.forEach(a => {
            const li = document.createElement('li');
            li.className = 'ranking-item';
            li.innerHTML = `
                <span class="ranking-name">${escapeHTML(a.nombre)}</span>
                <button class="action-icon-btn delete" onclick="deleteParameter('asignatura', ${a.id})">
                    <i aria-hidden="true" data-lucide="trash-2"></i>
                </button>
            `;
            asignaturasList.appendChild(li);
        });
    }

    // Fetch and render yearly config
    const configList = document.getElementById('config-anual-list');
    if (configList) {
        configList.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 0.75rem;">Cargando...</td></tr>';
        try {
            const res = await fetch('api.php?action=get_config');
            const data = await res.json();
            if (data.success && data.configs) {
                state.yearlyConfigs = data.configs;
                configList.innerHTML = '';
                if (data.configs.length === 0) {
                    configList.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 0.75rem;">Sin configuraciones registradas</td></tr>';
                } else {
                    data.configs.forEach(cfg => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid var(--border-color)';
                        tr.style.color = 'var(--text-primary)';
                        const dias_sin_clases_text = parseInt(cfg.dias_sin_clases) > 0 ? `${cfg.dias_sin_clases} días` : '<span style="color:var(--text-secondary); font-style:italic;">0</span>';
                        tr.innerHTML = `
                            <td style="padding: 0.75rem;"><strong>${cfg.year}</strong></td>
                            <td style="padding: 0.75rem;">${cfg.fecha_inicio}</td>
                            <td style="padding: 0.75rem;">${cfg.fecha_fin}</td>
                            <td style="padding: 0.75rem;">${cfg.horas_diarias} hrs/día</td>
                            <td style="padding: 0.75rem;">${dias_sin_clases_text}</td>
                        `;
                        configList.appendChild(tr);
                    });
                }
            }
        } catch (err) {
            console.error("Error al cargar configuraciones anuales", err);
            configList.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--danger); padding: 0.75rem;">Error al cargar</td></tr>';
        }
    }
    lucide.createIcons();
}

// Populate Modal Drops Selects
function populateModalDropdowns() {
    const cursoSelects = document.querySelectorAll('select[name="curso"]');
    const asignaturaSelects = document.querySelectorAll('select[name="asignatura"]');

    cursoSelects.forEach(select => {
        select.innerHTML = '<option value="">Seleccione...</option>';
        areaParams.cursos.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.nombre;
            opt.textContent = c.nombre;
            select.appendChild(opt);
        });
    });

    asignaturaSelects.forEach(select => {
        select.innerHTML = '<option value="">Seleccione...</option>';
        areaParams.asignaturas.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.nombre;
            opt.textContent = a.nombre;
            select.appendChild(opt);
        });
    });
}

// Populate student datalist for autocomplete
function populateStudentDatalist(students) {
    const datalist = document.getElementById('student-names');
    if (!datalist) return;
    datalist.innerHTML = '';
    students.forEach(student => {
        const option = document.createElement('option');
        option.value = student;
        datalist.appendChild(option);
    });
}

// Populate external student datalist for autocomplete
function populateExternoDatalist(alumnos) {
    const datalist = document.getElementById('externo-names');
    if (!datalist) return;
    datalist.innerHTML = '';
    alumnos.forEach(alumno => {
        const option = document.createElement('option');
        option.value = alumno.nombre_alumno;
        datalist.appendChild(option);
    });
}

// Add Parameter Call
async function addParameter(type, nombre) {
    try {
        const res = await fetch(`api.php?action=create_param&area=${state.currentArea}&type=${type}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre })
        });
        const data = await handleFetchResponse(res);
        if (data.success) {
            showToast(`${type === 'curso' ? 'Curso' : 'Asignatura'} añadido correctamente`, 'success');
            await loadAreaParameters();
        } else {
            showToast(data.error || 'Error al añadir parámetro', 'danger');
        }
    } catch (e) {
        if (e.message !== 'No autorizado') {
            showToast('Error de red', 'danger');
            console.error(e);
        }
    }
}

// Delete Parameter Call
async function deleteParameter(type, id) {
    if (!confirm(`¿Está seguro de que desea eliminar este ${type === 'curso' ? 'curso' : 'asignatura'}?`)) return;

    try {
        const res = await fetch(`api.php?action=delete_param&area=${state.currentArea}&type=${type}&id=${id}`, {
            method: 'POST'
        });
        const data = await handleFetchResponse(res);
        if (data.success) {
            showToast('Parámetro eliminado con éxito', 'success');
            await loadAreaParameters();
        } else {
            showToast(data.error || 'Error al eliminar el parámetro', 'danger');
        }
    } catch (e) {
        if (e.message !== 'No autorizado') {
            showToast('Error de red', 'danger');
            console.error(e);
        }
    }
}

// Change Page handler
function changePage(type, newPage) {
    state.pagination[type].page = newPage;
    loadTableData(type);
}

// Open Modal for Create (New)
function openCreateModal(type) {
    const modal = document.getElementById(`modal-${type}`);
    const form = document.getElementById(`form-${type}`);
    if (!modal || !form) return;

    form.reset();
    
    // Set default date
    const dateInput = form.querySelector('[name="fecha"]');
    if (dateInput) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    form.dataset.action = 'create';
    form.dataset.id = '';
    
    modal.querySelector('.modal-title').textContent = 'Añadir Nuevo Registro';
    modal.classList.add('active');
}

// Open Modal for Edit
async function openEditModal(type, id) {
    const modal = document.getElementById(`modal-${type}`);
    const form = document.getElementById(`form-${type}`);
    if (!modal || !form) return;

    const row = document.querySelector(`#table-${type} tr[data-id="${id}"]`);
    if (!row) return;

    try {
        form.dataset.action = 'update';
        form.dataset.id = id;
        
        // Form mapping
        if (type === 'uso') {
            const cells = row.querySelectorAll('td');
            form.querySelector('[name="fecha"]').value = cells[0].dataset.fecha || cells[0].textContent;
            setSelectValueSafely(form.querySelector('[name="curso"]'), cells[1].textContent === '-' ? '' : cells[1].textContent);
            setSelectValueSafely(form.querySelector('[name="asignatura"]'), cells[2].textContent === '-' ? '' : cells[2].textContent);
            form.querySelector('[name="cantidad_alumnos"]').value = parseInt(cells[3].textContent);
            setSelectValueSafely(form.querySelector('[name="categoria"]'), cells[4].textContent === '-' ? '' : cells[4].textContent);
            
            form.querySelector('[name="comex"]').value = cells[5].textContent === '-' ? '' : cells[5].textContent;
            form.querySelector('[name="finex"]').value = cells[6].textContent === '-' ? '' : cells[6].textContent;
            form.querySelector('[name="cantidad_horas"]').value = parseFloat(cells[7].textContent);
            form.querySelector('[name="observaciones"]').value = cells[8].textContent;
            
        } else if (type === 'voluntario') {
            const cells = row.querySelectorAll('td');
            form.querySelector('[name="fecha"]').value = cells[0].dataset.fecha || cells[0].textContent;
            form.querySelector('[name="nombre_estudiante"]').value = cells[1].textContent;
            setSelectValueSafely(form.querySelector('[name="curso"]'), cells[2].textContent === '-' ? '' : cells[2].textContent);
            setSelectValueSafely(form.querySelector('[name="asignatura"]'), cells[3].textContent === '-' ? '' : cells[3].textContent);
            form.querySelector('[name="tema"]').value = cells[4].textContent === '-' ? '' : cells[4].textContent;
            form.querySelector('[name="comex"]').value = cells[5].textContent === '-' ? '' : cells[5].textContent;
            form.querySelector('[name="finex"]').value = cells[6].textContent === '-' ? '' : cells[6].textContent;
            form.querySelector('[name="horas"]').value = parseFloat(cells[7].textContent);
            
        } else if (type === 'externo') {
            const cells = row.querySelectorAll('td');
            form.querySelector('[name="fecha"]').value = cells[0].dataset.fecha || cells[0].textContent;
            form.querySelector('[name="nombre_alumno"]').value = cells[1].textContent;
            form.querySelector('[name="run"]').value = cells[2].textContent === '-' ? '' : cells[2].textContent;
            form.querySelector('[name="telefono"]').value = cells[3].textContent === '-' ? '' : cells[3].textContent;
            form.querySelector('[name="email"]').value = cells[4].textContent === '-' ? '' : cells[4].textContent;
            form.querySelector('[name="cantidad_horas"]').value = parseFloat(cells[5].textContent);
            form.querySelector('[name="objeto_entrenamiento"]').value = cells[6].textContent === '-' ? '' : cells[6].textContent;
            form.querySelector('[name="procedencia"]').value = cells[7].textContent === '-' ? '' : cells[7].textContent;
            
            // Clean currency string to number
            const val = cells[8].textContent.replace(/[^0-9]/g, '');
            form.querySelector('[name="monto_cancelado"]').value = val ? parseFloat(val) : 0;
            
            const examState = cells[9].textContent.trim();
            form.querySelector('[name="examen_cimar"]').value = examState;
        }

        modal.querySelector('.modal-title').textContent = 'Editar Registro';
        modal.classList.add('active');
    } catch (e) {
        showToast('Error al preparar edición', 'danger');
        console.error(e);
    }
}

// Close Modal
function closeModal(type) {
    const modal = document.getElementById(`modal-${type}`);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Submit Form Handler
function setupFormSubmit(formId, type) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const action = form.dataset.action;
        const id = form.dataset.id;
        
        // Serialize form to object
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        // Endpoint
        let url = `api.php?action=${action}&area=${state.currentArea}&type=${type}`;
        if (action === 'update') {
            url += `&id=${id}`;
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await handleFetchResponse(res);

            if (result.success) {
                showToast(action === 'create' ? 'Registro añadido correctamente' : 'Registro actualizado correctamente', 'success');
                closeModal(type);
                refreshCurrentView();
            } else {
                showToast(result.error || 'Error al guardar los datos', 'danger');
            }
        } catch (e) {
            if (e.message !== 'No autorizado') {
                showToast('Error de red al guardar el registro', 'danger');
                console.error(e);
            }
        }
    });
}

// Delete Record Action
async function deleteRecord(type, id) {
    if (!confirm('¿Está seguro de que desea eliminar este registro?')) return;

    try {
        const res = await fetch(`api.php?action=delete&area=${state.currentArea}&type=${type}&id=${id}`, {
            method: 'POST'
        });
        const result = await handleFetchResponse(res);

        if (result.success) {
            showToast('Registro eliminado con éxito', 'success');
            refreshCurrentView();
        } else {
            showToast(result.error || 'Error al eliminar el registro', 'danger');
        }
    } catch (e) {
        if (e.message !== 'No autorizado') {
            showToast('Error de red al eliminar el registro', 'danger');
            console.error(e);
        }
    }
}

// Export data to Excel/CSV
function exportData(type) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const area = state.currentArea;
    const year = state.currentYear;
    
    window.location.href = `api.php?action=export_excel&type=${type}&area=${area}&year=${year}&csrf_token=${token}`;
}
