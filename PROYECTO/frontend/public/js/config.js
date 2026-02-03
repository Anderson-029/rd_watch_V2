/**
 * RD Watch - Configuración Global Frontend
 */
const API_CONFIG = {
    // Detectar si estamos en un subdirectorio (ej: /PROYECTO/)
    // Busca 'frontend' en la ruta y toma todo lo anterior como raíz
    get baseUrl() {
        if (window.location.protocol === 'file:') return 'http://localhost/backend/api';

        // Determinar raíz del proyecto dinámicamente
        const path = window.location.pathname;
        const projectRoot = path.substring(0, path.indexOf('/frontend'));

        // Si no encuentra 'frontend', asume raíz del dominio, sino usa la subcarpeta
        return window.location.origin + (projectRoot ? projectRoot : '') + '/backend/api';
    },

    // Nueva propiedad para saber dónde está la raíz pública del frontend
    get appUrl() {
        if (window.location.protocol === 'file:') return 'http://localhost/frontend/public';
        const path = window.location.pathname;
        const projectRoot = path.substring(0, path.indexOf('/frontend'));
        return window.location.origin + (projectRoot ? projectRoot : '') + '/frontend/public';
    }
};
// Convertimos los getters en propiedades estáticas para compatibilidad
Object.defineProperty(API_CONFIG, 'baseUrl', { value: API_CONFIG.baseUrl });
Object.defineProperty(API_CONFIG, 'appUrl', { value: API_CONFIG.appUrl });

// Alerta preventiva si se detecta acceso por archivo local
if (window.location.protocol === 'file:') {
    console.error("RD Watch: Estás accediendo vía file://. Para que el sistema funcione correctamente, usa http://localhost:8000");
}

// Alias para compatibilidad con código legado (script.js)
const API_BASE_SHOP = API_CONFIG.baseUrl;
