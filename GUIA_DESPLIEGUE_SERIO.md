# Guía de Despliegue: RD WATCH V2

Esta guía contiene los puntos críticos que debes revisar para asegurar que el sistema no se "totee" al moverlo de servidor.

## 1. Requerimientos del Servidor
Asegúrate de que el nuevo servidor cumpla con:
- **PHP 7.4 o superior** (Se recomienda 8.1+).
- **Extensiones de PHP**: `php-pdo`, `php-pgsql`, `php-mbstring`, `php-json`.
- **Base de Datos**: PostgreSQL instalado y corriendo.
- **Servidor Web**: Apache (con mod_rewrite activado) o Nginx.

## 2. Configuración Backend (`src/backend/.env`)
Es el punto más importante. Debes crear/editar este archivo con los datos del nuevo servidor:
```ini
DB_HOST=tu_nuevo_host (ej: localhost o una IP)
DB_PORT=5432
DB_NAME=nombre_db
DB_USER=usuario_db
DB_PASS=contraseña_db
```

## 3. Ajustes de Seguridad en `src/backend/config.php`
Cuando pases a un servidor de producción (con dominio real y HTTPS), busca estas líneas y ajústalas:

### Sesiones Seguras
```php
// Cambia a 1 cuando tengas certificado SSL (HTTPS)
ini_set('session.cookie_secure', 0); // <-- Cambiar a 1 en producción
```

### CORS (Control de Acceso)
Actualmente el código permite cualquier origen:
```php
header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
```
> [!TIP]
> Por seguridad, es mejor que en producción pongas tu dominio exacto:
> `header("Access-Control-Allow-Origin: https://tu-relojeria.com");`

## 4. Frontend y URLs (`src/js/config.js`)
El sistema está diseñado para ser portátil:
- Si instalas el proyecto en la raíz del servidor (ej: `https://tosite.com/`), funcionará solo.
- Si lo instalas en una carpeta (ej: `https://tosite.com/app/`), la lógica dinámica en `config.js` debería detectarlo.

**Verificación rápida**: Abre la consola del navegador (F12) y escribe `API_CONFIG.baseUrl`. Debe mostrar la ruta correcta hacia tus archivos PHP.

## 5. Permisos en Linux (¡Muy Importante!)
Al pasar de Windows a Linux, es normal que te salga "Forbidden" (Error 403) porque en Linux los archivos tienen dueños y permisos estrictos.

### El Usuario del Servidor
El servidor (Apache o Nginx) suele correr bajo el usuario `www-data`. Si los archivos son de tu usuario personal, el servidor no puede leerlos.

**Ejecuta estos comandos en la terminal de tu Linux:**
1. **Cambiar el dueño**:
   `sudo chown -R www-data:www-data /var/www/html/rd_watch_V2`
2. **Permisos para carpetas** (permite entrar a ellas):
   `find /var/www/html/rd_watch_V2 -type d -exec chmod 755 {} \;`
3. **Permisos para archivos** (permite leerlos):
   `find /var/www/html/rd_watch_V2 -type f -exec chmod 644 {} \;`

## 6. ¿Cómo evitar problemas de permisos?
Si quieres que el proyecto sea "conectar y listo" sin pelear con `chmod`, tienes estas opciones:

### Opción A: Servidor Interno de PHP (Solo Desarrollo)
Si solo quieres probarlo en tu Linux personal, corre este comando dentro de la carpeta del proyecto:
`php -S localhost:8000`
Como tú inicias el proceso, PHP tiene exactamente tus mismos permisos y **nada saldrá Forbidden**.

### Opción B: Docker (La solución profesional)
Podemos crear un archivo `docker-compose.yml`. Esto crea una "burbuja" donde las dependencias y permisos ya están configurados. Así, el proyecto correrá igual en Windows, Linux o cualquier servidor sin tocar una sola carpeta.

## 7. Almacenamiento y Archivos
- **Sin Carpetas de Uploads**: El sistema guarda los comprobantes de pago como datos binarios en la DB. ¡Esto te ahorra muchos dolores de cabeza con permisos de escritura!
- **.htaccess**: Si usas **Nginx**, el archivo `.htaccess` no tendrá efecto. Deberás configurar manualmente el bloqueo de archivos `.env` y el listado de directorios en tu archivo de configuración de Nginx.

## 6. Base de Datos (SQL)
No olvides ejecutar los scripts de la carpeta `sql/` en el nuevo servidor.
- Importar primero `schema.sql` (o el equivalente de creación de tablas).
- Validar las secuencias de PostgreSQL si vas a migrar datos existentes.

## 7. Stripe (Si se habilita)
Si decides usar Stripe en el futuro:
- Cambia las claves de prueba (`pk_test_...`) por las de producción (`pk_live_...`) en tu panel de administración o `.env`.

> [!IMPORTANT]
> **Prueba de Fuego**: Después de subir todo, intenta crear un usuario nuevo y realizar una compra con un comprobante falso. Si esto funciona, el 99% del sistema está configurado correctamente.
