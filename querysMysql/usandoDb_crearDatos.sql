
INSERT INTO direcciones (calle, numero_exterior, numero_interior, colonia, ciudad, estado, codigo_postal, pais) VALUES
('Av. Insurgentes Sur', '123', 'A', 'Roma Norte', 'Ciudad de México', 'CDMX', '06700', 'México'),
('Calle Reforma', '456', NULL, 'Centro', 'Guadalajara', 'Jalisco', '44100', 'México'),
('Blvd. Kukulcán', '789', '12', 'Zona Hotelera', 'Cancún', 'Quintana Roo', '77500', 'México'),
('Av. Revolución', '321', NULL, 'Zona Centro', 'Tijuana', 'Baja California', '22000', 'México'),
('Calle Hidalgo', '654', '3B', 'Centro Histórico', 'Puebla', 'Puebla', '72000', 'México'),
('Av. Universidad', '987', NULL, 'Del Valle', 'Ciudad de México', 'CDMX', '03100', 'México'),
('Calle Juárez', '147', '2A', 'Centro', 'Monterrey', 'Nuevo León', '64000', 'México'),
('Av. Constitución', '258', NULL, 'Americana', 'Guadalajara', 'Jalisco', '44160', 'México'),
('Calle Morelos', '369', '1C', 'Centro', 'Mérida', 'Yucatán', '97000', 'México'),
('Av. Tecnológico', '741', NULL, 'Tecnológico', 'León', 'Guanajuato', '37320', 'México');

INSERT INTO sucursales (nombre, telefono, direccion_id) VALUES
('Sucursal Roma', '555-0001', 1),
('Sucursal Guadalajara Centro', '333-0001', 2),
('Sucursal Cancún Plaza', '998-0001', 3),
('Sucursal Tijuana', '664-0001', 4),
('Sucursal Puebla Centro', '222-0001', 5);

INSERT INTO puesto (nombre_puesto, descripcion) VALUES
('Gerente General', 'Responsable de la operación general de la sucursal'),
('Vendedor', 'Atención al cliente y ventas de productos'),
('Cajero', 'Manejo de caja y procesamiento de pagos'),
('Almacenista', 'Control de inventario y almacén'),
('Supervisor de Ventas', 'Supervisión del equipo de ventas');

INSERT INTO datos_personas (nombre, apellido_paterno, apellido_materno, email, fecha_nacimiento, telefono) VALUES
('Juan', 'Pérez', 'García', 'juan.perez@email.com', '1985-03-15', '555-1001'),
('María', 'González', 'López', 'maria.gonzalez@email.com', '1990-07-22', '555-1002'),
('Carlos', 'Rodríguez', 'Martínez', 'carlos.rodriguez@email.com', '1988-11-08', '555-1003'),
('Ana', 'Hernández', 'Ruiz', 'ana.hernandez@email.com', '1992-05-14', '555-1004'),
('Luis', 'Jiménez', 'Torres', 'luis.jimenez@email.com', '1987-09-30', '555-1005'),
('Carmen', 'Morales', 'Vázquez', 'carmen.morales@email.com', '1991-12-18', '555-1006'),
('Roberto', 'Castro', 'Mendoza', 'roberto.castro@email.com', '1989-02-25', '555-1007'),
('Patricia', 'Ramos', 'Flores', 'patricia.ramos@email.com', '1993-08-10', '555-1008'),
('Fernando', 'Vargas', 'Guerrero', 'fernando.vargas@email.com', '1986-04-12', '555-1009'),
('Alejandra', 'Sánchez', 'Herrera', 'alejandra.sanchez@email.com', '1994-01-05', '555-1010');

INSERT INTO clientes (datos_personas_id) VALUES
(1), (2), (3), (4), (5), (6);

INSERT INTO empleados (sucursal_id, puesto_id, datos_personas_id, fecha_contratacion) VALUES
(1, 1, 7, '2020-01-15'),  -- Roberto como Gerente en Roma
(1, 2, 8, '2021-03-10'),  -- Patricia como Vendedor en Roma
(2, 1, 9, '2019-08-20'),  -- Fernando como Gerente en Guadalajara
(2, 3, 10, '2022-02-14'); -- Alejandra como Cajero en Guadalajara

INSERT INTO clientes_direcciones (cliente_id, direccion_id, es_principal) VALUES
(1, 6, TRUE),   -- Juan vive en Del Valle
(2, 7, TRUE),   -- María vive en Monterrey
(3, 8, TRUE),   -- Carlos vive en Americana
(4, 9, TRUE),   -- Ana vive en Mérida
(5, 10, TRUE),  -- Luis vive en León
(6, 1, TRUE);   -- Carmen vive en Roma Norte

INSERT INTO proveedores (nombre_empresa, rfc, nombre_contacto, email_contacto, telefono_contacto, direccion_id) VALUES
('Electrónicos México SA', 'EMX901201ABC', 'Pedro Martínez', 'pedro@electronicos.com', '555-2001', 1),
('Textiles Guadalajara', 'TGU850315XYZ', 'Laura Vega', 'laura@textiles.com', '333-2002', 2),
('Deportes del Norte', 'DDN920710DEF', 'Miguel Ángel', 'miguel@deportes.com', '664-2003', 4),
('Hogar y Jardín SA', 'HJA880425GHI', 'Sofía Ruiz', 'sofia@hogar.com', '222-2004', 5);

INSERT INTO categorias (nombre, descripcion) VALUES
('Electrónicos', 'Dispositivos electrónicos y tecnología'),
('Ropa', 'Ropa y accesorios para toda la familia'),
('Deportes', 'Artículos deportivos y fitness'),
('Hogar', 'Artículos para el hogar y jardín'),
('Libros', 'Libros y material educativo');

INSERT INTO productos (proveedor_id, nombre, descripcion, precio, stock, sku) VALUES
(1, 'Smartphone Galaxy A54', 'Teléfono inteligente con 128GB', 8999.99, 25, 'PHONE-GAL-A54'),
(1, 'Laptop HP Pavilion', 'Laptop HP con 8GB RAM y 256GB SSD', 15999.99, 15, 'LAP-HP-PAV15'),
(1, 'Audífonos Bluetooth', 'Audífonos inalámbricos con cancelación de ruido', 2499.99, 50, 'AUD-BT-NC01'),
(2, 'Playera Básica', 'Playera de algodón 100% talla M', 299.99, 100, 'PLAY-ALG-M'),
(2, 'Jeans Clásicos', 'Jeans azul marino talla 32', 899.99, 30, 'JEAN-AZU-32'),
(3, 'Balón de Fútbol', 'Balón oficial FIFA tamaño 5', 599.99, 40, 'BAL-FUT-FIFA5'),
(3, 'Tenis Running', 'Tenis para correr talla 9 MX', 1599.99, 20, 'TEN-RUN-9MX'),
(4, 'Sartén Antiadherente', 'Sartén de 28cm antiadherente', 799.99, 35, 'SAR-ANT-28'),
(4, 'Juego de Toallas', 'Set de 4 toallas de baño', 1299.99, 25, 'TOA-SET-4');

INSERT INTO productos_categorias (producto_id, categoria_id) VALUES
(1, 1), (2, 1), (3, 1),  -- Electrónicos
(4, 2), (5, 2),          -- Ropa
(6, 3), (7, 3),          -- Deportes
(8, 4), (9, 4);          -- Hogar

INSERT INTO ordenes_compra (cliente_id, empleado_id, sucursal_id, direccion_envio_id, estado, total) VALUES
(1, 2, 1, 6, 'completada', 11499.98),  -- Juan compra smartphone + audífonos
(2, 2, 1, 7, 'pendiente', 2199.98),    -- María compra playera + balón
(3, 4, 2, 8, 'procesando', 15999.99),  -- Carlos compra laptop
(4, 4, 2, 9, 'completada', 2399.98);   -- Ana compra jeans + tenis

INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad, precio_unitario) VALUES
-- Orden 1: Juan
(1, 1, 1, 8999.99),   -- 1 Smartphone
(1, 3, 1, 2499.99),   -- 1 Audífonos
-- Orden 2: María
(2, 4, 2, 299.99),    -- 2 Playeras
(2, 6, 1, 599.99),    -- 1 Balón
-- Orden 3: Carlos
(3, 2, 1, 15999.99),  -- 1 Laptop
-- Orden 4: Ana
(4, 5, 1, 899.99),    -- 1 Jeans
(4, 7, 1, 1599.99);   -- 1 Tenis

INSERT INTO pagos (orden_id, metodo_pago, monto, estado, referencia_pago) VALUES
(1, 'tarjeta_credito', 11499.98, 'completado', 'TXN-20241201-001'),
(4, 'efectivo', 2399.98, 'completado', 'CASH-20241201-002');

INSERT INTO reseñas (cliente_id, producto_id, calificacion, comentario) VALUES
(1, 1, 5, 'Excelente teléfono, muy buena calidad de cámara'),
(1, 3, 4, 'Buenos audífonos, la batería dura bastante'),
(4, 5, 5, 'Jeans de muy buena calidad, talla perfecta'),
(4, 7, 4, 'Tenis cómodos para correr, buen precio');