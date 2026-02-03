/**
 * RD Watch - Configuración Global Frontend
 */
const API_CONFIG = {
    // Detectar automáticamente la base URL. 
    // Si se abre vía file://, forzar localhost:8001 para intentar la comunicación (aunque el navegador podría bloquearlo)
    baseUrl: (window.location.protocol === 'file:')
        ? 'http://localhost/backend/api'
        : window.location.origin + '/backend/api'
};

// Alerta preventiva si se detecta acceso por archivo local
if (window.location.protocol === 'file:') {
    console.error("RD Watch: Estás accediendo vía file://. Para que el sistema funcione correctamente, usa http://localhost:8000");
}

// Alias para compatibilidad con código legado (script.js)
const API_BASE_SHOP = API_CONFIG.baseUrl;
