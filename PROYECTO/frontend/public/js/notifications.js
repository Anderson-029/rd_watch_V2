/**
 * RD Watch - Sistema de Notificaciones Toast Profesional
 */
window.showNotification = function (msg, type = 'success') {
    let notif = document.getElementById('notification');

    // Si no existe, crearlo dinámicamente
    if (!notif) {
        notif = document.createElement('div');
        notif.id = 'notification';
        notif.className = 'notification';
        document.body.appendChild(notif);
    }

    const isError = type === 'error' || msg.toLowerCase().includes('error') || msg.includes('⚠️') || msg.includes('❌');

    notif.textContent = msg;
    notif.className = 'notification';
    notif.classList.add(isError ? 'error' : 'success');

    // Forzado de reflow para reiniciar animación si ya estaba mostrándose
    notif.classList.remove('show');
    void notif.offsetWidth;

    notif.classList.add('show');

    // Auto-ocultar tras 4 segundos
    if (window.notifTimeout) clearTimeout(window.notifTimeout);
    window.notifTimeout = setTimeout(() => {
        notif.classList.remove('show');
    }, 4000);
};
