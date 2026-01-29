/**
 * ARCHIVO: js/script.js
 * Versión Final: Funciones globales para botones HTML + Lógica de BD
 * SECURITY: Includes CSRF protection and input validation
 */

// =========================================================
// 0. SECURITY - CSRF TOKEN MANAGEMENT
// =========================================================
let csrfToken = null;

/**
 * Obtener token CSRF del servidor
 */
async function fetchCsrfToken() {
    try {
        const response = await fetch(`${API_BASE}/get_csrf_token.php`, {
            credentials: 'include'
        });
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

/**
 * Secure Fetch Wrapper con CSRF token automático
 */
async function secureFetch(url, options = {}) {
    if (!csrfToken) {
        await fetchCsrfToken();
    }

    // Agregar CSRF token a peticiones que modifican estado
    if (options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = csrfToken;
    }

    options.credentials = 'include';

    try {
        const response = await fetch(url, options);

        // Actualizar token si viene uno nuevo
        if (response.ok) {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const clone = response.clone();
                const data = await clone.json();
                if (data.csrf_token) {
                    csrfToken = data.csrf_token;
                    sessionStorage.setItem('csrf_token', csrfToken);
                }
            }
        }

        return response;
    } catch (error) {
        console.error('Secure fetch error:', error);
        throw error;
    }
}

// =========================================================
// 1. CONFIGURACIÓN Y ESTADO GLOBAL
// =========================================================
const API_BASE = API_CONFIG.baseUrl;

let productsData = [];
let filteredData = [];
let cart = [];

const ITEMS_PER_PAGE = 9;
let currentPage = 1;

// =========================================================
// 2. FUNCIONES DE UTILIDAD
// =========================================================


function formatPrice(amount) {
    return Number(amount).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

// =========================================================
// 3. FUNCIONES GLOBALES (ACCESIBLES DESDE HTML)
// =========================================================

/**
 * Abre/Cierra el carrito lateral
 */
window.toggleCart = function (forceOpen = false) {
    const sidebar = document.getElementById('cart-sidebar');
    const overlay = document.getElementById('cart-overlay');
    if (!sidebar) return;

    if (forceOpen === true || !sidebar.classList.contains('active')) {
        sidebar.classList.add('active');
        if (overlay) overlay.style.display = 'block';
        loadCart(); // Recargar datos al abrir
    } else {
        sidebar.classList.remove('active');
        if (overlay) overlay.style.display = 'none';
    }
};

/**
 * Elimina producto del carrito
 */
window.removeFromCart = async function (productId) {
    if (!confirm('¿Eliminar producto?')) return;
    try {
        await fetch(`${API_BASE_SHOP}/carrito.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_producto: productId })
        });
        loadCart();
    } catch (e) { console.error(e); }
};

/**
 * Actualiza cantidad (+ / -)
 */
window.updateCartQuantity = async function (productId, newQuantity) {
    if (newQuantity < 1) return;
    try {
        const res = await fetch(`${API_BASE_SHOP}/carrito.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_producto: productId, cantidad: newQuantity })
        });
        if (res.ok) loadCart();
    } catch (e) { console.error(e); }
};

/**
 * Ir a la pantalla de Pago (Checkout)
 */
window.procedeToCheckout = function () {
    if (cart.length === 0) {
        showNotification('Tu carrito está vacío.', true);
        return;
    }

    // 1. Cerrar sidebar
    const sidebar = document.getElementById('cart-sidebar');
    const overlay = document.getElementById('cart-overlay');
    if (sidebar) sidebar.classList.remove('active');
    if (overlay) overlay.style.display = 'none';

    // 2. Cambiar de pantalla
    const shopSection = document.querySelector('.shop-section');
    const checkoutSection = document.getElementById('checkout-section');
    const floatBtn = document.getElementById('floating-cart-btn');

    if (shopSection) shopSection.classList.add('hidden-section');
    if (checkoutSection) checkoutSection.classList.remove('hidden-section');
    if (floatBtn) floatBtn.style.display = 'none';

    // 3. Actualizar resumen
    updateCheckoutSummary();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/**
 * Volver a la tienda desde el Checkout
 */
window.backToCart = function () {
    const shopSection = document.querySelector('.shop-section');
    const checkoutSection = document.getElementById('checkout-section');
    const floatBtn = document.getElementById('floating-cart-btn');

    if (checkoutSection) checkoutSection.classList.add('hidden-section');
    if (shopSection) shopSection.classList.remove('hidden-section');
    if (floatBtn) floatBtn.style.display = 'flex';

    window.toggleCart(true); // Reabrir carrito
};

// =========================================================
// 4. LÓGICA DE DATOS (Backend)
// =========================================================

async function addToCart(productId, quantity) {
    // 1. Validar Stock local
    const product = productsData.find(p => p.id === productId);
    if (product && product.stock < quantity) {
        showNotification('Stock insuficiente', true);
        return;
    }

    try {
        const res = await fetch(`${API_BASE_SHOP}/carrito.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_producto: productId, cantidad: quantity })
        });

        if (res.status === 401) {
            showNotification('🔒 Inicia sesión para comprar', true);
            const modal = document.getElementById('auth-modal');
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 10);
            }
            return;
        }

        const data = await res.json();

        if (data.ok) {
            showNotification('✅ Producto agregado');
            loadCart();
            window.toggleCart(true);
        } else {
            showNotification('❌ ' + (data.msg || 'Error'), true);
        }
    } catch (error) {
        console.error('Error addToCart:', error);
        showNotification('Error de conexión', true);
    }
}

async function loadCart() {
    try {
        const res = await fetch(`${API_BASE_SHOP}/carrito.php`, {
            method: 'GET',
            credentials: 'include'
        });
        const data = await res.json();

        if (data.ok) {
            cart = data.items.map(item => ({
                id: parseInt(item.id_producto),
                name: item.nom_producto,
                price: parseFloat(item.precio),
                img: item.url_imagen || 'images/default-watch.png',
                quantity: parseInt(item.cantidad),
                stock: parseInt(item.stock)
            }));
            updateCartDisplay();
        }
    } catch (error) { console.error('Error loadCart:', error); }
}

function updateCartDisplay() {
    const list = document.getElementById('cart-items-list');
    const countSpan = document.getElementById('cart-item-count');
    const totalSpan = document.getElementById('cart-total');
    const subtotalSpan = document.getElementById('cart-subtotal');
    const checkoutBtn = document.getElementById('btn-procede-checkout');

    if (!list) return;

    let total = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);
    let qty = cart.reduce((acc, item) => acc + item.quantity, 0);
    const SHIPPING = 15000;

    if (cart.length === 0) {
        list.innerHTML = '<p class="empty-cart-message">Carrito vacío</p>';
        if (checkoutBtn) checkoutBtn.disabled = true;
    } else {
        list.innerHTML = cart.map(item => `
            <div class="cart-item">
                <img src="${item.img}" class="cart-item-img" onerror="this.src='https://via.placeholder.com/80'">
                <div class="cart-item-details">
                    <h4>${item.name}</h4>
                    <p>${formatPrice(item.price)}</p>
                    <div class="cart-item-actions">
                        <div class="quantity-controls">
                            <button onclick="window.updateCartQuantity(${item.id}, ${item.quantity - 1})">-</button>
                            <span>${item.quantity}</span>
                            <button onclick="window.updateCartQuantity(${item.id}, ${item.quantity + 1})">+</button>
                        </div>
                        <button class="remove-item-btn" onclick="window.removeFromCart(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>`).join('');
        if (checkoutBtn) checkoutBtn.disabled = false;
    }

    if (countSpan) countSpan.textContent = qty;
    if (subtotalSpan) subtotalSpan.textContent = formatPrice(total);
    if (totalSpan) totalSpan.textContent = formatPrice(total > 0 ? total + SHIPPING : 0);
}

function updateCheckoutSummary() {
    const summaryList = document.getElementById('checkout-order-summary');
    if (!summaryList) return;

    const SHIPPING_COST = 15000;
    let subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    summaryList.innerHTML = cart.map(item => `
        <div class="cart-item">
            <img src="${item.img}" class="cart-item-img" onerror="this.src='https://via.placeholder.com/80'">
            <div class="cart-item-details">
                <h4>${item.name}</h4>
                <p>${formatPrice(item.price)} x ${item.quantity}</p>
            </div>
        </div>
    `).join('');

    const elSub = document.getElementById('checkout-subtotal');
    const elShip = document.getElementById('checkout-shipping');
    const elTotal = document.getElementById('checkout-final-total');
    const elPay = document.getElementById('payment-amount');

    if (elSub) elSub.textContent = formatPrice(subtotal);
    if (elShip) elShip.textContent = formatPrice(SHIPPING_COST);
    if (elTotal) elTotal.textContent = formatPrice(subtotal + SHIPPING_COST);
    if (elPay) elPay.textContent = formatPrice(subtotal + SHIPPING_COST);
}

// =========================================================
// 5. CARGA DE PRODUCTOS (CATÁLOGO)
// =========================================================

async function loadProducts() {
    const productList = document.getElementById('product-list');
    if (!productList) return;

    try {
        productList.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px">Cargando...</div>';

        const [resProd, resCat] = await Promise.all([
            fetch(`${API_BASE_SHOP}/productos.php`),
            fetch(`${API_BASE_SHOP}/catalogos.php?tipo=categorias`)
        ]);

        const dataProd = await resProd.json();
        const dataCat = await resCat.json();

        if (dataCat.ok) renderCategoriesSidebar(dataCat.categorias);

        if (dataProd.ok) {
            productsData = dataProd.productos.map(p => ({
                id: parseInt(p.id_producto),
                name: p.nom_producto,
                description: p.descripcion || 'Sin descripción.',
                price: parseFloat(p.precio),
                stock: parseInt(p.stock),
                category: String(p.nom_categoria || 'General'),
                brand: String(p.nom_marca || 'General'),
                img: p.url_imagen || 'images/default-watch.png',
                badge: (p.stock < 5 && p.stock > 0) ? '¡Pocas!' : ''
            }));

            populateBrandFilter(productsData);
            filteredData = [...productsData];
            renderPaginatedProducts();
        } else {
            productList.innerHTML = `<p>Error: ${dataProd.msg}</p>`;
        }
    } catch (error) {
        console.error(error);
        productList.innerHTML = '<p>Error de conexión.</p>';
    }
}

function renderCategoriesSidebar(categorias) {
    const container = document.getElementById('category-filters');
    if (!container) return;
    let html = '<li><a href="#" data-filter="all" class="active category-link">Todos</a></li>';
    categorias.forEach(cat => {
        html += `<li><a href="#" data-filter="${cat.nom_categoria}" class="category-link">${cat.nom_categoria}</a></li>`;
    });
    container.innerHTML = html;

    document.querySelectorAll('.category-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
            e.target.classList.add('active');
            applyFilters();
        });
    });
}

function renderPaginatedProducts() {
    const productList = document.getElementById('product-list');
    if (!productList) return;

    if (filteredData.length === 0) {
        productList.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px">No hay productos.</div>';
        return;
    }

    const totalItems = filteredData.length;
    const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
    if (currentPage > totalPages) currentPage = 1;

    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const pageItems = filteredData.slice(startIndex, startIndex + ITEMS_PER_PAGE);

    productList.innerHTML = pageItems.map(p => `
        <div class="product-card">
            <div class="product-image-container">
                <img src="${p.img}" alt="${p.name}" class="product-image" onerror="this.src='https://via.placeholder.com/250'">
                ${p.badge ? `<span class="product-badge">${p.badge}</span>` : ''}
            </div>
            <div class="product-details">
                <p class="product-category">${p.brand} - ${p.category}</p>
                <h3 class="product-name">${p.name}</h3>
                <p class="product-price">${formatPrice(p.price)}</p>
                <div class="product-actions">
                    ${p.stock > 0
            ? `<button class="button button-primary btn-add-cart" data-id="${p.id}"><i class="fas fa-cart-plus"></i> Añadir</button>`
            : `<button class="button button-secondary" disabled>Agotado</button>`
        }
                    <button class="button button-outline btn-view-product" data-id="${p.id}"><i class="fas fa-eye"></i> Ver</button>
                </div>
            </div>
        </div>
    `).join('');

    // Actualizar controles paginación
    const pageInfo = document.getElementById('page-info');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    if (pageInfo) pageInfo.textContent = `Página ${currentPage} de ${totalPages || 1}`;
    if (prevBtn) {
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => { currentPage--; renderPaginatedProducts(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
    }
    if (nextBtn) {
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
        nextBtn.onclick = () => { currentPage++; renderPaginatedProducts(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
    }
}

// Filtros y Buscadores
function applyFilters() {
    if (productsData.length === 0) return;
    const activeCatLink = document.querySelector('#category-filters .active');
    const activeCategory = activeCatLink ? activeCatLink.getAttribute('data-filter') : 'all';
    const brand = document.getElementById('brand-filter').value;
    const maxPrice = parseFloat(document.getElementById('price-range').value);

    document.getElementById('price-value').textContent = formatPrice(maxPrice);

    filteredData = productsData.filter(p => {
        const matchCat = activeCategory === 'all' || p.category === activeCategory;
        const matchBrand = brand === 'all' || p.brand === brand;
        const matchPrice = p.price <= maxPrice;
        return matchCat && matchBrand && matchPrice;
    });

    currentPage = 1;
    renderPaginatedProducts();
}

function populateBrandFilter(products) {
    const brandSelect = document.getElementById('brand-filter');
    if (!brandSelect) return;
    while (brandSelect.options.length > 1) { brandSelect.remove(1); }
    const brands = [...new Set(products.map(p => p.brand))].sort();
    brands.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b; opt.textContent = b;
        brandSelect.appendChild(opt);
    });
}

function openProductModal(id) {
    const product = productsData.find(p => p.id === id);
    if (!product) return;

    document.getElementById('modal-img').src = product.img;
    document.getElementById('modal-title').textContent = product.name;
    document.getElementById('modal-price').textContent = formatPrice(product.price);
    document.getElementById('modal-desc').textContent = product.description;

    const qtyInput = document.getElementById('modal-qty');
    qtyInput.value = 1; qtyInput.max = product.stock;

    const addBtn = document.getElementById('modal-add-btn');
    const newBtn = addBtn.cloneNode(true);
    addBtn.parentNode.replaceChild(newBtn, addBtn);

    newBtn.onclick = () => {
        addToCart(product.id, parseInt(qtyInput.value));
        document.getElementById('product-detail-modal').style.display = 'none';
    };

    const modal = document.getElementById('product-detail-modal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}

// =========================================================
// 6. PASARELA DE PAGO (CHECKOUT)
// =========================================================

const paymentForm = document.getElementById('payment-form');
if (paymentForm) {
    paymentForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const address = document.getElementById('shipping-address').value;
        const city = document.getElementById('shipping-city').value;
        const method = document.querySelector('input[name="payment_method"]:checked')?.value || 'Transferencia Bancaria';

        const submitBtn = this.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoader = submitBtn.querySelector('.btn-loader');

        btnText.style.display = 'none';
        btnLoader.style.display = 'inline-block';
        submitBtn.disabled = true;

        showNotification('🔄 Procesando orden...', false);

        try {
            const res = await fetch(`${API_BASE_SHOP}/checkout.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ direccion: address, ciudad: city, metodo: method })
            });

            const data = await res.json();

            if (data.ok) {
                showNotification('✅ ¡Orden creada exitosamente!');
                cart = [];
                updateCartDisplay();

                // Extraer ID de orden del mensaje (ej: "SUCCESS: Orden #123 creada exitosamente")
                const orderId = data.order_id || data.msg.match(/\d+/)?.[0];

                // Redireccionar a la página de factura
                setTimeout(() => {
                    window.location.href = `factura.html?orden=${orderId}`;
                }, 1000);
            } else {
                showNotification('❌ ' + data.msg, true);
                btnText.style.display = 'inline-block';
                btnLoader.style.display = 'none';
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            showNotification('Error de conexión', true);
            btnText.style.display = 'inline-block';
            btnLoader.style.display = 'none';
            submitBtn.disabled = false;
        }
    });
}

// =========================================================
// 7. INICIALIZACIÓN GLOBAL (Event Listeners)
// =========================================================

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar visuales
    const preloader = document.querySelector('.preloader');
    if (preloader) setTimeout(() => { preloader.classList.add('fade-out'); setTimeout(() => preloader.style.display = 'none', 500); }, 1000);

    const header = document.querySelector('.header');
    if (header) window.addEventListener('scroll', () => { if (window.scrollY > 50) header.classList.add('scrolled'); else header.classList.remove('scrolled'); });

    // SECURITY: Inicializar CSRF token si hay sesión
    const userLoggedIn = sessionStorage.getItem('user_logged_in');
    if (userLoggedIn === 'true') {
        fetchCsrfToken();
    }

    // Inicializar Tienda
    if (document.getElementById('product-list')) {
        loadProducts();
        document.getElementById('brand-filter')?.addEventListener('change', applyFilters);
        document.getElementById('price-range')?.addEventListener('input', applyFilters);
        document.getElementById('sort-order')?.addEventListener('change', applyFilters);
        document.querySelector('.close-product-modal')?.addEventListener('click', () => {
            document.getElementById('product-detail-modal').style.display = 'none';
        });
    }

    // Auth Modal
    const authModal = document.getElementById('auth-modal');
    if (authModal) {
        document.getElementById('login-btn')?.addEventListener('click', () => { authModal.style.display = 'flex'; setTimeout(() => authModal.classList.add('show'), 10); });
        document.querySelector('.close-modal')?.addEventListener('click', () => { authModal.classList.remove('show'); setTimeout(() => authModal.style.display = 'none', 300); });
    }

    loadCart(); // Siempre cargar carrito

    // Detector de clics para botones dinámicos
    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.btn-add-cart');
        if (addBtn) { e.preventDefault(); addToCart(parseInt(addBtn.dataset.id), 1); }

        const viewBtn = e.target.closest('.btn-view-product');
        if (viewBtn) { e.preventDefault(); openProductModal(parseInt(viewBtn.dataset.id)); }
    });

    // =========================================================
    // MOBILE MENU LOGIC
    // =========================================================
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const headerActions = document.querySelector('.header-actions');
    const navLinks = document.querySelectorAll('.nav-link');

    if (mobileMenuBtn && mainNav) {
        mobileMenuBtn.addEventListener('click', () => {
            const isActive = mainNav.classList.toggle('active');
            mobileMenuBtn.innerHTML = isActive ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
            document.body.style.overflow = isActive ? 'hidden' : '';

            // Toggle header actions visibility if they were moved by CSS
            if (headerActions) {
                headerActions.classList.toggle('active');
            }
        });

        // Close menu when clicking a link
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                mainNav.classList.remove('active');
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.style.overflow = '';
                if (headerActions) headerActions.classList.remove('active');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (mainNav.classList.contains('active') &&
                !mainNav.contains(e.target) &&
                !mobileMenuBtn.contains(e.target)) {
                mainNav.classList.remove('active');
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.style.overflow = '';
                if (headerActions) headerActions.classList.remove('active');
            }
        });
    }
});
// 8. GESTIÓN DE SESIÓN DE USUARIO (HEADER)
// =========================================================

async function checkSession() {
    try {
        const res = await fetch(`${API_BASE_SHOP}/me.php`, { credentials: 'include' });
        const data = await res.json();

        if (data.ok && data.user) {
            updateHeaderUser(data.user);
            // Guardar en sessionStorage para acceso rápido
            sessionStorage.setItem('user', JSON.stringify(data.user));
        } else {
            sessionStorage.removeItem('user');
        }
    } catch (error) {
        console.error('Error verificando sesión:', error);
    }
}

function updateHeaderUser(user) {
    const loginBtn = document.getElementById('login-btn');
    if (!loginBtn) return;

    // Cambiar texto e icono
    loginBtn.innerHTML = `<i class="fas fa-user-circle"></i> ${user.nombre.split(' ')[0]}`;

    // Cambiar comportamiento: Ir al panel en lugar de abrir modal
    // Primero clonamos el nodo para eliminar event listeners anteriores
    const newBtn = loginBtn.cloneNode(true);
    loginBtn.parentNode.replaceChild(newBtn, loginBtn);

    newBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const user = JSON.parse(sessionStorage.getItem('user'));
        if (user && user.rol === 'admin') {
            window.location.href = 'admin/admin.html';
        } else {
            window.location.href = 'user/user.html';
        }
    });
}

// Asegúrate de llamar a esta función cuando cargue la página
document.addEventListener('DOMContentLoaded', () => {
    // ... tu código existente ...
    loadTestimonials();
    loadServices();
    checkSession(); // <--- NUEVO
});

// 3. (REMOVED LEGACY LOGIC) - Ahora manejado dinámicamente por initRequestButtons() en loadServices()

// Función para cargar reseñas en el home
async function loadTestimonials() {
    const sliderContainer = document.querySelector('.reviews-slider');
    if (!sliderContainer) return;

    try {
        const res = await fetch(`${API_BASE}/resenas.php`);
        const data = await res.json();

        if (data.ok && data.resenas.length > 0) {
            // Limpiamos los testimonios estáticos (hardcoded)
            sliderContainer.innerHTML = '';

            data.resenas.forEach(review => {
                // Generar estrellas HTML
                let starsHtml = '';
                for (let i = 0; i < 5; i++) {
                    if (i < review.calificacion) {
                        starsHtml += '<i class="fas fa-star"></i>'; // Llena
                    } else {
                        starsHtml += '<i class="far fa-star"></i>'; // Vacía
                    }
                }

                // Crear tarjeta HTML
                const cardHtml = `
                    <div class="review-card">
                        <div class="review-rating" style="color: var(--warning-color);">
                            ${starsHtml}
                        </div>
                        <p class="review-text">"${review.comentario}"</p>
                        <div class="reviewer-info">
                            <div class="reviewer-avatar" style="background:#333; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                                ${review.nom_usuario.charAt(0).toUpperCase()}
                            </div>
                            <div class="reviewer-details">
                                <p class="reviewer-name">${review.nom_usuario}</p>
                                <p class="reviewer-role">Cliente Verificado</p>
                            </div>
                        </div>
                    </div>
                `;
                sliderContainer.innerHTML += cardHtml;
            });
        }
    } catch (error) {
        console.error('Error cargando testimonios:', error);
    }
}

// Función para cargar servicios dinámicamente
async function loadServices() {
    const servicesGrid = document.getElementById('public-services-grid');
    if (!servicesGrid) return;

    try {
        const res = await fetch(`${API_BASE_SHOP}/servicios.php`);
        const data = await res.json();

        if (data.ok && data.servicios.length > 0) {
            servicesGrid.innerHTML = ''; // Limpiar loader

            data.servicios.forEach(s => {
                // Asignar icono basado en palabras clave
                let iconClass = 'fas fa-clock'; // Default
                const nameLower = s.nom_servicio.toLowerCase();

                if (nameLower.includes('reparación') || nameLower.includes('reparacion')) iconClass = 'fas fa-tools';
                else if (nameLower.includes('mantenimiento')) iconClass = 'fas fa-cogs';
                else if (nameLower.includes('repuesto') || nameLower.includes('pieza')) iconClass = 'fas fa-box-open';
                else if (nameLower.includes('diagnostico') || nameLower.includes('diagnóstico')) iconClass = 'fas fa-stethoscope';
                else if (nameLower.includes('limpieza')) iconClass = 'fas fa-broom';

                const card = `
                    <div class="service-card">
                        <div class="card-icon">
                            <i class="${iconClass}"></i>
                        </div>
                        <h3 class="card-title">${s.nom_servicio}</h3>
                        <p class="card-text">${s.descripcion}</p>
                        <ul class="service-features">
                            <li><i class="fas fa-check-circle"></i> Duración: ${s.duracion_estimada}</li>
                            <li><i class="fas fa-check-circle"></i> Precio: ${formatPrice(s.precio_servicio)}</li>
                            <li><i class="fas fa-check-circle"></i> Garantizado</li>
                        </ul>
                        <a href="#contact-section" class="service-link btn-request-service" data-search="${s.nom_servicio}">
                            Solicitar <i class="fas fa-arrow-down"></i>
                        </a>
                    </div>
                `;
                servicesGrid.innerHTML += card;
            });

            // Re-asignar eventos a los nuevos botones
            initRequestButtons();
        } else {
            servicesGrid.innerHTML = '<p style="width:100%;text-align:center">No hay servicios disponibles.</p>';
        }
    } catch (error) {
        console.error('Error cargando servicios:', error);
        servicesGrid.innerHTML = '<p style="width:100%;text-align:center;color:red">Error al cargar servicios.</p>';
    }
}

// Función auxiliar para inicializar botones de solicitud (extraída para reuso)
function initRequestButtons() {
    const requestButtons = document.querySelectorAll('.btn-request-service');
    if (requestButtons.length > 0) {
        requestButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const searchTerm = btn.getAttribute('data-search').toLowerCase();
                const select = document.getElementById('contact-service');

                // Esperamos un momento para el scroll
                setTimeout(() => {
                    if (select && select.options.length > 1) {
                        for (let i = 0; i < select.options.length; i++) {
                            const optionText = select.options[i].text.toLowerCase();
                            if (optionText.includes(searchTerm)) {
                                select.selectedIndex = i;
                                select.style.borderColor = 'var(--primary-color)';
                                select.style.boxShadow = '0 0 10px rgba(184, 134, 11, 0.3)';
                                setTimeout(() => {
                                    select.style.borderColor = '';
                                    select.style.boxShadow = '';
                                }, 1500);
                                break;
                            }
                        }
                    }
                }, 100);
            });
        });
    }
}

// Asegúrate de llamar a esta función cuando cargue la página
document.addEventListener('DOMContentLoaded', () => {
    // ... tu código existente ...
    loadTestimonials();
    loadServices();
});
// =========================================================
// LÓGICA DE AUTENTICACIÓN (LOGIN Y REGISTRO)
// =========================================================

document.addEventListener('DOMContentLoaded', () => {

    /* =========================================================
       1. MANEJO DEL LOGIN (TU CÓDIGO ORIGINAL)
       ========================================================= */
    const loginForm = document.getElementById('loginForm');

    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault(); // <--- ESTO ES VITAL: Evita que la página se recargue

            const emailInput = document.getElementById('login-email');
            const passwordInput = document.getElementById('login-password');
            const btnLogin = loginForm.querySelector('.btn-login');
            const btnText = btnLogin.querySelector('.btn-text');
            const btnLoader = btnLogin.querySelector('.btn-loader');

            // UI Loading
            if (btnText) btnText.style.display = 'none';
            if (btnLoader) btnLoader.style.display = 'inline-block';
            btnLogin.disabled = true;

            try {
                // Ajusta esta ruta si tu carpeta backend está en otro lugar
                const response = await fetch(`${API_CONFIG.baseUrl}/login.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include', // Importante para mantener la sesión PHP
                    body: JSON.stringify({
                        email: emailInput.value,
                        password: passwordInput.value
                    })
                });

                const data = await response.json();

                if (data.ok) {
                    // Guardar datos básicos en sessionStorage para el frontend
                    sessionStorage.setItem('user', JSON.stringify(data.user));

                    showNotification('✅ Bienvenido ' + data.user.nombre);

                    // REDIRECCIÓN BASADA EN LA RESPUESTA DEL PHP
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showNotification('❌ ' + (data.msg || 'Error al iniciar sesión'), true);
                }

            } catch (error) {
                console.error('Error login:', error);
                showNotification('Error de conexión con el servidor', true);
            } finally {
                // Restaurar botón
                if (btnText) btnText.style.display = 'inline-block';
                if (btnLoader) btnLoader.style.display = 'none';
                btnLogin.disabled = false;
            }
        });
    }

    /* =========================================================
       2. MANEJO DEL REGISTRO (NUEVO CÓDIGO)
       ========================================================= */
    const signupForm = document.getElementById('signupForm');

    if (signupForm) {
        signupForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            // Referencias
            const nameInput = document.getElementById('signup-name');
            const emailInput = document.getElementById('signup-email');
            const phoneInput = document.getElementById('signup-phone');
            const passInput = document.getElementById('signup-password');
            const confirmInput = document.getElementById('signup-password-confirm');

            // Validar coincidencia de contraseñas
            if (passInput.value !== confirmInput.value) {
                showNotification('❌ Las contraseñas no coinciden', true);
                return;
            }

            // UI Loading
            const btnSignup = signupForm.querySelector('.btn-signup');
            const btnText = btnSignup.querySelector('.btn-text');
            const btnLoader = btnSignup.querySelector('.btn-loader');

            if (btnText) btnText.textContent = ""; // Ocultar texto temporalmente
            if (btnLoader) btnLoader.style.display = 'inline-block';
            btnSignup.disabled = true;

            try {
                // Usamos el archivo que conecta con tu FUN_REGISTRAR_USUARIO
                const API_URL = `${API_CONFIG.baseUrl}/signup.php`;

                const response = await secureFetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nombre: nameInput.value,
                        email: emailInput.value,
                        telefono: phoneInput.value,
                        password: passInput.value
                    })
                });

                const data = await response.json();

                if (response.ok && data.ok) {
                    showNotification('✅ Registro exitoso. Por favor inicia sesión.');
                    signupForm.reset();
                    // Simular clic en "Inicia Sesión" para cambiar la vista
                    const loginSwitcher = document.querySelector('.switcher-login');
                    if (loginSwitcher) loginSwitcher.click();
                } else {
                    const errorMsg = data.msg || 'Error al registrarse';
                    showNotification(`❌ ${errorMsg}`, true);
                }
            } catch (error) {
                console.error('Error signup:', error);
                showNotification('❌ Error de conexión al intentar registrarse', true);
            } finally {
                // Restaurar botón
                if (btnText) btnText.textContent = "CREAR CUENTA";
                if (btnLoader) btnLoader.style.display = 'none';
                btnSignup.disabled = false;
            }
        });
    }

    /* =========================================================
       3. FUNCIONALIDADES UI (OJO, TABS, MEDIDOR)
       ========================================================= */

    // A. CAMBIO DE PESTAÑAS (LOGIN <-> REGISTRO)
    const switchers = document.querySelectorAll('.switcher');
    switchers.forEach(item => {
        item.addEventListener('click', function () {
            document.querySelectorAll('.form-wrapper').forEach(fw => fw.classList.remove('is-active'));
            this.parentElement.classList.add('is-active');
        });
    });

    // B. MOSTRAR/OCULTAR CONTRASEÑA (EL OJO)
    const togglePassBtns = document.querySelectorAll('.toggle-password');
    togglePassBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault(); // Evitar submit
            const input = btn.previousElementSibling; // El input está antes del botón
            const icon = btn.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // C. MEDIDOR DE FUERZA DE CONTRASEÑA
    const passInputSignup = document.getElementById('signup-password');
    const strengthText = document.getElementById('strength-text');
    const bars = document.querySelectorAll('.strength-bar');

    if (passInputSignup && strengthText) {
        passInputSignup.addEventListener('input', function () {
            const val = this.value;
            let score = 0;

            if (val.length >= 6) score++;         // Longitud mínima
            if (val.length >= 10) score++;        // Longitud ideal
            if (/[A-Z]/.test(val)) score++;       // Mayúsculas
            if (/[0-9]/.test(val)) score++;       // Números
            if (/[^A-Za-z0-9]/.test(val)) score++;// Símbolos

            // Limitar a 4 niveles
            if (score > 4) score = 4;

            // Actualizar Texto
            const labels = ['Muy Débil', 'Débil', 'Media', 'Fuerte', 'Muy Segura'];
            strengthText.textContent = labels[score];

            // Colorear Barras
            bars.forEach((bar, idx) => {
                if (idx < score) {
                    if (score <= 1) bar.style.backgroundColor = '#e74c3c'; // Rojo
                    else if (score === 2) bar.style.backgroundColor = '#f1c40f'; // Amarillo
                    else if (score === 3) bar.style.backgroundColor = '#3498db'; // Azul
                    else bar.style.backgroundColor = '#2ecc71'; // Verde
                } else {
                    bar.style.backgroundColor = '#ddd'; // Gris
                }
            });
        });
    }

    // D. ABRIR/CERRAR MODAL
    const authModal = document.getElementById('auth-modal');
    const openBtns = document.querySelectorAll('#login-btn, .btn-open-login');
    const closeBtn = document.querySelector('.close-modal');

    if (authModal) {
        openBtns.forEach(btn => btn.addEventListener('click', (e) => {
            e.preventDefault();
            authModal.style.display = 'flex';
            setTimeout(() => authModal.classList.add('show'), 10);
        }));

        if (closeBtn) closeBtn.addEventListener('click', () => {
            authModal.classList.remove('show');
            setTimeout(() => authModal.style.display = 'none', 300);
        });

        authModal.addEventListener('click', (e) => {
            if (e.target === authModal) {
                authModal.classList.remove('show');
                setTimeout(() => authModal.style.display = 'none', 300);
            }
        });
    }

    // Helper para notificaciones (si no lo tenías ya)
    function showNotification(msg, isError = false) {
        const notif = document.getElementById('notification');
        if (!notif) return;
        notif.textContent = msg;
        notif.className = isError ? 'notification error' : 'notification success';
        notif.classList.add('show');
        setTimeout(() => notif.classList.remove('show'), 4000);
    }

});

// === LÓGICA DE INTERFAZ (SWITCHER LOGIN/SIGNUP) ===
// Esto hace que el botón "Regístrate" cambie el formulario visualmente
const switchers = document.querySelectorAll('.switcher');

switchers.forEach(item => {
    item.addEventListener('click', function () {
        // Remover la clase activa de todos los padres
        switchers.forEach(item => item.parentElement.classList.remove('is-active'));
        // Agregar la clase activa al padre del botón clickeado
        this.parentElement.classList.add('is-active');
    });
});

// === MODAL (ABRIR/CERRAR) ===
const authModal = document.getElementById('auth-modal');
const openLoginBtns = document.querySelectorAll('#login-btn, .btn-open-login'); // Selecciona todos los botones de login
const closeModal = document.querySelector('.close-modal');

if (authModal) {
    // Abrir modal
    openLoginBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            authModal.style.display = 'flex';
            setTimeout(() => authModal.classList.add('show'), 10);
        });
    });

    // Cerrar modal con la X
    if (closeModal) {
        closeModal.addEventListener('click', () => {
            authModal.classList.remove('show');
            setTimeout(() => authModal.style.display = 'none', 300);
        });
    }

    // Cerrar al dar click fuera del modal
    authModal.addEventListener('click', (e) => {
        if (e.target === authModal) {
            authModal.classList.remove('show');
            setTimeout(() => authModal.style.display = 'none', 300);
        }
    });
}
