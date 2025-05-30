
CALL sp_crear_cliente(
    'José',                    -- nombre
    'Martínez',               -- apellido_paterno
    'López',                  -- apellido_materno
    'jose.martinez@email.com', -- email
    '1995-06-20',             -- fecha_nacimiento
    '555-3001',               -- telefono
    @nuevo_cliente_id         -- variable de salida para obtener el ID
);

SELECT @nuevo_cliente_id AS 'ID del Cliente Creado';

CALL sp_crear_empleado(
    'Diana',                  -- nombre
    'Flores',                 -- apellido_paterno
    'Mendoza',               -- apellido_materno
    'diana.flores@tienda.com', -- email
    '1991-03-10',            -- fecha_nacimiento
    '555-3002',              -- telefono
    1,                       -- sucursal_id (Roma)
    2,                       -- puesto_id (Vendedor)
    '2024-01-15',            -- fecha_contratacion
    @nuevo_empleado_id       -- variable de salida
);

SELECT @nuevo_empleado_id AS 'ID del Empleado Creado';

SET @productos_json = JSON_ARRAY(
    JSON_OBJECT('producto_id', 1, 'cantidad', 1),  -- 1 Smartphone
    JSON_OBJECT('producto_id', 3, 'cantidad', 2),  -- 2 Audífonos
    JSON_OBJECT('producto_id', 8, 'cantidad', 1)   -- 1 Sartén
);

CALL sp_registrar_nueva_orden(
    1,                        -- cliente_id
    2,                        -- empleado_id (Patricia)
    1,                        -- sucursal_id (Roma)
    6,                        -- direccion_envio_id
    @productos_json           -- productos en formato JSON
);

SELECT * FROM ordenes_compra ORDER BY orden_id DESC LIMIT 1;

CALL sp_agregar_producto_a_orden(
    2,    -- orden_id
    4,    -- producto_id (Playera)
    1     -- cantidad
);

SELECT 
    od.orden_id,
    p.nombre AS producto,
    od.cantidad,
    od.precio_unitario,
    (od.cantidad * od.precio_unitario) AS subtotal
FROM ordenes_detalle od
JOIN productos p ON od.producto_id = p.producto_id
WHERE od.orden_id = 2;

CALL sp_eliminar_producto_de_orden(
    2,    -- orden_id
    6     -- producto_id (Balón de Fútbol)
);

CALL sp_finalizar_compra_transaccion(
    2,                        -- orden_id
    'tarjeta_credito',        -- metodo_pago
    'TXN-20241202-003'        -- referencia_pago
);

SELECT 
    oc.orden_id,
    oc.estado,
    oc.total,
    p.metodo_pago,
    p.monto,
    p.estado AS estado_pago
FROM ordenes_compra oc
LEFT JOIN pagos p ON oc.orden_id = p.orden_id
WHERE oc.orden_id = 2;

CALL sp_actualizar_estado_orden(
    3,                        -- orden_id
    'enviado',               -- nuevo_estado
    'transferencia',         -- metodo_pago
    'TRANSFER-20241202-001'  -- referencia_pago
);

CALL sp_obtener_info_cliente(1);

CALL sp_reporte_ventas_por_periodo('2024-11-01', '2025-12-31');

CALL sp_reporte_productos_mas_vendidos('2024-10-01', '2024-12-31', 5);

CALL sp_obtener_estadisticas_generales();

SELECT 
    p.sku,
    p.nombre,
    p.stock,
    p.precio,
    c.nombre AS categoria
FROM productos p
JOIN productos_categorias pc ON p.producto_id = pc.producto_id
JOIN categorias c ON pc.categoria_id = c.categoria_id
ORDER BY p.stock ASC;

-- Ver empleados por sucursal
SELECT 
    s.nombre AS sucursal,
    CONCAT(dp.nombre, ' ', dp.apellido_paterno) AS empleado,
    pu.nombre_puesto AS puesto,
    e.fecha_contratacion
FROM empleados e
JOIN sucursales s ON e.sucursal_id = s.sucursal_id
JOIN datos_personas dp ON e.datos_personas_id = dp.datos_personas_id
JOIN puesto pu ON e.puesto_id = pu.puesto_id
ORDER BY s.nombre, pu.nombre_puesto;

-- Ver órdenes pendientes de procesar
SELECT 
    oc.orden_id,
    CONCAT(dp.nombre, ' ', dp.apellido_paterno) AS cliente,
    oc.fecha_orden,
    oc.total,
    s.nombre AS sucursal
FROM ordenes_compra oc
JOIN clientes c ON oc.cliente_id = c.cliente_id
JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
LEFT JOIN sucursales s ON oc.sucursal_id = s.sucursal_id
WHERE oc.estado IN ('pendiente', 'procesando')
ORDER BY oc.fecha_orden;

-- Ver productos sin stock
SELECT 
    p.sku,
    p.nombre,
    p.stock,
    pr.nombre_empresa AS proveedor
FROM productos p
JOIN proveedores pr ON p.proveedor_id = pr.proveedor_id
WHERE p.stock = 0;

-- (debe fallar)
CALL sp_crear_cliente(
    'Otro',
    'Usuario',
    'Test',
    'juan.perez22@email.com',  -- Email ya existe
    '1990-01-01',
    '555-9999',
    @cliente_error
);

-- (debe fallar)
CALL sp_agregar_producto_a_orden(
    1,    -- orden_id (completada)
    4,    -- producto_id
    1     -- cantidad
);

-- (debe fallar)
CALL sp_finalizar_compra_transaccion(
    999,                     -- orden_id que no existe
    'efectivo',
    'TEST-REF'
);

-- Para resetear una orden específica a estado pendiente (útil para testing)
UPDATE ordenes_compra SET estado = 'pendiente' WHERE orden_id = 2;
DELETE FROM pagos WHERE orden_id = 2;



































-- cosas pa ver si elimino algo lo demas se elimina

CALL sp_crear_cliente(
    'Ruben',                    -- nombre
    'Acosta',               -- apellido_paterno
    'Guerrero',                  -- apellido_materno
    '151ruben@gmail.com', -- email
    '2004-08-16',             -- fecha_nacimiento
    '6142841891',               -- telefono
    @nuevo_cliente_id         -- variable de salida para obtener el ID
);

select * from datos_personas where datos_personas_id =15;
select * from clientes where datos_personas_id =15; -- 10

INSERT INTO reseñas (cliente_id, producto_id, calificacion, comentario) VALUES
(10, 1, 5, 'Al 100 lets gooooooooo');

SELECT * FROM tienda.reseñas where cliente_id =10;

DELETE FROM clientes WHERE cliente_id=10;










