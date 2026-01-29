# ⌚ RD Watch - Manual de Instalación y Despliegue

Este documento describe los requisitos y pasos necesarios para desplegar el ecosistema de **RD Watch** en sistemas Linux, macOS y Windows de forma automatizada y segura.

---

## Requisitos Mínimos

### Software Base
*   **PHP 7.4 o superior** (Recomendado 8.x).
*   **PostgreSQL 12 o superior**.
*   **Git** (Opcional).

---

## Instalación Rápida (Recomendada)

Hemos automatizado todo el proceso de creación de base de datos, carga de funciones y **blindaje de seguridad** (Bcrypt y esquema).

### Linux / macOS
1.  Otorga permisos de ejecución:
    ```bash
    chmod +x setup/unix/*.sh
    ```
2.  **Verificación**: Ejecuta el validador de entorno:
    ```bash
    ./setup/unix/00_verificar_requisitos.sh
    ```
3.  **Configuración**: Configura la base de datos y seguridad:
    ```bash
    ./setup/unix/01_configurar_bd.sh
    ```
4.  **Lanzamiento**: Inicia la plataforma:
    ```bash
    ./setup/unix/02_iniciar_servidores.sh
    ```
    *(Tip: Puedes usar `sh iniciar.sh` en la raíz para hacerlo todo en un paso).*

### Windows
1.  **Diagnóstico**: Ejecuta `setup\windows\01_verificar_requisitos.bat`.
2.  **Configuración**: Ejecuta `setup\windows\02_configurar_bd.bat`. (Crea la BD y aplica seguridad automáticamente).
3.  **Lanzamiento**: Ejecuta `setup\windows\03_iniciar_servidores.bat`.
4.  **Verificación**: Ejecuta `setup\windows\04_verificar_sistema.bat`.

---

## Seguridad y Mantenimiento

### Informe de Auditoría
Puedes consultar el cumplimiento de los estándares de seguridad en el [Informe OWASP Profesional](INFORME_SEGURIDAD_OWASP.md).

### Scripts de Utilidad
Si necesitas resetear las credenciales o reparar el esquema de seguridad sin reinstalar todo, utiliza:
-   **Unix**: `setup/unix/setup_security.sh`
-   **Windows**: `setup/windows/setup_security.bat`

---

## Direcciones de Acceso
*   **Frontend**: [http://localhost:8000](http://localhost:8000)
*   **Backend (API)**: [http://localhost:8001](http://localhost:8001)

---

