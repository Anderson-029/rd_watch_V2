# 🛠️ Guía del Backend de RD WATCH V2

Esta guía explica la arquitectura técnica del proyecto, centrada en la **seguridad, modularidad y automatización**.

---

## 🏗️ 1. Infraestructura y Seguridad
El proyecto utiliza una pila tecnológica moderna basada en PHP y **PostgreSQL 16**.

| Elemento | Función |
| :--- | :--- |
| **Pila (Stack)** | PHP 8.x + PostgreSQL (en lugar de MySQL/MariaDB para mayor seguridad y robustez). |
| **`config.php`** | Conexión central vía **PDO**. Maneja sesiones, CORS y carga de entorno. |
| **`.env`** | Almacena credenciales de BD y claves de sesión de forma privada. |
| **BYTEA Storage** | Los comprobantes de pago no se guardan en carpetas públicas, sino como binarios protegidos directamente en la base de datos (columna `bytea`). Esto evita ataques de ejecución de scripts maliciosos. |
| **`get_comprobante.php`** | Endpoint seguro que extrae el binario, limpia el buffer de salida (`ob_clean`) para evitar corrupción y sirve la imagen con el MIME type correcto, validando permisos de admin. |

---

## 💾 2. Lógica de Base de Datos (Blindaje PostgreSQL)
La lógica de negocio reside en **Funciones de PostgreSQL** organizadas por módulos en `sql/logica_backend/` (patrón de ocultación total):

- **`auth_security.sql`**: Autenticación, sesiones, rate limiting y recuperación de contraseña (14 funciones `fn_auth_*`, `fn_sec_*`).
- **`catalog_master.sql`**: Catálogo de productos, marcas, categorías, subcategorías y servicios (20 funciones `fn_cat_*`).
- **`ecommerce_core.sql`**: Carrito, checkout atómico y gestión de órdenes (14 funciones `fn_cart_*`, `fn_checkout_*`, `fn_orders_*`).
- **`client_panel.sql`**: Panel de usuario, dashboard, perfil, citas y reseñas (14 funciones `fn_user_*`, `fn_citas_*`, `fn_reviews_*`).
- **`admin_reports.sql`**: Reportes, facturación, estadísticas y configuración (8 funciones `fn_stats_*`, `fn_invoice_*`, `fn_admin_*`).

---

## 🔐 3. Flujo de Autenticación
- **`login.php`**: Valida credenciales contra la función `fn_auth_get_user` con protección anti brute-force (`fn_sec_check_rate_limit`).
- **`signup.php`**: Registra usuarios via `fn_auth_register` con hash BCRYPT y validación de duplicados.
- **`me.php`**: Sincroniza el estado de la sesión entre el servidor y el navegador via `fn_auth_get_session`.

---

## ⚙️ 4. Automatización (DevOps)
El proyecto incluye un script maestro de despliegue:

### Linux / Mac (`.sh`)
- **`deploy_all.sh`**: Script Bash que ejecuta en orden: Schema → Migraciones → Triggers → Datos semilla → Blindaje (5 pasos).
- Ejecución: `./deploy_all.sh`

### Windows (`.bat`)
- **`install_db.bat`**: Script Batch equivalente que configura el entorno, lee credenciales y ejecuta la secuencia SQL usando `psql`.
- Ejecución: Doble clic en el archivo o desde CMD.

---

## 🔄 Flujo de Datos (Workflow)
1. **Frontend**: El usuario realiza una acción (ej: Comprar).
2. **API (PHP)**: Recibe la petición y valida el usuario (`config.php`). PHP actúa como proxy JSON opaco.
3. **Database (PL/pgSQL)**: PHP llama a una función blindada (ej: `fn_checkout_process`). La lógica es atómica y las tablas nunca se exponen.
4. **Respuesta**: El sistema devuelve JSON estándar al navegador.

---

> [!IMPORTANT]
> **Coherencia Proyectual**: Cualquier cambio en la base de datos debe realizarse primero en los archivos `.sql` de la carpeta `sql/` y luego aplicarse usando el script de instalación para mantener la integridad del sistema.
