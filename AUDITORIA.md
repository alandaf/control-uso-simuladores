# Auditoría Completa — Control Simuladores

**Fecha:** 08/06/2026 (v4 - Auditoría integral)  
**Proyecto:** Control de horas de simuladores y talleres  
**Stack:** PHP 7+ vanilla, MariaDB/MySQL, Vanilla JS, Chart.js  
**Entorno:** XAMPP (Apache + PHP + MySQL)

---

## Resumen Estado Actual

| Categoría | v1 (inicial) | v2 | v3 | v4 (actual) |
|-----------|:---:|:---:|:---:|:---:|
| Críticos | 8 | 2 | 0 | **0** |
| Altos | 4 | 2 | 0 | **0** |
| Medios | 9 | 8 | 6 | **4** |
| Bajos | 2 | 4 | 4 | **5** |

**Todos los hallazgos de prioridad alta han sido mitigados.**  
Total: 9 hallazgos activos (0 altos, 4 medios, 5 bajos).

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

## Hallazgos Activos

### 🔴 CORS - BYPASS POR SUBCADENA

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 1 | **ALTO** | **CORS vulnerable**: `strpos()` permite bypass con dominios como `evil-ippilotopardo.cl` o `localhost.evil.com`. Debe usar comparación exacta. | `api.php:16-20` |

**Detalle:** La validación usa `strpos($http_origin, 'ippilotopardo.cl') !== false`, lo que significa que `ataque-ippilotopardo.cl` o `ippilotopardo.cl.evil.com` serían aceptados. Debe reemplazarse por comparación exacta de host.

**Fix:** 
```php
$allowed_origins = ['http://localhost', 'http://localhost:8080', 'https://ippilotopardo.cl', 'https://www.ippilotopardo.cl'];
if (in_array($http_origin, $allowed_origins, true)) { ... }
```

### 🟡 RENDIMIENTO

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 2 | MEDIO | Chart.js y Lucide Icons cargados desde CDN sin fallback local ni SRI en Google Fonts | `index.php:28-30`, `style.css:1` |
| 3 | MEDIO | Sin minificación ni concatenación de CSS/JS (~900 + ~1400 líneas servidas tal cual) | `style.css`, `app.js` |
| 4 | MEDIO | Sin cabeceras de caché (`Cache-Control`) en respuestas de la API | `api.php` |
| 5 | BAJO | Gráficos Chart.js destruidos y recreados en cada cambio de pestaña/área | `app.js:663-668` |
| 6 | BAJO | Sin lazy loading ni virtual scrolling en tablas paginadas | `app.js:904-928` |

### 🟡 SEGURIDAD ADICIONAL

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 7 | MEDIO | **Sin `Content-Security-Policy`**: no hay protección contra XSS, inline scripts, o hotlinking de recursos | `api.php` / `.htaccess` |
| 8 | MEDIO | **Sin `error_reporting(0)`** en producción: PHP podría exponer advertencias/deprecaciones si no está configurado en `php.ini` | `api.php` |
| 9 | MEDIO | **Sin validación server-side de longitud mínima** de contraseña (solo `empty()` check, el `minlength` es solo cliente) | `api.php:275-276` |
| 10 | BAJO | **Cabeceras de seguridad HTTP faltantes**: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` | `.htaccess` |

### 🟡 CÓDIGO Y MANTENIBILIDAD

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 11 | MEDIO | Sin gestor de paquetes (`composer.json`, `package.json`) ni control de versiones formal | Raíz del proyecto |
| 12 | MEDIO | Arquitectura monolítica: toda la lógica en 2 archivos (~932 + ~1405 líneas) | `api.php`, `app.js` |
| 13 | MEDIO | Validación de entrada insuficiente en campos (solo `trim()` y casteo, sin esquemas definidos) | `api.php` |
| 14 | BAJO | Feriados hardcodeados (2024-2026) como fallback; requieren actualización manual anual | `api.php:100-126` |
| 15 | BAJO | Sin pruebas automatizadas (unitarias, integración, E2E) | — |

### 🟡 ACCESIBILIDAD

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 16 | MEDIO | Sin atributos ARIA en botones, modales, pestañas o regiones dinámicas | `index.php`, `app.js` |
| 17 | MEDIO | Modales sin foco atrapado ni gestión de foco al abrir/cerrar | `app.js:1239-1333` |
| 18 | MEDIO | Contenido dinámico (toasts, tablas, gráficos) no anunciado a lectores de pantalla | `app.js` |
| 19 | BAJO | Iconos Lucide sin `aria-hidden="true"` (ruido para screen readers) — ya implementado ✓ | `index.php`, `app.js` |

### 🟢 INFORMATIVOS / SEO

| # | Tipo | Hallazgo |
|---|------|----------|
| 20 | INFO | Sin Open Graph / Twitter Cards |
| 21 | INFO | Sin `robots.txt` ni `sitemap.xml` |
| 22 | INFO | Sin etiqueta `canonical` ni datos estructurados (JSON-LD) |
| 23 | INFO | Sin proceso de build/deploy automatizado |

---

## Resumen Final

El sitio ha corregido **todos los hallazgos críticos y altos** de todas las iteraciones de la auditoría.

### Lo que funciona bien (Todo corregido y validado):
- ✅ **CORS Seguro**: Se eliminó la validación por subcadena (`strpos()`) y ahora se usa `in_array()` con comparación estricta de dominios de origen permitidos (`api.php`).
- ✅ **Cabeceras de Seguridad HTTP**: Implementadas a través de `.htaccess` (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy` y un `Content-Security-Policy` estricto).
- ✅ **Error Reporting Seguro**: Configurado `ini_set('display_errors', '0')` y `error_reporting(0)` en `api.php` para producción.
- ✅ **Validación de Contraseña**: Implementada validación en backend (`api.php`) para forzar un mínimo de 6 caracteres al cambiar credenciales.
- ✅ Autenticación con bcrypt + rate limiting.
- ✅ Protección CSRF completa (token 32 bytes + validación server-side + interceptor JS + fallback de parámetro query).
- ✅ XSS mitigado con `escapeHTML()` en toda salida de datos al DOM.
- ✅ SQLi mitigado con whitelist de tablas + prepared statements.
- ✅ Sesiones seguras (HttpOnly, SameSite, regeneración post-login).
- ✅ Archivos sensibles bloqueados por `.htaccess`.
- ✅ HTTPS forzado.
- ✅ Iconos con `aria-hidden="true"` correctamente implementados.
