# Procedimiento de Recuperación de Clave de Administrador

Este documento describe el procedimiento de emergencia para recuperar el acceso al panel administrativo en caso de olvidar el usuario o la contraseña almacenados en la base de datos MySQL/MariaDB.

---

## Mecanismo de Emergencia (Bypass por Variables de Entorno)

La API cuenta con una validación de respaldo (*fallback*). Si las credenciales ingresadas no coinciden con las de la base de datos, el sistema verificará si coinciden con los valores maestros definidos en el archivo de configuración `.env` del servidor. 

Dado que el archivo `.env` solo es accesible por personas con acceso directo a los archivos del servidor (vía FTP, cPanel o SSH), este método es completamente seguro y actúa como llave maestra de recuperación.

---

## Paso a Paso para Recuperar el Acceso

### Paso 1: Generar el Hash de una nueva contraseña temporal
Para almacenar la contraseña de forma segura, primero debemos encriptarla usando el algoritmo BCRYPT de PHP.
1. Si tienes acceso a una terminal con PHP (local o servidor), ejecuta el siguiente comando:
   ```bash
   php -r "echo password_hash('TuNuevaContraseñaTemporal', PASSWORD_BCRYPT);"
   ```
2. Si no tienes terminal PHP a mano, puedes usar este hash de ejemplo que corresponde a la contraseña **`Recuperar2026`**:
   ```text
   $2y$10$d/fUX50K4FskR2K5G4tCdu.yK9bU.0ZtZ6/c.nE4H6kGgSjD0/sDq
   ```

### Paso 2: Configurar las variables en el archivo `.env`
1. Conéctate a tu servidor web (cPanel, FTP, etc.) o entra a tu carpeta local.
2. Abre el archivo **`.env`** con un editor de texto.
3. Añade o edita las siguientes líneas al final del archivo con tu usuario de emergencia y el hash que obtuviste en el paso anterior:
   ```ini
   ADMIN_USER=recuperacion
   ADMIN_PASSWORD_HASH=$2y$10$d/fUX50K4FskR2K5G4tCdu.yK9bU.0ZtZ6/c.nE4H6kGgSjD0/sDq
   ```
4. Guarda los cambios.

### Paso 3: Iniciar Sesión en la Web
1. Ve al sitio web en tu navegador y haz clic en **Ingresar**.
2. Escribe las credenciales temporales configuradas en el archivo `.env`:
   * **Usuario**: `recuperacion`
   * **Contraseña**: `Recuperar2026` (o la que hayas generado).
3. El sistema te permitirá ingresar al panel administrativo.

### Paso 4: Cambiar la contraseña definitiva en la Base de Datos
1. Una vez dentro, ve a la pestaña **Parámetros**.
2. Desplázate hasta la sección **Gestión de Acceso Administrativo**.
3. Rellena el formulario con el usuario y la contraseña definitivos que deseas utilizar de ahora en adelante.
4. Haz clic en **Actualizar Accesos**. El sistema guardará de forma segura el nuevo hash en la base de datos y cerrará tu sesión automáticamente.

### Paso 5: Limpieza y Seguridad (Muy Importante)
Para evitar dejar credenciales maestras activas en el archivo plano, limpia el archivo de configuración:
1. Vuelve a abrir el archivo **`.env`** en el servidor.
2. **Elimina** o comenta las líneas `ADMIN_USER` y `ADMIN_PASSWORD_HASH` que agregaste en el Paso 2 (o cambia el password por otro hash aleatorio).
3. Guarda el archivo `.env`.

¡Listo! A partir de ese momento, el sistema solo permitirá ingresar con la nueva clave definitiva que acabas de guardar en la base de datos.
