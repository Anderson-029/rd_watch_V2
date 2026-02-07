# RD WATCH V2 - Sistema de Gestión de Relojería Profesional ⌚

Repositorio oficial del proyecto **RD WATCH V2**, un sistema de e-commerce y gestión de servicios técnicos para relojería de lujo, optimizado para **seguridad y escalabilidad**.

## 🚀 Inicio Rápido

Para poner en marcha el proyecto, simplemente ejecuta el script de instalación automática:

```bash
./install_db.sh
```

> [!NOTE]
> Asegúrate de tener configurado tu archivo `.env` en `src/backend/` con las credenciales de PostgreSQL.

## 🏗️ Arquitectura del Sistema

El proyecto sigue una arquitectura **Centrada en Datos (Database-First)**:

- **Frontend**: Vanila JS, CSS moderno y HTML5. Comunicación asíncrona vía Fetch API.
- **Backend**: Servidores API en PHP 8.x con seguridad reforzada (CORS, CSRF, Session Management).
- **Base de Datos**: PostgreSQL 16. Toda la lógica de negocio reside en **Funciones Modulares (PL/pgSQL)** para máxima velocidad y seguridad.

## 🔐 Características Principales

- **Almacenamiento Seguro (BYTEA)**: Los comprobantes de pago se almacenan de forma binaria en la base de datos, eliminando la necesidad de carpetas públicas inseguras.
- **Modularidad SQL**: Funciones organizadas por entidad (Usuarios, Productos, Órdenes, Facturas).
- **Instalador Automático**: Proceso de despliegue en un solo paso con registro detallado de logs.
- **Facturación Digital**: Generación automática de facturas PDF/HTML post-compra.

## 📁 Estructura del Proyecto

- `src/`: Código fuente de la aplicación (Frontend y Backend).
- `sql/`: Esquemas, funciones modulares y scripts de población de datos.
- `install_db.sh`: Motor de despliegue automático.
- `GUIA_BACKEND.md`: Documentación técnica detallada del servidor.

---

**Desarrollado con enfoque en Coherencia, Congruencia y Lógica de Programación Superior.**
