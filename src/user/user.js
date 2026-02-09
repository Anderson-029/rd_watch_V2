// 🔒 CONSTANTE BASE URL
const API_BASE_URL = API_CONFIG.baseUrl;

// ===================================
// CSRF Token Management
// ===================================
let csrfToken = null;

async function fetchCsrfToken() {
    try {
        const response = await fetch(`${API_BASE_URL}/get_csrf_token.php`, { credentials: 'include' });
        const data = await response.json();
        if (data.ok && data.csrf_token) {
            csrfToken = data.csrf_token;
            sessionStorage.setItem('csrf_token', csrfToken);
        }
    } catch (error) {
        console.error('Error fetching CSRF token:', error);
        csrfToken = sessionStorage.getItem('csrf_token');
    }
}

async function secureFetch(url, options = {}) {
    if (!csrfToken) await fetchCsrfToken();

    if (options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = csrfToken;
    }

    options.credentials = 'include';

    try {
        const response = await fetch(url, options);
        if (response.ok) {
            // Clonar para leer JSON sin consumir el body principal si se necesita después (aunque aquí leemos JSON para token)
            const clone = response.clone();
            try {
                const data = await clone.json();
                if (data.csrf_token) {
                    csrfToken = data.csrf_token;
                    sessionStorage.setItem('csrf_token', csrfToken);
                }
            } catch (e) { } // Ignorar si no es JSON
        }
        return response;
    } catch (error) {
        console.error('Secure fetch error:', error);
        throw error;
    }
}

// 🔒 OBTENER USUARIO DE LA SESIÓN
function getUser() {
    const userData = sessionStorage.getItem('user');
    if (!userData) return null;
    try {
        return JSON.parse(userData);
    } catch (err) {
        console.error('Error al parsear usuario:', err);
        return null;
    }
}

// 🔒 VERIFICACIÓN DE AUTENTICACIÓN Y CARGA INICIAL DE DATOS
(async function checkAuth() {
    const user = getUser();

    if (!user) {
        showNotification('⚠️ Debes iniciar sesión para acceder a tu panel');
        const appUrl = (typeof API_CONFIG !== 'undefined' && API_CONFIG.appUrl) ? API_CONFIG.appUrl : '../../';
        window.location.href = `${appUrl}/index.html`;
        return;
    }

    fetchCsrfToken();

    try {
        const userNameEl = document.getElementById('userName');
        if (userNameEl) {
            userNameEl.textContent = user.nombre.split(' ')[0];
        }

        if (user.rol === 'admin') {
            const welcomeSection = document.querySelector('.welcome-section');
            if (welcomeSection) {
                const adminAlert = document.createElement('div');
                adminAlert.style.cssText = 'background: var(--primary-color); color: var(--white); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;';
                adminAlert.innerHTML = `
                    <strong>👑 Eres administrador</strong>
                    <br><br>
                    <a href="../admin/admin.html" style="color: var(--white); text-decoration: underline;">
                        Ir al Panel de Administración →
                    </a>
                `;
                welcomeSection.insertBefore(adminAlert, welcomeSection.firstChild);
            }
        }

        await cargarDatosPerfil(user.id);
        await cargarPedidos(user.id);
        await cargarCitas(user.id);

    } catch (err) {
        console.error('Error al verificar usuario:', err);
        showNotification('❌ Sesión inválida');
        window.location.href = '../index.html';
    }
})();

// 🔄 CARGAR DATOS DEL PERFIL DESDE EL BACKEND
async function cargarDatosPerfil(userId) {
    try {
        const response = await fetch(`${API_BASE_URL}/user_actions.php?action=perfil&uid=${userId}`, {
            method: 'GET',
            credentials: 'include'
        });
        const result = await response.json();
        if (result.ok && result.data) {
            const perfilNombre = document.getElementById('perfilNombre');
            const perfilEmail = document.getElementById('perfilEmail');
            const inputNombre = document.getElementById('inputNombre');
            const inputEmail = document.getElementById('inputEmail');
            const inputTelefono = document.getElementById('inputTelefono');
            const direccionPrincipal = document.getElementById('direccionPrincipal');

            if (perfilNombre) perfilNombre.textContent = result.data.nom_usuario || '';
            if (perfilEmail) perfilEmail.textContent = result.data.correo_usuario || '';
            if (inputNombre) inputNombre.value = result.data.nom_usuario || '';
            if (inputEmail) inputEmail.value = result.data.correo_usuario || '';
            if (inputTelefono) inputTelefono.value = result.data.num_telefono_usuario || '';
            if (direccionPrincipal) direccionPrincipal.textContent = result.data.direccion_principal || 'No configurada';
        }
    } catch (error) {
        console.error('Error al cargar datos del perfil:', error);
    }
}

// 🔄 CARGAR PEDIDOS DEL USUARIO
async function cargarPedidos(userId) {
    try {
        const response = await fetch(`${API_BASE_URL}/user_actions.php?action=pedidos&uid=${userId}`, {
            method: 'GET',
            credentials: 'include'
        });
        const result = await response.json();
        if (result.ok && result.data) {
            const pedidos = result.data;
            const tbodyPedidos = document.getElementById('tbodyPedidos');
            if (!tbodyPedidos) return;

            if (pedidos.length === 0) {
                tbodyPedidos.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 40px; color: #888;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                            No tienes pedidos registrados
                        </td>
                    </tr>
                `;
                return;
            }

            tbodyPedidos.innerHTML = pedidos.map(pedido => {
                const estado = pedido.estado_orden || 'pendiente';
                const badgeClass = getBadgeClass(estado);
                const total = parseFloat(pedido.total_orden || 0);

                return `
                    <tr>
                        <td><strong>#${pedido.id_orden}</strong></td>
                        <td>${pedido.concepto || 'Pedido de productos'}</td>
                        <td>${pedido.fecha || 'N/A'}</td>
                        <td style="font-weight: bold;">$${total.toFixed(2)}</td>
                        <td><span class="badge ${badgeClass}">${capitalizeFirst(estado)}</span></td>
                    </tr>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('Error al cargar pedidos:', error);
    }
}

// 🔄 CARGAR CITAS DEL USUARIO
async function cargarCitas(userId) {
    try {
        const response = await fetch(`${API_BASE_URL}/citas.php`, {
            method: 'GET',
            credentials: 'include'
        });
        const result = await response.json();
        if (result.ok && result.citas) {
            const citas = result.citas;
            const tbodyCitas = document.getElementById('tbodyCitas');
            if (!tbodyCitas) return;

            if (citas.length === 0) {
                tbodyCitas.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 40px; color: #888;">
                            <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                            No tienes citas registradas
                        </td>
                    </tr>
                `;
                return;
            }

            const citasPendientes = citas.filter(c => c.estado === 'pendiente' || c.estado === 'confirmada').length;
            const citasActivasEl = document.getElementById('citasActivas');
            if (citasActivasEl) {
                if (citasPendientes > 0) {
                    citasActivasEl.textContent = `${citasPendientes} cita${citasPendientes !== 1 ? 's' : ''} pendiente${citasPendientes !== 1 ? 's' : ''}`;
                    citasActivasEl.style.color = 'var(--primary-color)';
                } else {
                    citasActivasEl.textContent = '0 citas pendientes';
                    citasActivasEl.style.color = '';
                }
            }

            tbodyCitas.innerHTML = citas.map(cita => {
                const estado = cita.estado || 'pendiente';
                const badgeClass = getBadgeClass(estado);
                return `
                    <tr>
                        <td><strong>${cita.nombre_servicio || 'Servicio'}</strong></td>
                        <td>${cita.fecha_preferida || 'N/A'}</td>
                        <td><span class="badge ${cita.prioridad === 'alta' ? 'cancelado' : 'enviado'}">${capitalizeFirst(cita.prioridad)}</span></td>
                        <td><span class="badge ${badgeClass}">${capitalizeFirst(estado)}</span></td>
                        <td style="font-size: 0.9em; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${cita.notas || ''}">
                            ${cita.notas || '-'}
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('Error al cargar citas:', error);
    }
}

// Función auxiliar para determinar clase de badge según estado
function getBadgeClass(estado) {
    const estadoLower = estado.toLowerCase();
    if (estadoLower.includes('confirmado')) return 'confirmado'; // Azul
    if (estadoLower.includes('enviado')) return 'enviado'; // Verde
    if (estadoLower.includes('cancelado')) return 'cancelado'; // Rojo
    return 'pendiente'; // Amarillo
}

// Función auxiliar para capitalizar primera letra
function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

// 🌆 CARGAR DEPARTAMENTOS Y CIUDADES
async function cargarDepartamentos() {
    try {
        const response = await fetch(`${API_BASE_URL}/ciudades.php?action=departamentos`);
        const result = await response.json();
        if (result.ok) {
            const selectDepto = document.getElementById('inputDepartamento');
            if (selectDepto) {
                selectDepto.innerHTML = '<option value="">Seleccione departamento...</option>' +
                    result.departamentos.map(d => `<option value="${d.id_departamento}">${d.nombre_departamento}</option>`).join('');
            }
        }
    } catch (error) {
        console.error('Error al cargar departamentos:', error);
    }
}

async function cargarCiudadesPorDepto(idDepartamento) {
    try {
        const response = await fetch(`${API_BASE_URL}/ciudades.php?action=ciudades&id_departamento=${idDepartamento}`);
        const result = await response.json();
        if (result.ok) {
            const selectCiudad = document.getElementById('inputCiudad');
            if (selectCiudad) {
                selectCiudad.innerHTML = '<option value="">Seleccione ciudad...</option>' +
                    result.ciudades.map(c => `<option value="${c.id_ciudad}" data-postal="${c.codigo_postal || ''}">${c.nombre_ciudad}</option>`).join('');
                selectCiudad.disabled = false;
            }
        }
    } catch (error) {
        console.error('Error al cargar ciudades:', error);
    }
}

// 🚪 CERRAR SESIÓN
function cerrarSesion() {
    if (!confirm('¿Deseas cerrar sesión?')) return;
    fetch(`${API_BASE_URL}/logout.php`, {
        method: 'POST',
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            sessionStorage.removeItem('user');
            showNotification('Sesión cerrada correctamente');
            window.location.href = `${API_BASE_URL.replace('/src/backend/api', '')}/index.html`;
        });
}

// 🔔 MOSTRAR NOTIFICACIÓN
function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    if (!notification) return;
    notification.textContent = message;
    notification.className = 'notification';
    if (isError) notification.classList.add('error');
    else notification.classList.add('success');
    notification.classList.add('show');
    setTimeout(() => notification.classList.remove('show'), 5000);
}

// 📄 MOSTRAR SECCIÓN
function showSection(sectionId) {
    document.querySelectorAll('.welcome-section, .form-section').forEach(section => {
        section.classList.remove('active');
        section.style.display = 'none';
    });
    if (sectionId === 'inicio') {
        const inicioEl = document.getElementById('inicio');
        if (inicioEl) inicioEl.style.display = 'block';
    } else {
        const section = document.getElementById(sectionId);
        if (section) {
            section.classList.add('active');
            section.style.display = 'block';
        }
    }
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('data-section') === sectionId) link.classList.add('active');
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// 📅 INICIALIZACIÓN DOM
document.addEventListener('DOMContentLoaded', async () => {
    const btnMap = {
        'btnResena': 'resenaForm',
        'btnEditarPerfil': 'perfilForm',
        'btnHistorial': 'pedidoForm',
        'btnDirecciones': 'direccionForm',
        'btnSolicitarServicio': 'servicioForm',
        'btnCitasHistorial': 'citaForm'
    };

    Object.keys(btnMap).forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', () => showSection(btnMap[id]));
    });

    document.querySelectorAll('.btn-cancelar').forEach(btn => {
        btn.addEventListener('click', () => showSection('inicio'));
    });

    const forms = [
        { id: 'formPerfil', handler: guardarPerfil },
        { id: 'formDireccion', handler: guardarDireccion },
        { id: 'formResena', handler: enviarResena }
    ];

    forms.forEach(item => {
        const f = document.getElementById(item.id);
        if (f) f.addEventListener('submit', (e) => item.handler(e));
    });

    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            const section = link.getAttribute('data-section');
            if (link.id === 'logoutLink') {
                e.preventDefault();
                cerrarSesion();
                return;
            }
            if (section) {
                e.preventDefault();
                showSection(section);
            }
        });
    });

    const fechaInput = document.getElementById('fechaPreferida');
    if (fechaInput) {
        const today = new Date().toISOString().split('T')[0];
        fechaInput.min = today;
        fechaInput.value = today;
    }

    const inputDepartamento = document.getElementById('inputDepartamento');
    if (inputDepartamento) {
        inputDepartamento.addEventListener('change', (e) => {
            const idDepto = e.target.value;
            if (idDepto) cargarCiudadesPorDepto(idDepto);
        });
    }

    const inputCiudad = document.getElementById('inputCiudad');
    if (inputCiudad) {
        inputCiudad.addEventListener('change', (e) => {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const postal = selectedOption.getAttribute('data-postal');
            const inputPostal = document.getElementById('inputPostal');
            if (postal && inputPostal) inputPostal.value = postal;
        });
    }

    const servicesGrid = document.getElementById('user-services-grid');
    if (servicesGrid) {
        servicesGrid.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-solicitar-servicio');
            if (btn) seleccionarServicio(btn.getAttribute('data-nombre'), btn.getAttribute('data-id'));
        });
    }

    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    if (mobileMenuBtn && mainNav) {
        mobileMenuBtn.addEventListener('click', () => {
            const isActive = mainNav.classList.toggle('active');
            mobileMenuBtn.innerHTML = isActive ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });
    }

    showSection('inicio');

    try {
        await cargarDepartamentos();
        await cargarServiciosPanel();
    } catch (err) {
        console.error("Error cargando datos iniciales:", err);
    }
});

async function guardarPerfil(e) {
    e.preventDefault();
    const user = getUser();
    if (!user) { showNotification('❌ Error de sesión', true); return; }
    const nombre = document.getElementById('inputNombre').value;
    const email = document.getElementById('inputEmail').value;
    const telefono = document.getElementById('inputTelefono').value;
    try {
        const response = await secureFetch(`${API_BASE_URL}/user_actions.php?action=update_profile`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ uid: user.id, nombre, email, telefono })
        });
        const result = await response.json();
        if (result.ok) {
            document.getElementById('perfilNombre').textContent = nombre;
            document.getElementById('userName').textContent = nombre.split(' ')[0];
            user.nombre = nombre;
            sessionStorage.setItem('user', JSON.stringify(user));
            showNotification('✅ Perfil actualizado correctamente');
            setTimeout(() => showSection('inicio'), 1500);
        } else {
            showNotification('❌ ' + (result.msg || 'Error al actualizar perfil'), true);
        }
    } catch (error) {
        showNotification('❌ Error al conectar con el servidor', true);
    }
}

async function guardarDireccion(e) {
    e.preventDefault();
    const user = getUser();
    if (!user) { showNotification('❌ Error de sesión', true); return; }
    const direccion = document.getElementById('inputDireccion').value;
    const ciudadId = document.getElementById('inputCiudad').value;
    const postal = document.getElementById('inputPostal').value;
    if (!ciudadId) { showNotification('❌ Debes seleccionar una ciudad', true); return; }
    try {
        const response = await secureFetch(`${API_BASE_URL}/user_actions.php?action=update_address`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ uid: user.id, direccion, ciudad_id: parseInt(ciudadId), postal })
        });
        const result = await response.json();
        if (result.ok) {
            const selectCiudad = document.getElementById('inputCiudad');
            const ciudadNombre = selectCiudad.options[selectCiudad.selectedIndex].text;
            document.getElementById('direccionPrincipal').textContent = `${direccion}, ${ciudadNombre}`;
            showNotification('✅ Dirección guardada correctamente');
            setTimeout(() => showSection('inicio'), 1500);
        } else {
            showNotification('❌ ' + (result.msg || 'Error al guardar dirección'), true);
        }
    } catch (error) {
        showNotification('❌ Error al conectar con el servidor', true);
    }
}

function seleccionarServicio(nombreServicio, idServicio) {
    document.getElementById('servicioSeleccionado').value = nombreServicio;
    document.getElementById('servicioSeleccionadoId').value = idServicio;
    const form = document.getElementById('formSolicitudServicio');
    if (form) {
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function cancelarSolicitud() {
    const form = document.getElementById('formSolicitudServicio');
    if (form) {
        form.reset();
        form.classList.add('hidden');
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const formSolicitud = document.getElementById('formSolicitudServicio');
if (formSolicitud) {
    formSolicitud.addEventListener('submit', async function (e) {
        e.preventDefault();
        const user = getUser();
        if (!user) { showNotification('❌ Error de sesión', true); return; }
        const idServicio = document.getElementById('servicioSeleccionadoId').value;
        const nombreServicio = document.getElementById('servicioSeleccionado').value;
        const fechaPreferida = document.getElementById('fechaPreferida').value;
        const prioridad = document.getElementById('prioridad').value;
        const notas = document.getElementById('notasServicio').value;
        try {
            const response = await secureFetch(`${API_BASE_URL}/citas.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ p_id_servicio: idServicio, p_fecha_pref: fechaPreferida, p_prioridad: prioridad, p_notas: notas })
            });
            const result = await response.json();
            if (result.ok) {
                showNotification(`✅ Solicitud enviada correctamente`);
                this.reset();
                this.classList.add('hidden');
                setTimeout(() => showSection('inicio'), 2000);
            } else {
                showNotification('❌ ' + result.msg, true);
            }
        } catch (error) {
            showNotification('❌ Error al conectar con el servidor', true);
        }
    });
}

const inputT = document.getElementById('inputTarjeta');
if (inputT) {
    inputT.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\s/g, '');
        e.target.value = value.match(/.{1,4}/g)?.join(' ') || value;
    });
}

async function enviarResena(e) {
    e.preventDefault();
    const user = getUser();
    if (!user) return;
    const ratingInputs = document.getElementsByName('rating');
    let calificacion = 0;
    for (const input of ratingInputs) { if (input.checked) { calificacion = input.value; break; } }
    const comentario = document.getElementById('resenaTexto').value;
    try {
        const res = await secureFetch(`${API_BASE_URL}/resenas.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_usuario: user.id, calificacion: parseInt(calificacion), comentario })
        });
        const data = await res.json();
        if (data.ok) {
            showNotification('✅ Reseña enviada');
            e.target.reset();
            setTimeout(() => showSection('inicio'), 1500);
        }
    } catch (err) { }
}

async function cargarServiciosPanel() {
    const servicesGrid = document.getElementById('user-services-grid');
    if (!servicesGrid) return;
    try {
        const res = await fetch(`${API_BASE_URL}/servicios.php`, { credentials: 'include' });
        const data = await res.json();
        if (data.ok) {
            servicesGrid.innerHTML = data.servicios.map(s => `
                <div class="service-card">
                    <h3>${s.nom_servicio}</h3>
                    <p>${s.descripcion}</p>
                    <button class="button button-primary btn-solicitar-servicio" data-nombre="${s.nom_servicio}" data-id="${s.id_servicio}">Solicitar</button>
                </div>
            `).join('');
        }
    } catch (e) { }
}
