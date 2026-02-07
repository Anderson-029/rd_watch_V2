/**
 * factura.js - Manejo de la página de factura
 */

// Usamos un nombre único para evitar conflictos con script.js (SyntaxError: redeclaration of const API_BASE)
const API_BASE_FACTURA = API_CONFIG.baseUrl;

// Función para formatear precio
function formatPrice(amount) {
    return Number(amount).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

// Función para formatear fecha
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Función para obtener parámetro de URL
function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

// Función principal para cargar la factura
async function cargarFactura() {
    const container = document.getElementById('factura-container');
    const template = document.getElementById('factura-template');

    try {
        const idOrden = getUrlParameter('orden');
        if (!idOrden) {
            container.innerHTML = `
                <div style="text-align:center; padding:50px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#ffc107;"></i>
                    <h2>No se encontró el número de orden</h2>
                    <p>Por favor, verifica el enlace e intenta nuevamente.</p>
                    <a href="user/user.html" class="btn-factura btn-volver" style="display:inline-flex;margin-top:20px;">
                        <i class="fas fa-arrow-left"></i> Volver a Mi Cuenta
                    </a>
                </div>
            `;
            return;
        }

        // Cargar datos de factura y banco en paralelo
        const [resFactura, resBanco] = await Promise.all([
            fetch(`${API_BASE_FACTURA}/get_factura.php?id_orden=${idOrden}`, {
                credentials: 'include'
            }),
            fetch(`${API_BASE_FACTURA}/get_config_banco.php`)
        ]);

        const dataFactura = await resFactura.json();
        const dataBanco = await resBanco.json();

        if (!dataFactura.ok) {
            console.error('Error reportado por API:', dataFactura.msg);
            throw new Error(dataFactura.msg || 'Error al cargar factura');
        }

        // Clonar template y llenar con datos
        const facturaContent = template.content.cloneNode(true);

        // Información de factura y orden
        facturaContent.getElementById('num-factura').textContent = dataFactura.factura.id_factura;
        facturaContent.getElementById('fecha-emision').textContent = formatDate(dataFactura.factura.fecha_emision);
        facturaContent.getElementById('orden-numero').textContent = idOrden;
        facturaContent.getElementById('orden-estado').textContent =
            dataFactura.factura.estado_orden === 'pendiente' ? 'Pendiente de Pago' : dataFactura.factura.estado_orden;
        facturaContent.getElementById('orden-fecha').textContent = formatDate(dataFactura.factura.fecha_orden);
        facturaContent.getElementById('ref-pago').textContent = idOrden;

        // Datos del cliente
        facturaContent.getElementById('cliente-nombre').textContent = dataFactura.factura.nom_usuario;
        facturaContent.getElementById('cliente-email').textContent = dataFactura.factura.correo_usuario;
        facturaContent.getElementById('cliente-telefono').textContent = dataFactura.factura.num_telefono_usuario;

        // Dirección de envío - Extraer del campo concepto que tiene formato "Envío a: dirección (metodo)"
        let direccionEnvio = dataFactura.factura.direccion_principal || 'No especificada';
        if (dataFactura.factura.concepto) {
            const match = dataFactura.factura.concepto.match(/Envío a: (.+?) \(/);
            if (match && match[1]) {
                direccionEnvio = match[1];
            }
        }
        facturaContent.getElementById('cliente-direccion').textContent = direccionEnvio;

        // Productos
        const tbody = facturaContent.getElementById('productos-tbody');
        let subtotal = 0;

        dataFactura.productos.forEach(prod => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${prod.nom_producto}</td>
                <td class="text-right">${prod.cantidad}</td>
                <td class="text-right">${formatPrice(prod.precio_unitario)}</td>
                <td class="text-right">${formatPrice(prod.subtotal_linea)}</td>
            `;
            tbody.appendChild(tr);
            subtotal += parseFloat(prod.subtotal_linea);
        });

        // Totales (Restamos el costo de envío que ya está incluido en total_factura)
        const ENVIO = 15000;
        const subtotalSinEnvio = subtotal;

        facturaContent.getElementById('total-subtotal').textContent = formatPrice(subtotalSinEnvio);
        facturaContent.getElementById('total-envio').textContent = formatPrice(ENVIO);
        facturaContent.getElementById('total-final').textContent = formatPrice(dataFactura.factura.total_factura);

        // Información bancaria
        if (dataBanco.ok) {
            facturaContent.getElementById('banco-nombre').textContent = dataBanco.banco.nombre;
            facturaContent.getElementById('banco-tipo').textContent = dataBanco.banco.tipo_cuenta;
            facturaContent.getElementById('banco-numero').textContent = dataBanco.banco.numero_cuenta;
            facturaContent.getElementById('banco-titular').textContent = dataBanco.banco.titular;
            facturaContent.getElementById('banco-breb').textContent = dataBanco.banco.breb_llave;
        }

        // Reemplazar contenido
        container.innerHTML = '';
        container.appendChild(facturaContent);
        console.log('Factura renderizada exitosamente para orden:', idOrden);

    } catch (error) {
        console.error('Error cargando factura:', error);
        container.innerHTML = `
            <div style="text-align:center; padding:50px;">
                <i class="fas fa-times-circle" style="font-size:3rem;color:#dc3545;"></i>
                <h2>Error al cargar la factura</h2>
                <p>${error.message}</p>
                <div style="margin-top:20px; font-size: 0.9rem; color: #666;">
                    ID Orden intentado: ${getUrlParameter('orden') || 'Ninguno'}
                </div>
                <a href="user/user.html" class="btn-factura btn-volver" style="display:inline-flex;margin-top:20px;">
                    <i class="fas fa-arrow-left"></i> Volver a Mi Cuenta
                </a>
            </div>
        `;
    }
}

// Inicialización Robusta: Ejecutar si el DOM ya está listo o esperar a que lo esté
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM cargado, iniciando carga de factura...');
        cargarFactura();
    });
} else {
    console.log('El DOM ya estaba listo, iniciando carga de factura inmediata...');
    cargarFactura();
}
