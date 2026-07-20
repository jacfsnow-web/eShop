// API Client for UniSur-eShop Microservices (Configurado para XAMPP local)
const AUTH_BASE_URL = 'http://localhost/eShop/services/auth-service';
const CATALOG_BASE_URL = 'http://localhost/eShop/services/catalog-service';
const ORDER_BASE_URL = 'http://localhost/eShop/services/order-service';

/**
 * Common Fetch wrapper that handles responses and parses RFC 7807 problem details
 */
async function request(url, options = {}) {
    const defaultHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };

    options.headers = {
        ...defaultHeaders,
        ...options.headers
    };

    try {
        const response = await fetch(url, options);
        const contentType = response.headers.get('content-type') || '';
        let data = null;

        if (contentType.includes('application/json') || contentType.includes('application/problem+json')) {
            data = await response.json();
        }

        if (!response.ok) {
            // Handle RFC 7807 problem details
            const error = new Error(data?.detail || data?.title || `HTTP error! status: ${response.status}`);
            error.status = response.status;
            error.problem = data; // Contains the full RFC 7807 error details
            throw error;
        }

        return data;
    } catch (err) {
        if (err.status) throw err; // Already parsed error
        
        // General networking / CORS error fallback
        const networkError = new Error('No se pudo establecer conexión con el servicio. Verifique si el servicio está en línea.');
        networkError.status = 503;
        throw networkError;
    }
}

const AuthService = {
    register: async (nombreCompleto, correoElectronico, password) => {
        return request(`${AUTH_BASE_URL}/register`, {
            method: 'POST',
            body: JSON.stringify({ nombre_completo: nombreCompleto, correo_electronico: correoElectronico, password })
        });
    },

    login: async (correoElectronico, password) => {
        return request(`${AUTH_BASE_URL}/login`, {
            method: 'POST',
            body: JSON.stringify({ correo_electronico: correoElectronico, password })
        });
    },

    getUser: async (userId) => {
        return request(`${AUTH_BASE_URL}/users?id=${userId}`, {
            method: 'GET'
        });
    }
};

const CatalogService = {
    getProducts: async () => {
        return request(`${CATALOG_BASE_URL}/products`, {
            method: 'GET'
        });
    },

    getProductById: async (id) => {
        return request(`${CATALOG_BASE_URL}/products?id=${id}`, {
            method: 'GET'
        });
    },

    getProductBySku: async (sku) => {
        return request(`${CATALOG_BASE_URL}/products?sku=${sku}`, {
            method: 'GET'
        });
    }
};

const OrderService = {
    createOrder: async (userId, shippingAddress, items) => {
        return request(`${ORDER_BASE_URL}/orders`, {
            method: 'POST',
            body: JSON.stringify({
                id_usuario: userId,
                direccion_envio: shippingAddress,
                items: items
            })
        });
    },

    getOrders: async () => {
        return request(`${ORDER_BASE_URL}/orders`, {
            method: 'GET'
        });
    }
};
