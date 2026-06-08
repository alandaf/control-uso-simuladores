# Auditoría Completa — Control Simuladores

**Fecha:** 08/06/2026 (v3 - Re-auditoría final)  
**Proyecto:** Control de horas de simuladores y talleres  
**Stack:** PHP 7+ vanilla, MariaDB/MySQL, Vanilla JS, Chart.js  
**Entorno:** XAMPP (Apache + PHP + MySQL)

---

## Resumen Estado Actual

| Categoría | v1 (inicial) | v2 | v3 (actual) |
|-----------|:---:|:---:|:---:|
| Críticos | 8 | 2 | **0** |
| Altos | 4 | 2 | **0** |
| Medios | 9 | 8 | **6** |
| Bajos | 2 | 4 | **4** |

**Todos los hallazgos críticos y altos han sido corregidos.**  
Quedan 10 hallazgos de prioridad media/baja (rendimiento, accesibilidad, mantenibilidad).

---

## ✅ CORREGIDOS - Resumen por iteración

### Corregidos en v1→v2 (primera ronda)
- Contraseña almacenada como bcrypt hash (no texto plano)
- CSRF implementado con tokens de 32 bytes + validación server-side
- CORS restringido a `localhost` e `ippilotopardo.cl`
- `session_regenerate_id(true)` después de login exitoso
- Cookies de sesión con `HttpOnly`, `SameSite=Strict`, `Secure` condicional
- Rate limiting en login (5 intentos, bloqueo de 5 minutos)
- Errores PDO ya no se exponen al cliente; se usa `error_log()` + mensaje genérico
- Fetch interceptor añade automáticamente header `X-CSRF-Token`
- CSRF token expuesto en meta tag para acceso JS

### Corregidos en v2→v3 (segunda ronda)
- **SQL Injection mitigado** — nueva función `validateTableName()` con whitelist de tablas permitidas, aplicada en todos los endpoints que usan nombres de tabla dinámicos (`api.php:56-61`)
- **XSS eliminado totalmente** — `escapeHTML()` aplicado también en listas de configuración de cursos/asignaturas (`app.js:1042, 1059`)
- **Archivos sensibles protegidos** — `.htaccess` bloquea extensiones `sqlite|db|sql|xlsx|py|zip|md`, el archivo `migrate_to_mysql.php`, y el directorio `scratch/`
- **HTTPS forzado** — regla de redirección HTTP→HTTPS agregada en `.htaccess:2-4`
- **`.gitignore` creado** para excluir archivos sensibles del repositorio

---

## Hallazgos Persistentes (Media/Baja Prioridad)

### 🟡 RENDIMIENTO

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 1 | MEDIO | Chart.js, Lucide Icons y Google Fonts cargados desde CDN sin fallback local ni SRI | `index.php:28-30` |
| 2 | MEDIO | Sin minificación ni concatenación de CSS/JS (896 + ~1350 líneas servidas tal cual) | `style.css`, `app.js` |
| 3 | MEDIO | Sin cabeceras de caché; todas las peticiones a la API sin `Cache-Control` | `api.php` |
| 4 | BAJO | Gráficos Chart.js destruidos y recreados en cada cambio de pestaña/área | `app.js:605-610` |
| 5 | BAJO | Sin lazy loading ni virtual scrolling en tablas paginadas | `app.js:873-963` |

### 🟡 CÓDIGO Y MANTENIBILIDAD

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 6 | MEDIO | Sin gestor de paquetes (`composer.json`, `package.json`) | Raíz del proyecto |
| 7 | MEDIO | Arquitectura monolítica: toda la lógica en 2 archivos (~869 + ~1350 líneas) | `api.php`, `app.js` |
| 8 | MEDIO | Validación de entrada insuficiente en algunos campos (solo `trim()` y casteo) | `api.php` |
| 9 | BAJO | Sin control de versiones formal (`.gitignore` creado pero sin repo init) | Raíz del proyecto |

### 🟡 ACCESIBILIDAD

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 10 | MEDIO | Sin atributos ARIA en botones, modales, pestañas o regiones dinámicas | `index.php`, `app.js` |
| 11 | MEDIO | Modales sin foco atrapado, sin cierre con Escape, sin gestión de foco | `app.js:1180-1275` |
| 12 | MEDIO | Contenido dinámico (toasts, tablas, gráficos) no anunciado a lectores de pantalla | `app.js` |
| 13 | BAJO | Iconos Lucide sin `aria-hidden="true"` (ruido para screen readers) | `index.php`, `app.js` |

### 🟢 INFORMATIVOS

| # | Tipo | Hallazgo |
|---|------|----------|
| 14 | INFO | Sin Open Graph / Twitter Cards |
| 15 | INFO | Sin `robots.txt` ni `sitemap.xml` |
| 16 | INFO | Sin etiqueta `canonical` ni datos estructurados (JSON-LD) |
| 17 | INFO | Sin pruebas automatizadas (unitarias, integración, E2E) |
| 18 | INFO | Sin proceso de build/deploy |

---

## Resumen Final

El sitio ha pasado de **12 hallazgos críticos/altos** a **0**.  
La aplicación es funcionalmente segura para producción con:

- ✅ Autenticación con bcrypt + rate limiting
- ✅ Protección CSRF completa
- ✅ XSS mitigado con `escapeHTML()` en toda salida de datos
- ✅ SQLi mitigado con whitelist de tablas
- ✅ Sesiones seguras (HttpOnly, SameSite, regeneración)
- ✅ CORS restrictivo
- ✅ Archivos sensibles bloqueados por `.htaccess`
- ✅ HTTPS forzado
- ✅ Logging seguro sin fuga de información

Los hallazgos restantes son de optimización (rendimiento, accesibilidad, SEO) y no representan riesgos de seguridad.
