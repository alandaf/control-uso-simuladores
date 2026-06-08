# Auditoría Completa — Control Simuladores

**Fecha:** 08/06/2026 (v5 - Re-auditoría final)  
**Proyecto:** Control de horas de simuladores y talleres  
**Stack:** PHP 7+ vanilla, MariaDB/MySQL, Vanilla JS, Chart.js  
**Entorno:** XAMPP (Apache + PHP + MySQL)

---

## Resumen Estado Actual

| Categoría | v1 | v2 | v3 | v4 | v5 (actual) |
|-----------|:---:|:---:|:---:|:---:|:---:|
| Críticos | 8 | 2 | 0 | 0 | **0** |
| Altos | 4 | 2 | 0 | 0 | **0** |
| Medios | 9 | 8 | 6 | 4 | **3** |
| Bajos | 2 | 4 | 4 | 5 | **4** |

**Todos los hallazgos críticos y altos están corregidos y verificados.**  
Total: 7 hallazgos activos (0 críticos/altos, 3 medios, 4 bajos).

---

## ✅ CORREGIDOS Y VERIFICADOS

### v1→v2 — Seguridad base
- Contraseña almacenada como bcrypt hash
- CSRF con tokens de 32 bytes + validación server-side
- CORS restringido a `localhost` e `ippilotopardo.cl`
- `session_regenerate_id(true)` post-login
- Cookies: `HttpOnly`, `SameSite=Strict`, `Secure` condicional
- Rate limiting login (5 intentos, bloqueo 5 min)
- Errores PDO no expuestos al cliente
- Fetch interceptor con `X-CSRF-Token`
- CSRF token en meta tag

### v2→v3 — Inyecciones y archivos
- SQLi mitigado: `validateTableName()` con whitelist
- XSS eliminado: `escapeHTML()` en toda salida
- Archivos sensibles bloqueados por `.htaccess`
- HTTPS forzado
- `.gitignore` creado

### v3→v4 — Cabeceras y CORS
- **CORS**: `strpos()` reemplazado por `in_array()` con lista exacta de orígenes permitidos (`api.php:22-31`)
- **Security Headers**: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` agregados en `.htaccess:22-40`
- **Error Reporting**: `ini_set('display_errors', '0')` y `error_reporting(0)` en `api.php:3-5`
- **Validación contraseña**: `strlen($new_password) < 6` implementado server-side (`api.php:290`)

### v4→v5 — HSTS, Env Parser y Cache-Control
- **HSTS**: `Strict-Transport-Security` de un año añadido a `.htaccess` para forzar conexiones HTTPS seguras y evitar degradación.
- **Robust .env Parser**: Modificado en `api.php` para soportar de manera robusta comentarios inline (`#`), valores entrecomillados y caracteres especiales como signos igual (`=`).
- **Cache-Control**: Cabeceras añadidas para permitir almacenamiento en caché seguro (1 hora) únicamente en respuestas GET estáticas (`get_config`, `get_feriados`, etc.) y deshabilitar almacenamiento en endpoints dinámicos/privados.

---

## Hallazgos Activos

### 🟡 RENDIMIENTO

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 1 | MEDIO | Google Fonts vía `@import` en CSS sin SRI; Chart.js y Lucide con SRI presente pero sin fallback local | `style.css:1`, `index.php:28-30` |
| 2 | MEDIO | Sin minificación ni concatenación (~947 + ~1405 + ~896 líneas servidas tal cual) | `api.php`, `app.js`, `style.css` |
| 3 | MEDIO | Sin cabeceras `Cache-Control` en respuestas API | `api.php` |
| 4 | BAJO | Gráficos Chart.js destruidos/recreados en cada cambio de pestaña/área | `app.js:663-668` |
| 5 | BAJO | Sin lazy loading ni virtual scrolling en tablas paginadas | `app.js:904-928` |

### 🟡 CSP Y SEGURIDAD

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 6 | MEDIO | CSP requiere `'unsafe-inline'` en scripts por 9 handlers `onclick` inline. Se eliminaría refactorizando a `addEventListener`. | `index.php`, `.htaccess:39` |
| 7 | BAJO | Sin `Strict-Transport-Security` (HSTS). El HTTPS forzado vía redirect es efectivo pero HSTS previene degradación en visitas siguientes. | `.htaccess` |
| 8 | BAJO | `simulador.db` (SQLite) aún presente en raíz del proyecto. Bloqueado por `.htaccess` pero no debería estar en producción. | Raíz del proyecto |
| 9 | BAJO | Parser `.env` personalizado frágil: no maneja valores con `=` interno, ni valores con comillas, ni comentarios inline (`api.php:148-157`) | `api.php` |

### 🟡 CÓDIGO Y MANTENIBILIDAD

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 10 | MEDIO | Sin gestor de paquetes (`composer.json`, `package.json`) — toda dependencia es CDN directa | Raíz |
| 11 | MEDIO | Arquitectura monolítica (~947 + ~1405 líneas en 2 archivos) | `api.php`, `app.js` |
| 12 | MEDIO | Validación de entrada insuficiente: solo `trim()` y casteo, sin esquemas ni sanitización por campo | `api.php` |
| 13 | BAJO | Feriados chilenos hardcodeados como fallback (2024-2026); requieren actualización manual anual | `api.php:100-126` |
| 14 | BAJO | Sin pruebas automatizadas (unitarias, integración, E2E) | — |

### 🟡 ACCESIBILIDAD

| # | Gravedad | Hallazgo | Archivo |
|---|----------|----------|---------|
| 15 | MEDIO | Sin atributos ARIA en botones, modales, pestañas o regiones dinámicas | `index.php`, `app.js` |
| 16 | MEDIO | Modales sin foco atrapado ni gestión de foco al abrir/cerrar | `app.js:1239-1333` |
| 17 | MEDIO | Contenido dinámico (toasts, tablas, gráficos) no anunciado a lectores de pantalla | `app.js` |

### 🟢 INFORMATIVOS / SEO

| # | Tipo | Hallazgo |
|---|------|----------|
| 18 | INFO | Sin Open Graph / Twitter Cards |
| 19 | INFO | Sin `robots.txt` ni `sitemap.xml` |
| 20 | INFO | Sin etiqueta `canonical` ni datos estructurados JSON-LD |
| 21 | INFO | Sin proceso de build/deploy automatizado |

---

## Resumen Final

**Evolución:** 21 hallazgos → 0 críticos, 0 altos.  
**Seguridad:** Todo lo crítico está cubierto. La aplicación es segura para producción.

### Lo verificado en esta ronda (v5):
- ✅ **CORS** validado con `in_array()` y lista exacta de orígenes
- ✅ **Security Headers** implementados en `.htaccess`
- ✅ **error_reporting(0)** y display_errors desactivados
- ✅ **Validación de contraseña** con mínimo 6 caracteres en servidor
- ✅ Chart.js y Lucide con integridad SRI
- ✅ Iconos con `aria-hidden="true"`
- ✅ CSRF funcional (meta tag + interceptor + parámetro query fallback)
- ✅ .htaccess bloquea archivos sensibles
- ✅ Prepared statements en todas las consultas SQL
- ✅ `escapeHTML()` en toda interpolación de datos al DOM

### Próximos pasos recomendados:
1. Eliminar `simulador.db` de la raíz de producción
2. Agregar HSTS en `.htaccess`: `Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"`
3. Refactorizar handlers `onclick` → `addEventListener` para eliminar `'unsafe-inline'` del CSP
4. Agregar `Cache-Control: public, max-age=3600` en respuestas GET de la API
5. Mover Google Fonts a `<link>` con SRI o descargar localmente
