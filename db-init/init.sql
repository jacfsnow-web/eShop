-- Initialize Databases and Schemas for UniSur-eShop

-- 1. DATABASE: db_ecommerce_auth
CREATE DATABASE IF NOT EXISTS db_ecommerce_auth;
USE db_ecommerce_auth;

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(255) NOT NULL,
    correo_electronico VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL DEFAULT 'cliente',
    token_sesion VARCHAR(255) NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed test users (password for both is: password123)
-- hash is password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO usuarios (nombre_completo, correo_electronico, password_hash, rol) VALUES
('Juan Pérez', 'juan.perez@example.com', '$2y$10$6ftJkpjcFtKqVeJArji93uJQuIkscnH4cF0P1e5sNuKrH7geJa3NG', 'cliente'),
('Admin UniSur', 'admin@unisur.edu.mx', '$2y$10$6ftJkpjcFtKqVeJArji93uJQuIkscnH4cF0P1e5sNuKrH7geJa3NG', 'administrador')
ON DUPLICATE KEY UPDATE id_usuario=id_usuario;


-- 2. DATABASE: db_ecommerce_catalog
CREATE DATABASE IF NOT EXISTS db_ecommerce_catalog;
USE db_ecommerce_catalog;

CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    nombre_producto VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    stock_disponible INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Categories
INSERT INTO categorias (id_categoria, nombre, descripcion) VALUES
(1, 'Electrónica', 'Dispositivos electrónicos y accesorios tecnológicos'),
(2, 'Libros y Educación', 'Libros de texto, novelas y material académico'),
(3, 'Ropa y Accesorios', 'Prendas de vestir oficiales y accesorios de UniSur')
ON DUPLICATE KEY UPDATE id_categoria=id_categoria;

-- Seed Products
INSERT INTO productos (id_producto, id_categoria, sku, nombre_producto, descripcion, precio, stock_disponible, activo) VALUES
(1, 1, 'SKU-LAP-001', 'Laptop Dell Inspiron 15', 'Laptop de 15.6 pulgadas con procesador Intel i7, 16GB RAM y 512GB SSD.', 1200.00, 10, 1),
(2, 1, 'SKU-MOU-002', 'Mouse Inalámbrico Logitech', 'Mouse ergonómico inalámbrico de alta precisión y batería recargable.', 25.50, 50, 1),
(3, 2, 'SKU-LIB-003', 'Patrones de Diseño en PHP 8', 'Libro completo sobre arquitectura de software y patrones de diseño en PHP moderno.', 45.00, 5, 1),
(4, 3, 'SKU-TSH-004', 'Playera Polo UniSur', 'Playera polo de algodón oficial con logotipo bordado de UniSur.', 19.99, 100, 1),
(5, 1, 'SKU-AUD-005', 'Audífonos Bluetooth Sony', 'Audífonos inalámbricos con cancelación de ruido activa y 30 horas de batería.', 199.99, 15, 1)
ON DUPLICATE KEY UPDATE id_producto=id_producto;


-- 3. DATABASE: db_ecommerce_orders
CREATE DATABASE IF NOT EXISTS db_ecommerce_orders;
USE db_ecommerce_orders;

CREATE TABLE IF NOT EXISTS pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(50) NOT NULL DEFAULT 'pendiente',
    subtotal DECIMAL(10, 2) NOT NULL,
    impuesto DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    direccion_envio TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalle_pedidos (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    sku_producto VARCHAR(100) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    subtotal_linea DECIMAL(10, 2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
