/**
 * RD WATCH - PANEL DE ADMINISTRACIÓN (CORE UI)
 * ---------------------------------------------------------
 * Propósito: Orquestar la interfaz administrativa de RD-Watch. Gestiona 
 * inventarios, clientes, órdenes y estadísticas en tiempo real.
 * 
 * Pilares de Seguridad:
 * 1. Auth Gate: Valida el rol de 'admin' antes de cargar la interfaz.
 * 2. Secure Fetch: Centraliza las peticiones API con protección CSRF.
 * 3. Atomic Updates: Maneja estados locales para una UI fluida y consistente.
 */
"use strict";

// ==========================================
// 1. VERIFICACIÓN DE AUTENTICACIÓN
// ==========================================
(function checkAuth() {
  // Definimos la base de la API apuntando a la carpeta EXACTA
  const API_BASE = API_CONFIG.baseUrl;

  // Verificar si hay sesión activa
  secureFetch(`${API_BASE}/me.php`, {
    method: 'GET'
  })
    .then(res => res.json())
    .then(data => {
      // Si no hay sesión, redirigir al login usando la ruta correcta
      if (!data.ok || !data.user) {
        showNotification('⚠️ Debes iniciar sesión para acceder al panel de administración');
        const appUrl = API_CONFIG.appUrl || '../..';
        window.location.href = `${appUrl}/index.html`;
        return;
      }

      // Verificación de rol
      if (data.user.rol !== 'admin') {
        showNotification('⚠️ No tienes permisos de administrador');
        const appUrl = API_CONFIG.appUrl || '../..';
        window.location.href = `${appUrl}/index.html`;
        return;
      }

      // Usuario admin autenticado correctamente
    })
    .catch(err => {
      console.error('Error verificando sesión:', err);
      // En caso de error, sacar al usuario a la ruta correcta
      const appUrl = API_CONFIG.appUrl || '../..';
      window.location.href = `${appUrl}/index.html`;
    });
})();

// ==========================================
// 2. FUNCIÓN DE LOGOUT (CORREGIDO)
// ==========================================
function cerrarSesion() {
  if (!confirm('¿Deseas cerrar sesión?')) return;

  const API_BASE = API_CONFIG.baseUrl;

  secureFetch(`${API_BASE}/logout.php`, {
    method: 'POST'
  })
    .then(res => res.json())
    .then(data => {
      /* API_BASE es config.baseUrl (el del backend).
       * Necesitamos la URL de la APP (frontend root).
       * Como API_BASE termina en /src/backend/api, lo limpiamos o usamos la lógica inversa.
       * O mejor, simplemente ../../index.html ya que estamos en src/admin/
       * Pero lo más robusto es usar API_CONFIG.appUrl si estuviere disponible aquí.
       * admin.js está incluido en admin.html, el cual usa config.js antes. 
       * Así que API_CONFIG existe. */

      const appUrl = API_CONFIG.appUrl || '../..';
      window.location.href = `${appUrl}/index.html`;
    })
    .catch(err => {
      console.error('Error al cerrar sesión:', err);
      sessionStorage.removeItem('user');
      const appUrl = API_CONFIG.appUrl || '../..';
      window.location.href = `${appUrl}/index.html`;
    });
}

// ==========================================
// 3. LÓGICA DEL DASHBOARD (CRUD)
// ==========================================
document.addEventListener("DOMContentLoaded", () => {
  const API_BASE = API_CONFIG.baseUrl;

  // Estado local
  let productos = [];
  let pedidos = [];
  let clientes = [];
  let servicios = [];
  let marcas = [];
  let categorias = [];
  let subcategorias = [];
  let citas = []; // NUEVO


  /* ===== Navegación ===== */
  const links = document.querySelectorAll(".admin-link");
  const sections = document.querySelectorAll(".admin-section");
  if (links.length && sections.length) {
    links.forEach((btn) => {
      btn.addEventListener("click", () => {
        links.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        const target = btn.dataset.target || "";

        sections.forEach((sec) => {
          if (sec.id === target) {
            sec.classList.add("is-active");
            // Recargar datos específicos si es necesario
            if (target === 'citas') renderCitas();
          } else {
            sec.classList.remove("is-active");
          }
        });
      });
    });
  }

  /* ===== Sidebar móvil ===== */
  const sidebar = document.getElementById("adminSidebar");
  const openMenuBtn = document.getElementById("btn-open-admin-menu");
  if (sidebar && openMenuBtn) {
    openMenuBtn.addEventListener("click", () => {
      sidebar.classList.toggle("open");
    });
  }

  /* ===== Modal utils ===== */
  const modalOverlay = document.getElementById("modalOverlay");
  function openModal(id) {
    const el = document.querySelector(id);
    if (!el || !modalOverlay) return;
    el.style.display = "flex";
    requestAnimationFrame(() => el.classList.add("show"));
    modalOverlay.classList.add("show");
  }
  function closeModal(id) {
    const el = document.querySelector(id);
    if (!el || !modalOverlay) return;
    el.classList.remove("show");
    setTimeout(() => { el.style.display = "none"; }, 200);
    modalOverlay.classList.remove("show");
  }
  document.querySelectorAll("[data-close]").forEach((btn) => {
    btn.addEventListener("click", () => closeModal(btn.dataset.close));
  });
  if (modalOverlay) {
    modalOverlay.addEventListener("click", () => {
      document.querySelectorAll(".modal").forEach((m) => {
        if (getComputedStyle(m).display !== "none") closeModal("#" + m.id);
      });
    });
  }

  /* ===== Dashboard ===== */
  async function cargarEstadisticas() {
    try {
      const res = await secureFetch(`${API_BASE}/stats.php`);
      const data = await res.json();
      if (data.ok) {
        const { productos, pedidos, clientes, servicios, ventas_monto, ventas_cant } = data.stats;
        const sp = document.getElementById("statProductos");
        const spe = document.getElementById("statPedidos");
        const sc = document.getElementById("statClientes");
        const ss = document.getElementById("statServicios");
        const svm = document.getElementById("statVentasMonto");
        const svc = document.getElementById("statVentasCant");

        if (sp) sp.textContent = String(productos);
        if (spe) spe.textContent = String(pedidos);
        if (sc) sc.textContent = String(clientes);
        if (ss) ss.textContent = String(servicios);
        if (svc) svc.textContent = String(ventas_cant);
        if (svm) svm.textContent = typeof formatPrice === 'function' ? formatPrice(ventas_monto) : '$' + ventas_monto.toLocaleString();
      }
    } catch (error) {
      console.error("Error cargando estadísticas:", error);
    }
  }

  function renderDashboard() {
    cargarEstadisticas();

    const ctx = document.getElementById("estadosChart");
    if (ctx && typeof Chart !== "undefined") {
      const estados = ["pendiente", "confirmado", "enviado", "cancelado"];
      const mapping = {
        "pendiente": { label: "Pendientes", color: "#FFD700" }, // Oro brillante
        "confirmado": { label: "Confirmados", color: "#AF944F" }, // Oro marca
        "enviado": { label: "Enviados", color: "#2E7D32" }, // Verde esmeralda oscuro
        "cancelado": { label: "Cancelados", color: "#92000A" } // Rojo carmesí marca
      };

      const labels = estados.map(e => mapping[e].label);
      const counts = estados.map(e => pedidos.filter(p => p.estado === e).length);
      const colors = estados.map(e => mapping[e].color);

      if (ctx._chartInstance) ctx._chartInstance.destroy();
      ctx._chartInstance = new Chart(ctx, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [{
            label: "Cantidad de Pedidos",
            data: counts,
            backgroundColor: colors,
            borderRadius: 6,
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(0,0,0,0.05)' },
              ticks: { font: { family: 'Montserrat' } }
            },
            x: {
              grid: { display: false },
              ticks: { font: { family: 'Montserrat', weight: '600' } }
            }
          }
        }
      });
    }
  }

  /* =======================
   * PRODUCTOS
   * ======================= */
  const tbodyProductos = document.getElementById("tbodyProductos");
  const buscarProducto = document.getElementById("buscarProducto");
  const btnNuevoProducto = document.getElementById("btnNuevoProducto");

  async function cargarProductos() {
    try {
      const res = await secureFetch(`${API_BASE}/productos.php`);
      const data = await res.json();
      if (data.ok) {
        productos = data.productos.map(p => ({
          id: p.id_producto,
          nombre: p.nom_producto,
          precio: parseFloat(p.precio),
          stock: parseInt(p.stock),
          imagen: p.url_imagen || 'https://via.placeholder.com/90x90?text=Producto',
          marca: p.nom_marca || 'N/A',
          categoria: p.nom_categoria || 'N/A',
          descripcion: p.descripcion || '',
          id_marca: p.id_marca,
          id_categoria: p.id_categoria,
          id_subcategoria: p.id_subcategoria
        }));
        drawProductos();
      }
    } catch (err) {
      console.error('Error cargando productos:', err);
      // showNotification('Error al cargar productos'); // Comentado para no spammear alertas si falla la primera carga
    }
  }

  function drawProductos(list = productos) {
    if (!tbodyProductos) return;
    tbodyProductos.innerHTML = list.map((p) => `
      <tr>
        <td><img src="${p.imagen}" alt="${p.nombre}"></td>
        <td>${p.nombre}</td>
        <td>$${Number(p.precio).toFixed(2)}</td>
        <td>${p.stock}</td>
        <td>${p.marca}</td>
        <td class="actions">
          <button class="button button-outline" onclick="editarProducto(${p.id})"><i class="fas fa-pen"></i></button>
          <button class="button button-danger" onclick="eliminarProducto(${p.id})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`).join("");
  }

  if (buscarProducto) {
    buscarProducto.addEventListener("input", (e) => {
      const q = String(e.target.value || "").toLowerCase();
      drawProductos(productos.filter((p) => p.nombre.toLowerCase().includes(q)));
    });
  }

  const modalProducto = document.getElementById("modalProducto");
  const formProducto = document.getElementById("formProducto");
  const pId = document.getElementById("pId");
  const pNombre = document.getElementById("pNombre");
  const pDescripcion = document.getElementById("pDescripcion");
  const pPrecio = document.getElementById("pPrecio");
  const pStock = document.getElementById("pStock");
  const pImagen = document.getElementById("pImagen");
  const pMarca = document.getElementById("pMarca");
  const pCategoria = document.getElementById("pCategoria");
  const pSubcategoria = document.getElementById("pSubcategoria");

  async function cargarCatalogosProducto() {
    try {
      const [resMarcas, resCat] = await Promise.all([
        secureFetch(`${API_BASE}/catalogos.php?tipo=marcas`),
        secureFetch(`${API_BASE}/catalogos.php?tipo=categorias`)
      ]);
      const [dataMarcas, dataCat] = [await resMarcas.json(), await resCat.json()];
      if (dataMarcas.ok) {
        pMarca.innerHTML = '<option value="">Seleccione...</option>' +
          dataMarcas.marcas.map(m => `<option value="${m.id_marca}">${m.nom_marca}</option>`).join('');
      }
      if (dataCat.ok) {
        pCategoria.innerHTML = '<option value="">Seleccione...</option>' +
          dataCat.categorias.map(c => `<option value="${c.id_categoria}">${c.nom_categoria}</option>`).join('');
      }
    } catch (err) {
      console.error('Error cargando catálogos (producto):', err);
    }
  }

  if (pCategoria) {
    pCategoria.addEventListener('change', async () => {
      const idCat = pCategoria.value;
      if (!idCat) {
        pSubcategoria.innerHTML = '<option value="">Seleccione...</option>';
        return;
      }
      try {
        const res = await secureFetch(`${API_BASE}/catalogos.php?tipo=subcategorias&id_categoria=${idCat}`);
        const data = await res.json();
        if (data.ok) {
          pSubcategoria.innerHTML = '<option value="">Seleccione...</option>' +
            data.subcategorias.map(s => `<option value="${s.id_subcategoria}">${s.nom_subcategoria}</option>`).join('');
        }
      } catch (err) {
        console.error('Error cargando subcategorías:', err);
      }
    });
  }

  if (btnNuevoProducto && formProducto) {
    btnNuevoProducto.addEventListener("click", async () => {
      const title = document.getElementById("tituloModalProducto");
      if (title) title.textContent = "Nuevo Producto";
      formProducto.reset();
      formProducto.dataset.editing = "";
      await cargarCatalogosProducto();
      const maxId = productos.length > 0 ? Math.max(...productos.map(p => p.id)) : 0;
      pId.value = maxId + 1;
      openModal("#modalProducto");
    });
  }

  function editarProducto(id) {
    if (!formProducto) return;
    const prod = productos.find((p) => p.id === id);
    if (!prod) return;
    cargarCatalogosProducto().then(() => {
      const title = document.getElementById("tituloModalProducto");
      if (title) title.textContent = "Editar Producto";
      pId.value = prod.id; pId.readOnly = true;
      pNombre.value = prod.nombre;
      pDescripcion.value = prod.descripcion || '';
      pPrecio.value = prod.precio;
      pStock.value = prod.stock || 0;
      pImagen.value = prod.imagen;
      setTimeout(() => {
        if (prod.id_marca) pMarca.value = prod.id_marca;
        if (prod.id_categoria) {
          pCategoria.value = prod.id_categoria;
          pCategoria.dispatchEvent(new Event('change'));
          setTimeout(() => { if (prod.id_subcategoria) pSubcategoria.value = prod.id_subcategoria; }, 500);
        }
      }, 300);
      formProducto.dataset.editing = String(id);
      openModal("#modalProducto");
    });
  }

  async function eliminarProducto(id) {
    if (!confirm("¿Eliminar producto?")) return;
    try {
      const res = await secureFetch(`${API_BASE}/productos.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_producto: id })
      });
      const data = await res.json();
      if (data.ok) {
        showNotification('Producto eliminado correctamente');
        await cargarProductos();
        renderDashboard();
      } else {
        showNotification(data.msg || 'Error al eliminar producto');
      }
    } catch (err) {
      console.error(err);
      showNotification('Error al eliminar producto');
    }
  }

  if (formProducto) {
    formProducto.addEventListener("submit", async (e) => {
      e.preventDefault();
      const payload = {
        id_producto: Number(pId.value),
        id_marca: Number(pMarca.value),
        nom_producto: pNombre.value.trim(),
        descripcion: pDescripcion.value.trim(),
        precio: Number(pPrecio.value),
        id_categoria: Number(pCategoria.value),
        id_subcategoria: Number(pSubcategoria.value),
        stock: Number(pStock.value),
        url_imagen: pImagen.value.trim() || null
      };
      const editing = formProducto.dataset.editing;
      try {
        const method = editing ? 'PUT' : 'POST';
        const res = await secureFetch(`${API_BASE}/productos.php`, {
          method, headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
          showNotification(data.msg || 'Guardado');
          closeModal("#modalProducto");
          pId.readOnly = false;
          await cargarProductos();
          renderDashboard();
        } else {
          showNotification(data.msg || 'Error al guardar producto');
        }
      } catch (err) {
        console.error(err);
        showNotification('Error guardando producto: ' + err.message);
      }
    });
  }

  window.editarProducto = editarProducto;
  window.eliminarProducto = eliminarProducto;

  /* =======================
   * PEDIDOS & CLIENTES
   * ======================= */
  const tbodyPedidos = document.getElementById("tbodyPedidos");
  const tbodyClientes = document.getElementById("tbodyClientes");

  async function cargarPedidos() {
    try {
      const res = await secureFetch(`${API_BASE}/pedidos.php`, {
        method: 'GET'
      });
      const data = await res.json();

      if (data.ok) {
        pedidos = data.pedidos.map(p => ({
          id: p.id_orden,
          cliente: p.cliente, // Viene del JOIN con tab_Usuarios
          email: p.email_cliente,
          estado: p.estado_orden,
          total: parseFloat(p.total_orden),
          fecha: p.fecha,
          tiene_comprobante: p.tiene_comprobante == 1
        }));

        drawPedidos();
        renderDashboard(); // Actualizar contadores
      }
    } catch (err) {
      console.error('Error cargando pedidos:', err);
    }
  }

  function drawPedidos() {
    const tbodyPedidos = document.getElementById("tbodyPedidos");
    if (!tbodyPedidos) return;

    if (pedidos.length === 0) {
      tbodyPedidos.innerHTML = '<tr><td colspan="5" style="text-align:center">No hay pedidos registrados</td></tr>';
      return;
    }

    // Función auxiliar para color de badge
    const getBadgeClass = (estado) => {
      const est = estado.toLowerCase();
      if (est.includes('cancelado')) return 'cancelado'; // Rojo
      if (est.includes('enviado')) return 'enviado'; // Verde
      if (est.includes('confirmado')) return 'confirmado'; // Azul
      return 'pendiente'; // Amarillo por defecto
    };

    tbodyPedidos.innerHTML = pedidos
      .map((p) => `
        <tr>
          <td>#${p.id}</td>
          <td>
            ${p.cliente}<br>
            <small style="color:#888">${p.email}</small>
          </td>
          <td>${p.fecha}</td>
          <td>
            <select class="form-control select-estado" onchange="cambiarEstadoPedido(${p.id}, this.value)" 
              style="padding: 5px; border-radius: 4px; border: 1px solid #ddd; font-weight: bold;
              background: ${p.estado === 'cancelado' ? '#dc3545' : p.estado === 'confirmado' ? '#007bff' : p.estado === 'enviado' ? '#28a745' : '#ffc107'};
              color: ${p.estado === 'pendiente' ? '#000' : '#fff'};">
              <option value="pendiente" ${p.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
              <option value="confirmado" ${p.estado === 'confirmado' ? 'selected' : ''}>Confirmado</option>
              <option value="enviado" ${p.estado === 'enviado' ? 'selected' : ''}>Enviado</option>
              <option value="cancelado" ${p.estado === 'cancelado' ? 'selected' : ''}>Cancelado</option>
            </select>
          </td>
          <td style="text-align:center;">
             ${p.tiene_comprobante
          ? `<a href="../backend/api/get_comprobante.php?id_orden=${p.id}" target="_blank" class="button button-small button-outline" title="Ver Comprobante"><i class="fas fa-file-invoice-dollar"></i></a>`
          : '<span style="color:#ccc;font-size:0.8em;">-</span>'}
          </td>
          <td style="font-weight:bold">$${p.total.toFixed(2)}</td>
        </tr>`)
      .join("");
  };

  window.cambiarEstadoPedido = async function (id_orden, nuevo_estado) {
    if (!confirm(`¿Deseas cambiar el estado a ${nuevo_estado}?`)) return;

    try {
      const res = await secureFetch(`${API_BASE}/pedidos.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_orden, estado: nuevo_estado })
      });

      const data = await res.json();
      if (data.ok) {
        showNotification(`✅ Pedido #${id_orden} actualizado a ${nuevo_estado}`);
        cargarPedidos();
      } else {
        showNotification(`❌ Error: ${data.msg}`);
      }
    } catch (err) {
      console.error('Error al cambiar estado:', err);
      showNotification('❌ Error de conexión');
    }
  };

  /* ==========================
   * FUNCIÓN CARGAR CLIENTES 
   * ========================== */
  async function cargarClientes() {
    try {
      const res = await secureFetch(`${API_BASE}/clientes.php`, {
        method: 'GET'
      });
      const data = await res.json();

      if (data.ok) {
        // Mapeamos los datos de la BD a la estructura que usa la tabla
        clientes = data.clientes.map(c => ({
          id: c.id_usuario,
          nombre: c.nom_usuario,
          email: c.correo_usuario,
          tel: c.num_telefono_usuario || 'N/A',
          activo: c.activo,
          fecha: c.fecha_registro
        }));

        drawClientes();
        renderDashboard(); // Para actualizar el contador del dashboard
      }
    } catch (err) {
      console.error('Error cargando clientes:', err);
    }
  }

  function drawClientes() {
    const tbodyClientes = document.getElementById("tbodyClientes");
    if (!tbodyClientes) return;

    if (clientes.length === 0) {
      tbodyClientes.innerHTML = '<tr><td colspan="4" style="text-align:center">No hay clientes registrados</td></tr>';
      return;
    }

    tbodyClientes.innerHTML = clientes
      .map((c) => `
        <tr>
          <td>
            <strong>${c.nombre}</strong><br>
            <small style="color:#888">ID: ${c.id}</small>
          </td>
          <td>${c.email}</td>
          <td>${c.tel}</td>
          <td>
            <span class="badge ${c.activo ? 'active' : 'inactive'}">
                ${c.activo ? 'Activo' : 'Inactivo'}
            </span>
          </td>
        </tr>`)
      .join("");
  }
  /* =======================
   * SERVICIOS
   * ======================= */
  const tbodyServicios = document.getElementById("tbodyServicios");
  const btnNuevoServicio = document.getElementById("btnNuevoServicio");
  const formServicio = document.getElementById("formServicio");
  const sId = document.getElementById("sId");
  const sNombre = document.getElementById("sNombre");
  const sDescripcion = document.getElementById("sDescripcion");
  const sPrecio = document.getElementById("sPrecio");
  const sDuracion = document.getElementById("sDuracion");
  const buscarServicio = document.getElementById("buscarServicio");

  async function cargarServicios() {
    try {
      const res = await secureFetch(`${API_BASE}/servicios.php`);
      const data = await res.json();
      if (data.ok) {
        servicios = data.servicios;
        drawServicios();
      }
    } catch (err) {
      console.error('Error cargando servicios:', err);
      // showNotification('Error al cargar servicios');
    }
  }

  function drawServicios(list = servicios) {
    if (!tbodyServicios) return;
    tbodyServicios.innerHTML = list.map((s) => `
      <tr>
        <td>${s.id_servicio}</td>
        <td>${s.nom_servicio}</td>
        <td>$${Number(s.precio_servicio).toFixed(2)}</td>
        <td>${s.duracion_estimada} min</td>
        <td class="actions">
          <button class="button button-outline" onclick="editarServicio(${s.id_servicio})"><i class="fas fa-pen"></i></button>
          <button class="button button-danger" onclick="eliminarServicio(${s.id_servicio})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`).join("");
  }

  if (buscarServicio) {
    buscarServicio.addEventListener("input", (e) => {
      const q = String(e.target.value || "").toLowerCase();
      drawServicios(servicios.filter((s) => s.nom_servicio.toLowerCase().includes(q)));
    });
  }

  if (btnNuevoServicio && formServicio) {
    btnNuevoServicio.addEventListener("click", () => {
      const title = document.getElementById("tituloModalServicio");
      if (title) title.textContent = "Nuevo Servicio";
      formServicio.reset();
      formServicio.dataset.editing = "";
      const maxId = servicios.length > 0 ? Math.max(...servicios.map(s => s.id_servicio)) : 100;
      sId.value = maxId + 1;
      openModal("#modalServicio");
    });
  }

  function editarServicio(id) {
    if (!formServicio) return;
    const s = servicios.find((x) => x.id_servicio === id);
    if (!s) return;
    const title = document.getElementById("tituloModalServicio");
    if (title) title.textContent = "Editar Servicio";
    sId.value = String(s.id_servicio); sId.readOnly = true;
    sNombre.value = s.nom_servicio;
    sDescripcion.value = s.descripcion;
    sPrecio.value = String(s.precio_servicio);
    sDuracion.value = String(s.duracion_estimada);
    formServicio.dataset.editing = String(id);
    openModal("#modalServicio");
  }

  if (formServicio) {
    formServicio.addEventListener("submit", async (e) => {
      e.preventDefault();
      const payload = {
        id_servicio: Number(sId.value),
        nom_servicio: sNombre.value.trim(),
        descripcion: sDescripcion.value.trim(),
        precio_servicio: Number(sPrecio.value),
        duracion_estimada: Number(sDuracion.value)
      };
      const editing = formServicio.dataset.editing;
      try {
        const method = editing ? 'PUT' : 'POST';
        const res = await secureFetch(`${API_BASE}/servicios.php`, {
          method, headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
          showNotification(data.msg || 'Guardado');
          closeModal("#modalServicio");
          sId.readOnly = false;
          await cargarServicios();
          renderDashboard();
        } else {
          showNotification(data.msg || 'Error al guardar servicio');
        }
      } catch (err) {
        console.error(err);
        showNotification("Error guardando el servicio: " + err.message);
      }
    });
  }

  async function eliminarServicio(id) {
    if (!confirm("¿Eliminar servicio?")) return;
    try {
      const res = await secureFetch(`${API_BASE}/servicios.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_servicio: id })
      });
      const data = await res.json();
      if (data.ok) {
        showNotification('Servicio eliminado correctamente');
        await cargarServicios();
        renderDashboard();
      } else {
        showNotification(data.msg || 'Error al eliminar servicio');
      }
    } catch (err) {
      console.error(err);
      showNotification("Error eliminando servicio: " + err.message);
    }
  }

  window.editarServicio = editarServicio;
  window.eliminarServicio = eliminarServicio;

  /* =======================
   * MARCAS
   * ======================= */
  const tbodyMarcas = document.getElementById("tbodyMarcas");
  const btnNuevaMarca = document.getElementById("btnNuevaMarca");
  const formMarca = document.getElementById("formMarca");
  const mId = document.getElementById("mId");
  const mNombre = document.getElementById("mNombre");
  const mActiva = document.getElementById("mActiva");
  const buscarMarca = document.getElementById("buscarMarca");

  async function cargarMarcas() {
    try {
      // Usar el endpoint de administración (marcas.php) para obtener TODAS las marcas (incluidas inactivas)
      const res = await secureFetch(`${API_BASE}/marcas.php`);
      const data = await res.json();
      if (data.ok) {
        // El endpoint devuelve: id_marca, nom_marca, estado_marca
        marcas = data.marcas.map(x => ({
          id_marca: x.id_marca,
          nom_marca: x.nom_marca,
          estado_marca: x.estado_marca // Booleano o entero, se usará para el badge
        }));
        drawMarcas();
      }
    } catch (e) {
      console.error('Error cargando marcas:', e);
    }
  }

  function drawMarcas(list = marcas) {
    if (!tbodyMarcas) return;
    tbodyMarcas.innerHTML = list.map(m => `
      <tr>
        <td>${m.id_marca}</td>
        <td>${m.nom_marca}</td>
        <td><span class="badge ${m.estado_marca ? 'active' : 'inactive'}">${m.estado_marca ? 'Activa' : 'Inactiva'}</span></td>
        <td class="actions">
          <button class="button button-outline" onclick="editarMarca(${m.id_marca})"><i class="fas fa-pen"></i></button>
          <button class="button button-danger" onclick="eliminarMarca(${m.id_marca})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`).join('');
  }

  if (buscarMarca) {
    buscarMarca.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      drawMarcas(marcas.filter(m => m.nom_marca.toLowerCase().includes(q)));
    });
  }

  if (btnNuevaMarca && formMarca) {
    btnNuevaMarca.addEventListener('click', () => {
      document.getElementById("tituloModalMarca").textContent = "Nueva Marca";
      formMarca.reset();
      formMarca.dataset.editing = "";
      const max = marcas.length ? Math.max(...marcas.map(m => m.id_marca)) : 0;
      mId.value = max + 1;
      mActiva.checked = true;
      openModal("#modalMarca");
    });
  }

  function editarMarca(id) {
    const m = marcas.find(x => x.id_marca === id);
    if (!m) return;
    document.getElementById("tituloModalMarca").textContent = "Editar Marca";
    mId.value = m.id_marca; mId.readOnly = true;
    mNombre.value = m.nom_marca;
    mActiva.checked = !!m.estado_marca;
    formMarca.dataset.editing = String(id);
    openModal("#modalMarca");
  }

  async function eliminarMarca(id) {
    if (!confirm('¿Eliminar marca?')) return;
    try {
      const res = await secureFetch(`${API_BASE}/marcas.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_marca: id })
      });
      const data = await res.json();
      if (data.ok) {
        showNotification('Marca eliminada');
        await cargarMarcas();
        await cargarCatalogosProducto();
      } else {
        showNotification(data.msg || 'Error al eliminar marca');
      }
    } catch (e) {
      console.error(e); showNotification('Error al eliminar marca');
    }
  }

  if (formMarca) {
    formMarca.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        id_marca: Number(mId.value),
        nom_marca: mNombre.value.trim(),
        estado_marca: mActiva.checked ? 1 : 0
      };
      const editing = formMarca.dataset.editing;
      try {
        const method = editing ? 'PUT' : 'POST';
        const res = await secureFetch(`${API_BASE}/marcas.php`, {
          method, headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
          showNotification(data.msg || 'Guardado');
          closeModal("#modalMarca");
          mId.readOnly = false;
          await cargarMarcas();
          await cargarCatalogosProducto();
        } else {
          showNotification(data.msg || 'Error al guardar marca');
        }
      } catch (e2) {
        console.error(e2); showNotification('Error al guardar marca: ' + e2.message);
      }
    });
  }

  window.editarMarca = editarMarca;
  window.eliminarMarca = eliminarMarca;

  /* =======================
   * CATEGORÍAS
   * ======================= */
  const tbodyCategorias = document.getElementById("tbodyCategorias");
  const btnNuevaCategoria = document.getElementById("btnNuevaCategoria");
  const formCategoria = document.getElementById("formCategoria");
  const cId = document.getElementById("cId");
  const cNombre = document.getElementById("cNombre");
  const cDescripcion = document.getElementById("cDescripcion");
  const cActiva = document.getElementById("cActiva");
  const buscarCategoria = document.getElementById("buscarCategoria");

  async function cargarCategorias() {
    try {
      const res = await secureFetch(`${API_BASE}/categorias.php`);
      const data = await res.json();
      if (data.ok) {
        categorias = data.categorias.map(c => ({
          id_categoria: c.id_categoria,
          nom_categoria: c.nom_categoria,
          descripcion_categoria: c.descripcion_categoria ?? '',
          estado: c.estado
        }));
        drawCategorias();
        refrescarSelectsCategorias();
      }
    } catch (e) {
      console.error('Error cargando categorías:', e);
    }
  }

  /* =======================
   * CONFIGURACIÓN
   * ======================= */
  const formConfigTienda = document.getElementById('formConfigTienda');
  const formConfigAdmin = document.getElementById('formConfigAdmin');

  async function cargarConfiguracion() {
    try {
      const res = await secureFetch(`${API_BASE}/admin_settings.php`);
      const data = await res.json();
      if (data.ok) {
        // Tienda
        const store = data.store || { nombre: 'RD-Watch', moneda: 'USD' };
        const elNombre = document.getElementById('tiendaNombre');
        const elMoneda = document.getElementById('tiendaMoneda');
        if (elNombre) elNombre.value = store.nombre;
        if (elMoneda) elMoneda.value = store.moneda;

        // Admin
        const admin = data.admin || { usuario: 'admin' };
        const elUser = document.getElementById('adminUsuario');
        if (elUser) elUser.value = admin.usuario;
      }
    } catch (e) {
      console.error('Error cargando configuración:', e);
    }
  }

  if (formConfigTienda) {
    formConfigTienda.addEventListener('submit', async (e) => {
      e.preventDefault();
      const nombre = document.getElementById('tiendaNombre').value;
      const moneda = document.getElementById('tiendaMoneda').value;

      try {
        const res = await secureFetch(`${API_BASE}/admin_settings.php?action=update_store`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ nombre, moneda })
        });
        const data = await res.json();
        showNotification(data.msg || (data.ok ? 'Guardado' : 'Error'));
      } catch (e) {
        console.error(e);
        showNotification('Error al guardar configuración de tienda');
      }
    });
  }

  if (formConfigAdmin) {
    formConfigAdmin.addEventListener('submit', async (e) => {
      e.preventDefault();
      const usuario = document.getElementById('adminUsuario').value;
      const currentPass = document.getElementById('adminCurrentPass').value;
      const newPass = document.getElementById('adminNewPass').value;

      if (!currentPass) {
        showNotification('Debes ingresar tu contraseña actual para confirmar cambios.');
        return;
      }

      try {
        const res = await secureFetch(`${API_BASE}/admin_settings.php?action=update_admin`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            usuario,
            current_pass: currentPass,
            new_pass: newPass
          })
        });
        const data = await res.json();
        if (data.ok) {
          showNotification('Cuenta actualizada correctamente.');
          document.getElementById('adminCurrentPass').value = '';
          document.getElementById('adminNewPass').value = '';
          // Opcional: recargar nombre de usuario mostrado en la UI si lo hubiera
        } else {
          showNotification(data.msg || 'Error al actualizar cuenta');
        }
      } catch (e) {
        console.error(e);
        showNotification('Error al actualizar cuenta de administrador');
      }
    });
  }


  function drawCategorias(list = categorias) {
    if (!tbodyCategorias) return;
    tbodyCategorias.innerHTML = list.map(c => `
      <tr>
        <td>${c.id_categoria}</td>
        <td>${c.nom_categoria}</td>
        <td><span class="badge ${c.estado ? 'active' : 'inactive'}">${c.estado ? 'Activa' : 'Inactiva'}</span></td>
        <td class="actions">
          <button class="button button-outline" onclick="editarCategoria(${c.id_categoria})"><i class="fas fa-pen"></i></button>
          <button class="button button-danger" onclick="eliminarCategoria(${c.id_categoria})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`).join('');
  }

  if (buscarCategoria) {
    buscarCategoria.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      drawCategorias(categorias.filter(c =>
        c.nom_categoria.toLowerCase().includes(q) ||
        (c.descripcion_categoria || '').toLowerCase().includes(q)
      ));
    });
  }

  if (btnNuevaCategoria && formCategoria) {
    btnNuevaCategoria.addEventListener('click', () => {
      document.getElementById("tituloModalCategoria").textContent = "Nueva Categoría";
      formCategoria.reset();
      formCategoria.dataset.editing = "";
      const max = categorias.length ? Math.max(...categorias.map(c => c.id_categoria)) : 0;
      cId.value = max + 1;
      cActiva.checked = true;
      openModal("#modalCategoria");
    });
  }

  function editarCategoria(id) {
    const c = categorias.find(x => x.id_categoria === id);
    if (!c) return;
    document.getElementById("tituloModalCategoria").textContent = "Editar Categoría";
    cId.value = c.id_categoria; cId.readOnly = true;
    cNombre.value = c.nom_categoria;
    cDescripcion.value = c.descripcion_categoria || '';
    cActiva.checked = c.estado !== 0;
    formCategoria.dataset.editing = String(id);
    openModal("#modalCategoria");
  }

  async function eliminarCategoria(id) {
    if (!confirm('Eliminar categoría y sus subcategorías asociadas?')) return;
    try {
      const res = await secureFetch(`${API_BASE}/categorias.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_categoria: id })
      });
      const data = await res.json();
      if (data.ok) {
        showNotification('Categoría eliminada');
        await cargarCategorias();
        await cargarSubcategorias(getFiltroCat());
        await cargarCatalogosProducto();
      } else {
        showNotification(data.msg || 'Error al eliminar categoría');
      }
    } catch (e) {
      console.error(e); showNotification('Error al eliminar categoría');
    }
  }

  if (formCategoria) {
    formCategoria.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        id_categoria: Number(cId.value),
        nom_categoria: cNombre.value.trim(),
        descripcion_categoria: cDescripcion.value.trim(),
        estado: cActiva.checked ? 1 : 0
      };
      const editing = formCategoria.dataset.editing;
      try {
        const method = editing ? 'PUT' : 'POST';
        const res = await secureFetch(`${API_BASE}/categorias.php`, {
          method, headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
          showNotification(data.msg || 'Guardado');
          closeModal("#modalCategoria");
          cId.readOnly = false;
          await cargarCategorias();
          await cargarCatalogosProducto();
        } else {
          showNotification(data.msg || 'Error al guardar categoría');
        }
      } catch (e2) { console.error(e2); showNotification('Error al guardar categoría: ' + e2.message); }
    });
  }

  window.editarCategoria = editarCategoria;
  window.eliminarCategoria = eliminarCategoria;

  /* =======================
   * SUBCATEGORÍAS 
   * ======================= */
  const tbodySubcategorias = document.getElementById("tbodySubcategorias");
  const btnNuevaSubcategoria = document.getElementById("btnNuevaSubcategoria");
  const formSubcategoria = document.getElementById("formSubcategoria");
  const scId = document.getElementById("scId");
  const scCategoria = document.getElementById("scCategoria");
  const scNombre = document.getElementById("scNombre");
  const filtroCatSub = document.getElementById("filtroCatSub");
  const buscarSubcategoria = document.getElementById("buscarSubcategoria");

  function getFiltroCat() {
    return filtroCatSub ? Number(filtroCatSub.value) || null : null;
  }

  async function cargarSubcategorias(catId = null) {
    try {
      const res = await secureFetch(`${API_BASE}/categorias.php?action=subcategoria`);
      const data = await res.json();

      if (data.ok) {
        subcategorias = data.subcategorias.map(s => ({
          id_subcategoria: s.id_subcategoria,
          nom_subcategoria: s.nom_subcategoria,
          id_categoria: s.id_categoria,
          nom_categoria: s.nom_categoria || '',
          estado: s.estado ?? true
        }));
        drawSubcategorias();
      } else {
        console.error('Error cargando subcategorías:', data.msg);
      }
    } catch (e) {
      console.error('Error cargando subcategorías:', e);
    }
  }

  function drawSubcategorias(list = subcategorias) {
    if (!tbodySubcategorias) return;

    const q = (buscarSubcategoria?.value || '').toLowerCase();
    const f = getFiltroCat();

    const filtered = list.filter(s =>
      (!f || s.id_categoria === f) &&
      (s.nom_subcategoria.toLowerCase().includes(q) ||
        (s.nom_categoria || '').toLowerCase().includes(q))
    );

    tbodySubcategorias.innerHTML = filtered.map(s => `
    <tr>
      <td>${s.id_subcategoria}</td>
      <td>${s.nom_categoria}</td>
      <td>${s.nom_subcategoria}</td>
      <td class="actions">
        <button class="button button-outline" onclick="editarSubcategoria(${s.id_categoria}, ${s.id_subcategoria})">
          <i class="fas fa-pen"></i>
        </button>
        <button class="button button-danger" onclick="eliminarSubcategoria(${s.id_categoria}, ${s.id_subcategoria})">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    </tr>`).join('');
  }

  function refrescarSelectsCategorias() {
    const opts = ['<option value="">Seleccione...</option>'].concat(
      categorias.map(c => `<option value="${c.id_categoria}">${c.nom_categoria}</option>`)
    ).join('');

    if (scCategoria) scCategoria.innerHTML = opts;

    if (filtroCatSub) {
      const all = '<option value="">Todas las categorías</option>' +
        categorias.map(c => `<option value="${c.id_categoria}">${c.nom_categoria}</option>`).join('');
      filtroCatSub.innerHTML = all;
    }
  }

  if (btnNuevaSubcategoria && formSubcategoria) {
    btnNuevaSubcategoria.addEventListener('click', () => {
      document.getElementById("tituloModalSubcategoria").textContent = "Nueva Subcategoría";
      formSubcategoria.reset();
      formSubcategoria.dataset.editing = "";
      formSubcategoria.dataset.editingCat = "";

      const max = subcategorias.length ? Math.max(...subcategorias.map(s => s.id_subcategoria)) : 0;
      scId.value = max + 1;
      scId.readOnly = false;

      openModal("#modalSubcategoria");
    });
  }

  function editarSubcategoria(idCat, idSub) {
    const s = subcategorias.find(x =>
      x.id_categoria === idCat && x.id_subcategoria === idSub
    );
    if (!s) return;

    document.getElementById("tituloModalSubcategoria").textContent = "Editar Subcategoría";
    scId.value = s.id_subcategoria;
    scId.readOnly = true;
    scNombre.value = s.nom_subcategoria;
    scCategoria.value = s.id_categoria;

    formSubcategoria.dataset.editing = String(idSub);
    formSubcategoria.dataset.editingCat = String(idCat);

    openModal("#modalSubcategoria");
  }

  async function eliminarSubcategoria(idCat, idSub) {
    if (!confirm('¿Eliminar subcategoría?')) return;

    try {
      const res = await secureFetch(`${API_BASE}/categorias.php?action=subcategoria`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id_categoria: idCat,
          id_subcategoria: idSub
        })
      });

      const data = await res.json();

      if (data.ok) {
        showNotification('Subcategoría eliminada correctamente');
        await cargarSubcategorias();
        await cargarCatalogosProducto();
      } else {
        showNotification(data.msg || 'Error al eliminar subcategoría');
      }
    } catch (e) {
      console.error(e);
      showNotification('Error al eliminar subcategoría: ' + e.message);
    }
  }

  if (buscarSubcategoria) {
    buscarSubcategoria.addEventListener('input', () => drawSubcategorias());
  }

  if (filtroCatSub) {
    filtroCatSub.addEventListener('change', () => drawSubcategorias());
  }

  if (formSubcategoria) {
    formSubcategoria.addEventListener('submit', async (e) => {
      e.preventDefault();

      // Validaciones
      const idCat = Number(scCategoria.value);
      const idSub = Number(scId.value);
      const nombre = scNombre.value.trim();

      if (!idCat) {
        showNotification('Debe seleccionar una categoría');
        return;
      }

      if (!idSub || idSub <= 0) {
        showNotification('ID de subcategoría inválido');
        return;
      }

      if (!nombre) {
        showNotification('El nombre es requerido');
        return;
      }

      const payload = {
        id_categoria: idCat,
        id_subcategoria: idSub,
        nom_subcategoria: nombre
      };

      const editing = formSubcategoria.dataset.editing;

      try {
        const method = editing ? 'PUT' : 'POST';
        const res = await secureFetch(`${API_BASE}/categorias.php?action=subcategoria`, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (data.ok) {
          showNotification(data.msg || 'Subcategoría guardada correctamente');
          closeModal("#modalSubcategoria");
          scId.readOnly = false;
          await cargarSubcategorias();
          await cargarCatalogosProducto();
        } else {
          showNotification(data.msg || 'Error al guardar subcategoría');
        }
      } catch (e2) {
        console.error(e2);
        showNotification('Error al guardar subcategoría: ' + e2.message);
      }
    });
  }

  window.editarSubcategoria = editarSubcategoria;
  window.eliminarSubcategoria = eliminarSubcategoria;




  /* =======================
   * GESTIÓN DE CITAS
   * ======================= */
  async function cargarCitasAdmin() {
    try {
      const res = await secureFetch(`${API_BASE}/citas.php`);
      const data = await res.json();
      if (data.ok) {
        citas = data.citas;
        renderCitas();
      }
    } catch (error) {
      console.error("Error al cargar citas:", error);
    }
  }

  function renderCitas() {
    const tbody = document.getElementById('tbodyCitas');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!citas || citas.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay citas registradas.</td></tr>';
      return;
    }

    citas.forEach(cita => {
      const tr = document.createElement('tr');

      let badgeClass = 'secondary';
      const est = (cita.estado || '').toLowerCase();

      if (est === 'confirmada' || est === 'completada') badgeClass = 'success';
      else if (est === 'cancelada') badgeClass = 'danger';
      else if (est === 'pendiente') badgeClass = 'warning';

      tr.innerHTML = `
            <td>#${cita.id_reserva}</td>
            <td>
                <strong>${cita.cliente || 'Usuario'}</strong><br>
                <small>ID: ${cita.id_usuario}</small>
            </td>
            <td>${cita.nombre_servicio || 'Servicio'}</td>
            <td>${cita.fecha_preferida}</td>
            <td><span class="badge ${cita.prioridad === 'alta' ? 'danger' : 'primary'}">${cita.prioridad}</span></td>
            <td><span class="badge ${badgeClass}">${cita.estado}</span></td>
            <td>
                ${cita.tiene_foto ? `
                    <a href="../backend/api/get_foto_cita.php?id_reserva=${cita.id_reserva}" 
                       target="_blank"
                       style="color: var(--primary-color); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"
                       title="Ver foto adjunta">
                        <i class="fas fa-image"></i> Ver
                    </a>
                ` : '<span style="color: #999;">Sin foto</span>'}
            </td>
            <td>
                <div class="action-buttons">
                    ${est === 'pendiente' ? `
                        <button class="button button-icon" style="color:var(--success)" title="Confirmar" onclick="cambiarEstadoCita(${cita.id_reserva}, 'confirmada')">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                    ${est !== 'completada' && est !== 'cancelada' ? `
                         <button class="button button-icon" style="color:var(--primary)" title="Completar" onclick="cambiarEstadoCita(${cita.id_reserva}, 'completada')">
                            <i class="fas fa-check-double"></i>
                        </button>
                    ` : ''}
                    ${est !== 'cancelada' ? `
                        <button class="button button-icon" style="color:var(--danger)" title="Cancelar" onclick="cambiarEstadoCita(${cita.id_reserva}, 'cancelada')">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        `;
      tbody.appendChild(tr);
    });
  }

  window.cambiarEstadoCita = async function (idReserva, nuevoEstado) {
    if (!confirm(`¿Estás seguro de marcar esta cita como "${nuevoEstado}"?`)) return;

    try {
      const res = await secureFetch(`${API_BASE}/citas.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          id_reserva: idReserva,
          estado: nuevoEstado
        })
      });

      const data = await res.json();
      if (data.ok) {
        showNotification(`✅ Cita actualizada a: ${nuevoEstado}`);
        cargarCitasAdmin(); // Recargar lista
      } else {
        showNotification(`❌ Error: ${data.msg}`);
      }
    } catch (error) {
      console.error("Error al actualizar cita:", error);
      showNotification("❌ Error de conexión");
    }
  };

  /* =======================
   * INIT
   * ======================= */
  async function init() {
    await Promise.all([
      cargarProductos(),
      cargarServicios(),
      cargarMarcas(),
      cargarCategorias(),
      cargarClientes(),
      cargarPedidos(),
      cargarCitasAdmin(), // NUEVO
      cargarConfiguracion(),
      cargarEstadisticas()
    ]);

    await cargarSubcategorias();
    renderDashboard();

    const btnLogout = document.getElementById("btn-logout");
    if (btnLogout) {
      btnLogout.addEventListener("click", cerrarSesion);
    }
  }
  init();
});