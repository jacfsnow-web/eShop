// Application state
let state = {
    user: null,
    cart: []
};

// DOM Elements
const elements = {
    btnAuthTrigger: document.getElementById('btnAuthTrigger'),
    userPanel: document.getElementById('userPanel'),
    userName: document.getElementById('userName'),
    btnLogout: document.getElementById('btnLogout'),
    authModal: document.getElementById('authModal'),
    btnAuthClose: document.getElementById('btnAuthClose'),
    authModalTitle: document.getElementById('authModalTitle'),
    loginFormSection: document.getElementById('loginFormSection'),
    registerFormSection: document.getElementById('registerFormSection'),
    formLogin: document.getElementById('formLogin'),
    formRegister: document.getElementById('formRegister'),
    catalogGrid: document.getElementById('catalogGrid'),
    btnRefreshCatalog: document.getElementById('btnRefreshCatalog'),
    cartCount: document.getElementById('cartCount'),
    cartItemsContainer: document.getElementById('cartItemsContainer'),
    btnClearCart: document.getElementById('btnClearCart'),
    summarySubtotal: document.getElementById('summarySubtotal'),
    summaryTax: document.getElementById('summaryTax'),
    summaryTotal: document.getElementById('summaryTotal'),
    shippingAddress: document.getElementById('shippingAddress'),
    btnCheckout: document.getElementById('btnCheckout'),
    btnRefreshOrders: document.getElementById('btnRefreshOrders'),
    ordersTableBody: document.getElementById('ordersTableBody'),
    toastContainer: document.getElementById('toastContainer')
};

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
    // Restore session
    const savedUser = localStorage.getItem('unisur_session_user');
    const savedToken = localStorage.getItem('unisur_session_token');
    if (savedUser && savedToken) {
        state.user = JSON.parse(savedUser);
        updateAuthUI();
    }

    // Restore cart
    const savedCart = localStorage.getItem('unisur_cart');
    if (savedCart) {
        state.cart = JSON.parse(savedCart);
        updateCartUI();
    }

    // Bind Event Listeners
    elements.btnAuthTrigger.addEventListener('click', () => openAuthModal(true));
    elements.btnAuthClose.addEventListener('click', closeAuthModal);
    elements.btnLogout.addEventListener('click', handleLogout);
    elements.btnRefreshCatalog.addEventListener('click', loadCatalog);
    elements.btnClearCart.addEventListener('click', clearCart);
    elements.btnCheckout.addEventListener('click', handleCheckout);
    elements.btnRefreshOrders.addEventListener('click', loadOrders);
    
    // Initial fetch
    loadCatalog();
    loadOrders();
});

// Toast System
function showToast(title, body, type = 'info', problem = null) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let iconClass = 'fa-circle-info';
    if (type === 'success') iconClass = 'fa-circle-check';
    if (type === 'error') iconClass = 'fa-circle-exclamation';

    let rfcHtml = '';
    if (problem) {
        rfcHtml = `
            <div class="toast-rfc7807">
                <strong>RFC 7807 Problem Detail:</strong><br>
                Type: ${problem.type || 'about:blank'}<br>
                Status: ${problem.status || 500}<br>
                Instance: ${problem.instance || ''}
            </div>
        `;
    }

    toast.innerHTML = `
        <div class="toast-header">
            <span class="toast-title">
                <i class="fa-solid ${iconClass}"></i> ${title}
            </span>
            <button class="toast-close" onclick="this.closest('.toast').remove()">&times;</button>
        </div>
        <div class="toast-body">
            ${body}
            ${rfcHtml}
        </div>
    `;

    elements.toastContainer.appendChild(toast);
    
    // Auto remove after 6 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 6000);
}

// Auth Modal Controls
function openAuthModal(isLogin = true) {
    toggleAuthForm(isLogin);
    elements.authModal.classList.add('active');
}

function closeAuthModal() {
    elements.authModal.classList.remove('active');
}

function toggleAuthForm(showLogin) {
    if (showLogin) {
        elements.authModalTitle.textContent = 'Iniciar Sesión';
        elements.loginFormSection.style.display = 'block';
        elements.registerFormSection.style.display = 'none';
    } else {
        elements.authModalTitle.textContent = 'Registrar Cuenta';
        elements.loginFormSection.style.display = 'none';
        elements.registerFormSection.style.display = 'block';
    }
}

// Form Handlers
async function handleLoginForm(event) {
    event.preventDefault();
    const email = document.getElementById('loginEmail').value;
    const pass = document.getElementById('loginPassword').value;

    try {
        const res = await AuthService.login(email, pass);
        state.user = res.usuario;
        localStorage.setItem('unisur_session_user', JSON.stringify(res.usuario));
        localStorage.setItem('unisur_session_token', res.token_sesion);
        
        showToast('Acceso Correcto', `¡Bienvenido de vuelta, ${res.usuario.nombre_completo}!`, 'success');
        updateAuthUI();
        closeAuthModal();
        
        // Reset form
        elements.formLogin.reset();
    } catch (err) {
        showToast('Fallo de Autenticación', err.message, 'error', err.problem);
    }
}

async function handleRegisterForm(event) {
    event.preventDefault();
    const name = document.getElementById('regName').value;
    const email = document.getElementById('regEmail').value;
    const pass = document.getElementById('regPassword').value;

    try {
        const res = await AuthService.register(name, email, pass);
        showToast('Registro Exitoso', 'Usuario creado correctamente. Ya puedes iniciar sesión.', 'success');
        
        // Auto transition to login page
        toggleAuthForm(true);
        document.getElementById('loginEmail').value = email;
        
        // Reset register form
        elements.formRegister.reset();
    } catch (err) {
        showToast('Error al Registrar', err.message, 'error', err.problem);
    }
}

function handleLogout() {
    state.user = null;
    localStorage.removeItem('unisur_session_user');
    localStorage.removeItem('unisur_session_token');
    updateAuthUI();
    showToast('Sesión Cerrada', 'Has cerrado tu sesión correctamente.', 'info');
}

function updateAuthUI() {
    if (state.user) {
        elements.btnAuthTrigger.style.display = 'none';
        elements.userPanel.style.display = 'flex';
        elements.userName.textContent = state.user.nombre_completo;
        elements.btnCheckout.disabled = state.cart.length === 0;
    } else {
        elements.btnAuthTrigger.style.display = 'inline-flex';
        elements.userPanel.style.display = 'none';
        elements.btnCheckout.disabled = true;
    }
}

// Quick login utility (for testing convenience)
window.quickLogin = async function(email) {
    try {
        const res = await AuthService.login(email, 'password123');
        state.user = res.usuario;
        localStorage.setItem('unisur_session_user', JSON.stringify(res.usuario));
        localStorage.setItem('unisur_session_token', res.token_sesion);
        
        showToast('Acceso Rápido', `Sesión iniciada como ${res.usuario.nombre_completo}`, 'success');
        updateAuthUI();
    } catch (err) {
        showToast('Acceso Rápido Fallido', err.message, 'error', err.problem);
    }
};

// Catalog Controls
async function loadCatalog() {
    elements.catalogGrid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
            <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
            <p>Actualizando catálogo...</p>
        </div>
    `;

    try {
        const products = await CatalogService.getProducts();
        
        if (products.length === 0) {
            elements.catalogGrid.innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                    <p>No se encontraron productos activos en el catálogo.</p>
                </div>
            `;
            return;
        }

        elements.catalogGrid.innerHTML = '';
        products.forEach(p => {
            const isOutOfStock = p.stock_disponible <= 0;
            const stockClass = isOutOfStock ? 'stock-none' : (p.stock_disponible < 5 ? 'stock-low' : 'stock-good');
            const stockText = isOutOfStock ? 'Sin Stock' : `${p.stock_disponible} disponibles`;

            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `
                <div>
                    <span class="product-category">${escapeHtml(p.nombre_categoria || 'Categoría')}</span>
                    <h3 class="product-name">${escapeHtml(p.nombre_producto)}</h3>
                    <p class="product-desc">${escapeHtml(p.descripcion || 'Sin descripción.')}</p>
                    <div class="product-stock">
                        <span class="stock-badge ${stockClass}"></span>
                        <span>${stockText} (SKU: ${escapeHtml(p.sku)})</span>
                    </div>
                </div>
                <div class="product-footer">
                    <span class="product-price">$${p.precio.toFixed(2)}</span>
                    <button class="btn btn-primary" onclick='addToCart(${JSON.stringify(p)})' ${isOutOfStock ? 'disabled' : ''}>
                        <i class="fa-solid fa-cart-plus"></i> ${isOutOfStock ? 'Agotado' : 'Añadir'}
                    </button>
                </div>
            `;
            elements.catalogGrid.appendChild(card);
        });
    } catch (err) {
        elements.catalogGrid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--error-text);">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                <p>Error al cargar el catálogo de productos.</p>
                <small>${escapeHtml(err.message)}</small>
            </div>
        `;
    }
}

// Cart Controls
window.addToCart = function(product) {
    const existing = state.cart.find(item => item.id_producto === product.id_producto);
    
    if (existing) {
        if (existing.cantidad + 1 > product.stock_disponible) {
            showToast('Límite de Stock', `No puedes agregar más de ${product.stock_disponible} unidades de este producto.`, 'error');
            return;
        }
        existing.cantidad++;
    } else {
        state.cart.push({
            id_producto: product.id_producto,
            sku: product.sku,
            nombre_producto: product.nombre_producto,
            precio: product.precio,
            cantidad: 1,
            stock_disponible: product.stock_disponible
        });
    }

    localStorage.setItem('unisur_cart', JSON.stringify(state.cart));
    updateCartUI();
    showToast('Carrito Actualizado', `Se agregó "${product.nombre_producto}" al carrito.`, 'info');
};

window.removeFromCart = function(productId) {
    state.cart = state.cart.filter(item => item.id_producto !== productId);
    localStorage.setItem('unisur_cart', JSON.stringify(state.cart));
    updateCartUI();
};

window.changeCartQty = function(productId, delta) {
    const item = state.cart.find(i => i.id_producto === productId);
    if (!item) return;

    const newQty = item.cantidad + delta;
    if (newQty <= 0) {
        removeFromCart(productId);
        return;
    }

    if (newQty > item.stock_disponible) {
        showToast('Stock Excedido', `Solo hay ${item.stock_disponible} unidades disponibles de este producto.`, 'error');
        return;
    }

    item.cantidad = newQty;
    localStorage.setItem('unisur_cart', JSON.stringify(state.cart));
    updateCartUI();
};

function clearCart() {
    state.cart = [];
    localStorage.removeItem('unisur_cart');
    updateCartUI();
}

function updateCartUI() {
    const count = state.cart.reduce((sum, item) => sum + item.cantidad, 0);
    elements.cartCount.textContent = count;

    if (state.cart.length === 0) {
        elements.cartItemsContainer.innerHTML = `
            <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">El carrito está vacío</p>
        `;
        elements.summarySubtotal.textContent = '$0.00';
        elements.summaryTax.textContent = '$0.00';
        elements.summaryTotal.textContent = '$0.00';
        elements.btnCheckout.disabled = true;
        return;
    }

    elements.cartItemsContainer.innerHTML = '';
    let subtotal = 0.0;

    state.cart.forEach(item => {
        const itemTotal = item.precio * item.cantidad;
        subtotal += itemTotal;

        const row = document.createElement('div');
        row.className = 'cart-item';
        row.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-name">${escapeHtml(item.nombre_producto)}</div>
                <div class="cart-item-meta">
                    $${item.precio.toFixed(2)} x ${item.cantidad}
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <button class="quick-user-btn" style="padding: 0.2rem 0.4rem;" onclick="changeCartQty(${item.id_producto}, -1)">-</button>
                <button class="quick-user-btn" style="padding: 0.2rem 0.4rem;" onclick="changeCartQty(${item.id_producto}, 1)">+</button>
            </div>
            <div class="cart-item-price">$${itemTotal.toFixed(2)}</div>
            <button class="btn btn-secondary btn-icon" style="padding: 0.35rem; color: var(--error-text);" onclick="removeFromCart(${item.id_producto})">
                <i class="fa-solid fa-times"></i>
            </button>
        `;
        elements.cartItemsContainer.appendChild(row);
    });

    const taxRate = 0.16;
    const tax = subtotal * taxRate;
    const total = subtotal + tax;

    elements.summarySubtotal.textContent = `$${subtotal.toFixed(2)}`;
    elements.summaryTax.textContent = `$${tax.toFixed(2)}`;
    elements.summaryTotal.textContent = `$${total.toFixed(2)}`;
    
    // Enable checkout only if items in cart AND user is logged in
    elements.btnCheckout.disabled = !state.user;
}

// Checkout and Orders
async function handleCheckout() {
    if (!state.user) {
        showToast('Inicia Sesión', 'Debes iniciar sesión para realizar una compra.', 'error');
        openAuthModal(true);
        return;
    }

    const address = elements.shippingAddress.value.trim();
    if (!address) {
        showToast('Dirección Faltante', 'Por favor ingresa una dirección de envío.', 'error');
        elements.shippingAddress.focus();
        return;
    }

    elements.btnCheckout.disabled = true;
    elements.btnCheckout.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando...';

    // Format items for order payload
    const itemsPayload = state.cart.map(item => ({
        id_producto: item.id_producto,
        cantidad: item.cantidad
    }));

    try {
        const res = await OrderService.createOrder(state.user.id_usuario, address, itemsPayload);
        showToast('¡Compra Exitosa!', `Pedido #${res.id_pedido} procesado y stock actualizado correctamente.`, 'success');
        
        clearCart();
        elements.shippingAddress.value = '';
        loadCatalog(); // Refresh products inventory count
        loadOrders();  // Refresh orders history log
    } catch (err) {
        showToast('Error en la Compra', err.message, 'error', err.problem);
    } finally {
        elements.btnCheckout.disabled = false;
        elements.btnCheckout.innerHTML = '<i class="fa-solid fa-credit-card"></i> Confirmar y Comprar';
    }
}

async function loadOrders() {
    try {
        const orders = await OrderService.getOrders();
        
        if (orders.length === 0) {
            elements.ordersTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        No hay pedidos registrados en Order Service aún.
                    </td>
                </tr>
            `;
            return;
        }

        elements.ordersTableBody.innerHTML = '';
        orders.forEach(o => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><span class="order-id">#${o.id_pedido}</span></td>
                <td>${o.id_usuario}</td>
                <td>${escapeHtml(o.fecha_pedido)}</td>
                <td>${escapeHtml(o.direccion_envio)}</td>
                <td>$${o.subtotal.toFixed(2)}</td>
                <td>$${o.impuesto.toFixed(2)}</td>
                <td><strong>$${o.total.toFixed(2)}</strong></td>
                <td><span class="order-status">${escapeHtml(o.estado)}</span></td>
            `;
            elements.ordersTableBody.appendChild(row);
        });
    } catch (err) {
        elements.ordersTableBody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--error-text);">
                    Error al cargar pedidos. Verifique que Order Service esté corriendo en puerto 8003.
                </td>
            </tr>
        `;
    }
}

// Utilities
function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
