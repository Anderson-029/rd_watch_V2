# RD Watch - Sistema de Gestión de Relojería de Lujo

![Project Status](https://img.shields.io/badge/Status-Production--Ready-success?style=for-the-badge)
![Tech Stack](https://img.shields.io/badge/Stack-PHP_8.1--PostgreSQL--Vanilla_JS-blue?style=for-the-badge)

Versión 3.5 "Elite" - Ecosistema completo para administración de servicios, ventas y catálogo de relojería fina.

## Documentación Maestra
Para entender a fondo la evolución y el estado del proyecto, consulta los siguientes documentos en la raíz:
- [LISTA DE TAREAS](file:///home/anderson/Documentos/rd_2/TASK_LIST.md): Seguimiento de hitos y progreso.
- [PLAN DE IMPLEMENTACIÓN](file:///home/anderson/Documentos/rd_2/IMPLEMENTATION_PLAN.md): Arquitectura y hoja de ruta técnica.
- [RECORRIDO TÉCNICO](file:///home/anderson/Documentos/rd_2/WALKTHROUGH.md): Guía visual y funcional.

---

## Guía de Despliegue Rápido

### 1. Requisitos Iniciales
- **Servidor Web**: PHP 8.1+ (ej. Apache, Nginx o Servidor Integrado PHP).
- **Base de Datos**: PostgreSQL con conexión activa.

### 2. Configuración en un Clic
Si estás en el entorno de desarrollo local, utiliza el orquestador:
```bash
./PROYECTO/iniciar.sh
```
*Este script automatiza el arranque del Frontend (puerto 8000) y Backend (puerto 8001), gestionando logs automáticamente.*

### 3. Configuración Manual
1. **Variables de Entorno**: Configura `PROYECTO/backend/.env` basándote en `.env.example`.
2. **Base de Datos**: Ejecuta el script automatizado:
   ```bash
   ./PROYECTO/setup_database.sh
   ```

---

