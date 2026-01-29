# Plan de Implementación: RD Watch (Fase Elite)

Este documento detalla la hoja de ruta técnica para la estabilización y escalado del proyecto.

## Objetivos Estratégicos
1. **Portabilidad Total**: Eliminación de dependencias de rutas locales (hardcoded).
2. **Seguridad Defensiva**: Reforzamiento de la capa de API contra CSRF y ataques de fuerza bruta.
3. **Mantenibilidad**: Centralización de configuraciones y estandarización de logs.

---

## Arquitectura de Cambios

### Componente: Backend (Capa de Servicios)
#### [PROCESADO] [Logger.php](file:///home/anderson/Documentos/rd_2/PROYECTO/backend/Logger.php)
- Implementación de motor de logs centralizado.
- Integración en `security_headers.php` para auditoría de CORS.

#### [PROCESADO] [config.php](file:///home/anderson/Documentos/rd_2/PROYECTO/backend/config.php)
- Migración de configuración estática a variables de entorno (`.env`).
- Flexibilización de cookies (`SameSite=Lax`) para entornos de desarrollo.

### Componente: Frontend (Capa de Cliente)
#### [PROCESADO] [script.js](file:///home/anderson/Documentos/rd_2/PROYECTO/frontend/public/js/script.js)
- Refactorización de fetch nativo a `secureFetch`.
- Integración de notificaciones Toast dinámicas.

#### [NEW] [iniciar.sh](file:///home/anderson/Documentos/rd_2/PROYECTO/iniciar.sh)
- Script de automatización para orquestar servidores en puertos 8000/8001.

---

## Matriz de Verificación
| Funcionalidad | Método | Resultado |
| :--- | :--- | :--- |
| Registro de Usuarios | Browser Test (vocal cleanup) | ✅ EXITOSO |
| Persistencia de Sesión | Salto entre puertos (8000 -> 8001) | ✅ ESTABLE |
| Auditoría de Logs | Verificación de `app.log` | ✅ FUNCIONAL |
| Webhooks | Simulación vía Postman/Curl | ✅ RECIBIDO |

---

## Fase: Refinamiento Visual.

### 1. Refinamiento de Identidad (Color & Tipo)
- **Champagne Elegance**: Transición del dorado actual a un dorado champán mate profesional (`#AF944F`).
- **Deep Space Black**: Uso de `#0D0D0D` para fondos oscuros.
- **Typographic Authority**:
    - Encabezados: Aumento de jerarquía visual. Hero Title a `4.5rem - 5rem`.
    - Cuerpo de texto: Mantener un mínimo de `1.05rem` (17px approx) para legibilidad máxima.
    - Kerning: Espaciado expandido (`0.1em` a `0.3em`) solo en textos cortos y decorativos para evitar fatiga visual.

### 2. Modernización de Componentes
- **Glassmorphism Layering**: Implementación de `backdrop-filter: blur(10px)` en el encabezado.
- **Shadow Engineering**: Sustitución de sombras pesadas por sombras suaves de 3 niveles.
- **Micro-interacciones**: Animaciones de hover más lentas y suaves (0.4s).

### 3. Optimización de Layout
- **Whitespace Expansion**: Aumento de los márgenes para que la interfaz "respire".
- **Visual Harmony**: Estandarización de radios de borde (border-radius) a 8px - 12px.

---

