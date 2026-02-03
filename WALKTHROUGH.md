# Recorrido Técnico: RD Watch

Guía detallada sobre las nuevas funcionalidades y correcciones del sistema.

## Despliegue en Producción
El sistema está optimizado para funcionar en servidores estándar (Ubuntu Server / Apache / Nginx).

### Configuración del Servidor
1.  **Base de Datos**: Importar los esquemas y datos iniciales en PostgreSQL usando pgAdmin o línea de comandos.
    *   Base de datos: `db_rdwatch`
    *   Usuario de aplicación: `agomez`
2.  **Archivos**: Desplegar el contenido de `PROYECTO` en `/var/www/html`.
3.  **Configuración**: El sistema utiliza variables de entorno automatizadas. Asegúrese de que el archivo `.env` exista en la carpeta `backend/` con las credenciales correctas.

> [!NOTE]
> No se requieren scripts de inicio manuales (`.sh` o `.bat`). El servidor web gestionará las peticiones directamente.

---

### Embellecimiento Visual Integral (Senior UX/UI)
Se ha transformado la identidad visual de todo el sitio para proyectar un estándar de boutique de lujo.

> [!TIP]
> **Paleta Cromática**: Uso de `Oro Champán Mate` (#AF944F) y `Gris Carbón Profundo` (#0D0D0D) para una elegancia atemporal.
> **Legibilidad**: Fuente base aumentada a **17px** con interlineado generoso (1.7) para una lectura sin fatiga.

#### Secciones Rediseñadas:
- **Header**: Implementación de **Glassmorphism** y navegación minimalista.
- **Hero Section**: Tipografía majestuosa y animaciones de entrada suaves.
- **Servicios**: Tarjetas con elevación sutil y bordes oro refinados.
- **Galería & Testimonios**: Diseño limpio con enfoque en el contenido y sombras de tres niveles.
- **Contacto & Footer**: Modo oscuro profesional con formularios elegantes y tipografía de autoridad.

![Vista General del Rediseño](/home/anderson/.gemini/antigravity/brain/e8f324da-a4b8-4a2a-96f6-750490251066/hero_section_verification_1769564195183.png)
*(Captura del nuevo Hero y Header con estética Senior UX/UI)*

---

## Mejoras en Registro y Seguridad
El flujo de nuevos usuarios ha sido blindado y optimizado.

### 1. Robustez en el Registro
Se corrigió el manejo de números telefónicos internacionales y se integró la protección CSRF proactiva.

> [!TIP]
> El sistema ahora limpia automáticamente formatos como `+57 322...` para asegurar la integridad en la base de datos.

![Registro Exitoso](/home/anderson/.gemini/antigravity/brain/e8f324da-a4b8-4a2a-96f6-750490251066/signup_result_state_1769548347382.png)

### 2. Gestión de Notificaciones
Se reemplazaron las ventanas emergentes bloqueantes por un sistema de **Toast Notifications** premium.

```javascript
// Ejemplo de llamada en el código:
showNotification('✅ Perfil actualizado correctamente');
```

---

## Diseño Responsivo Global (Mobile & Tablet First)
Se ha implementado una arquitectura adaptativa completa para asegurar que la experiencia de lujo se mantenga en cualquier dispositivo.

#### Optimizaciones Realizadas:
- **Menú Móvil Inteligente**: Nuevo sistema de navegación tipo "hamburguesa" con despliegue lateral fluido y cierre automático.
- **Tipografía Adaptativa**: Ajuste automático de tamaños de fuente para legibilidad óptima en móviles.
- **Rejillas Flexibles**: Secciones de Galería, Testimonios y Productos ahora se apilan de forma inteligente en una sola columna.
- **Tienda & Facturación**: Tablas y filtros rediseñados para una navegación táctil cómoda.

![Vista Móvil y Menú](/home/anderson/.gemini/antigravity/brain/520930b3-4903-4792-b573-425b5315de68/mobile_menu_open_final_1769713189163.png)
*(Captura del nuevo menú móvil y diseño adaptativo)*

---

## Auditoría de Logs e Informe OWASP
Toda la plataforma ha sido auditada bajo los estándares **OWASP Top 10:2021**.

- **Informe de Seguridad**: [INFORME_SEGURIDAD_OWASP.md](file:///home/anderson/Documentos/rd_2/PROYECTO/INFORME_SEGURIDAD_OWASP.md)
- **Trazabilidad de Logs**: Cada evento crítico se registra en `/backend/logs/`.

---

### 2. Autenticación Robusta
- **Login/Registro**: Flujo completo funcional con contraseña encriptada (Bcrypt).
- **Redirección Inteligente**: El frontend detecta automáticamente si está en `/PROYECTO/` u otra carpeta.

---

## Requisitos para el Servidor de Producción (Checklist)
Para que todo esto funcione en tu servidor final (`10.5.213.111` o similar), asegúrate de tener instalado:

### 1. Sistema Base
- **Servidor Web**: Apache 2.4+ (o Nginx).
- **Base de Datos**: PostgreSQL 13+ (idealmente 14 o 15).

### 2. PHP y Extensiones (Vital)
No basta con instalar PHP, necesitas estas piezas específicas:
- `php-pgsql` (Para conectar con la BD).
- `php-pdo` (El motor que usamos en `config.php`).
- `php-mbstring` (Para manejar tildes y caracteres especiales).
- `php-json` (Para las respuestas de la API).

### 3. Configuraciones Clave
- **Apache (`.htaccess`)**: Asegúrate de que `AllowOverride All` esté activado para que funcionen las rutas amigables si las usas en el futuro.
- **Permisos de Carpeta**: La carpeta `/var/www/html/PROYECTO` debe tener permisos de lectura para el usuario `www-data`.
  ```bash
  sudo chown -R www-data:www-data /var/www/html/PROYECTO
  sudo chmod -R 755 /var/www/html/PROYECTO
  ```

### 4. Último Paso Antes de Lanzar
Recuerda editar el archivo `.env` en el servidor con las credenciales reales:
```ini
DB_USER=gr_rdwatch
DB_HOST=localhost
```

### 2. Seguridad en Autenticación
- **Parche de Hashes**: Se detectó que la BD tenía claves en texto plano. Se actualizaron los scripts semilla para usar **Bcrypt** ($2y$10$...), alineándose con la seguridad del backend PHP (`password_verify`).

### 3. Preparación para Despliegue (CORS)
- **Configuración Dinámica**: El sistema ahora lee la IP del servidor desde `.env` (`CORS_ALLOWED_ORIGINS`) e inyecta esa IP automáticamente en las cabeceras de seguridad (CSP).
- **Acceso Dual**: Permite acceso seguro tanto por `localhost` como por IP de red (`10.x.x.x`) sin reconfiguraciones manuales.

---
