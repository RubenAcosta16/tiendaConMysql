
DROP DATABASE IF EXISTS tienda;
CREATE DATABASE tienda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tienda;

CREATE TABLE direcciones (
    direccion_id INT PRIMARY KEY AUTO_INCREMENT,
    calle VARCHAR(255) NOT NULL,
    numero_exterior VARCHAR(50),
    numero_interior VARCHAR(50),
    colonia VARCHAR(150),
    ciudad VARCHAR(100) NOT NULL,
    estado VARCHAR(100) NOT NULL,
    codigo_postal VARCHAR(20) NOT NULL,
    pais VARCHAR(100) DEFAULT 'México'
) ENGINE=InnoDB;

CREATE TABLE sucursales (
    sucursal_id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    direccion_id INT UNIQUE, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (direccion_id) REFERENCES direcciones(direccion_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE datos_personas (
    datos_personas_id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido_paterno VARCHAR(100) NOT NULL,
    apellido_materno VARCHAR(100),
    email VARCHAR(255) UNIQUE NOT NULL,
    fecha_nacimiento DATE,
    telefono VARCHAR(20)
) ENGINE=InnoDB;

CREATE TABLE puesto (
    puesto_id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_puesto VARCHAR(100) NOT NULL,
    descripcion VARCHAR(250) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE clientes (
    cliente_id INT PRIMARY KEY AUTO_INCREMENT,
    datos_personas_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (datos_personas_id) REFERENCES datos_personas(datos_personas_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE clientes_direcciones (
    cliente_id INT NOT NULL,
    direccion_id INT NOT NULL,
    es_principal BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (cliente_id, direccion_id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id) ON DELETE CASCADE,
    FOREIGN KEY (direccion_id) REFERENCES direcciones(direccion_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE empleados (
    empleado_id INT PRIMARY KEY AUTO_INCREMENT,
    sucursal_id INT NOT NULL,
    puesto_id INT NOT NULL,
    datos_personas_id INT NOT NULL UNIQUE, 
    fecha_contratacion DATE NOT NULL,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(sucursal_id) ON DELETE CASCADE,
    FOREIGN KEY (puesto_id) REFERENCES puesto(puesto_id) ON DELETE RESTRICT, 
    FOREIGN KEY (datos_personas_id) REFERENCES datos_personas(datos_personas_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE proveedores (
    proveedor_id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_empresa VARCHAR(255) NOT NULL,
    rfc VARCHAR(13) UNIQUE,
    nombre_contacto VARCHAR(200),
    email_contacto VARCHAR(255),
    telefono_contacto VARCHAR(20),
    direccion_id INT,
    FOREIGN KEY (direccion_id) REFERENCES direcciones(direccion_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE categorias (
    categoria_id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    descripcion TEXT
) ENGINE=InnoDB;

CREATE TABLE productos (
    producto_id INT PRIMARY KEY AUTO_INCREMENT,
    proveedor_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(proveedor_id) ON DELETE RESTRICT 
) ENGINE=InnoDB;

CREATE TABLE productos_categorias (
    producto_id INT NOT NULL,
    categoria_id INT NOT NULL,
    PRIMARY KEY (producto_id, categoria_id),
    FOREIGN KEY (producto_id) REFERENCES productos(producto_id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(categoria_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ordenes_compra (
    orden_id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    empleado_id INT, 
    sucursal_id INT, 
    direccion_envio_id INT, 
    fecha_orden DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'procesando', 'enviado', 'completada', 'cancelada') DEFAULT 'pendiente',
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id) ON DELETE RESTRICT, 
    FOREIGN KEY (empleado_id) REFERENCES empleados(empleado_id) ON DELETE SET NULL,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(sucursal_id) ON DELETE SET NULL,
    FOREIGN KEY (direccion_envio_id) REFERENCES direcciones(direccion_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ordenes_detalle (
    detalle_id INT PRIMARY KEY AUTO_INCREMENT,
    orden_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes_compra(orden_id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(producto_id) ON DELETE RESTRICT 
) ENGINE=InnoDB;

CREATE TABLE reseñas (
    reseña_id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    producto_id INT NOT NULL,
    calificacion TINYINT CHECK (calificacion BETWEEN 1 AND 5),
    comentario TEXT,
    fecha_reseña DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(producto_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pagos (
    pago_id INT PRIMARY KEY AUTO_INCREMENT,
    orden_id INT NOT NULL,
    metodo_pago ENUM('tarjeta_credito', 'tarjeta_debito', 'efectivo', 'transferencia', 'paypal', 'otro') NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'completado', 'fallido', 'reembolsado') DEFAULT 'pendiente',
    referencia_pago VARCHAR(255), 
    FOREIGN KEY (orden_id) REFERENCES ordenes_compra(orden_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_productos_nombre ON productos(nombre);
CREATE INDEX idx_datos_personas_email ON datos_personas(email); 
CREATE INDEX idx_ordenes_fecha ON ordenes_compra(fecha_orden);
CREATE INDEX idx_ordenes_cliente ON ordenes_compra(cliente_id);
CREATE INDEX idx_productos_sku ON productos(sku);



CREATE TABLE bitacora (
    bitacora_id INT AUTO_INCREMENT PRIMARY KEY,
    tabla_afectada VARCHAR(100) NOT NULL,
    registro_id INT, 
    operacion ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    usuario_db VARCHAR(100) NOT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    datos_viejos TEXT, 
    datos_nuevos TEXT  
) ENGINE=InnoDB;