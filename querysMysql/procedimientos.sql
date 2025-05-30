DELIMITER //

CREATE PROCEDURE sp_registrar_nueva_orden(
    IN p_cliente_id INT,
    IN p_empleado_id INT,
    IN p_sucursal_id INT,
    IN p_direccion_envio_id INT,
    IN p_productos_json JSON 
)
BEGIN
    DECLARE v_orden_id INT;
    DECLARE v_producto_id INT;
    DECLARE v_cantidad INT;
    DECLARE v_precio_unitario DECIMAL(10,2);
    DECLARE i INT DEFAULT 0;
    DECLARE num_productos INT;

    
    START TRANSACTION;

    
    INSERT INTO ordenes_compra (cliente_id, empleado_id, sucursal_id, direccion_envio_id, estado)
    VALUES (p_cliente_id, p_empleado_id, p_sucursal_id, p_direccion_envio_id, 'pendiente');

    SET v_orden_id = LAST_INSERT_ID();

    
    SET num_productos = JSON_LENGTH(p_productos_json);

    
    WHILE i < num_productos DO
        SET v_producto_id = JSON_EXTRACT(p_productos_json, CONCAT('$[', i, '].producto_id'));
        SET v_cantidad = JSON_EXTRACT(p_productos_json, CONCAT('$[', i, '].cantidad'));

    
        SELECT precio INTO v_precio_unitario FROM productos WHERE producto_id = v_producto_id;

    
        IF (SELECT stock FROM productos WHERE producto_id = v_producto_id) >= v_cantidad THEN
            INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad, precio_unitario)
            VALUES (v_orden_id, v_producto_id, v_cantidad, v_precio_unitario);
        ELSE
    
            ROLLBACK;
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente para uno de los productos.';
        END IF;

        SET i = i + 1;
    END WHILE;

    
    COMMIT;

END //

DELIMITER ;













DELIMITER //

CREATE PROCEDURE sp_actualizar_estado_orden(
    IN p_orden_id INT,
    IN p_nuevo_estado ENUM('pendiente', 'procesando', 'enviado', 'completada', 'cancelada'),
    IN p_metodo_pago ENUM('tarjeta_credito', 'tarjeta_debito', 'efectivo', 'transferencia', 'paypal', 'otro'),
    IN p_referencia_pago VARCHAR(255)
)
BEGIN
    DECLARE v_total_orden DECIMAL(12,2);

    
    IF NOT EXISTS (SELECT 1 FROM ordenes_compra WHERE orden_id = p_orden_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden especificada no existe.';
    END IF;

    
    SELECT total INTO v_total_orden FROM ordenes_compra WHERE orden_id = p_orden_id;

    
    UPDATE ordenes_compra
    SET estado = p_nuevo_estado
    WHERE orden_id = p_orden_id;

    
    IF p_nuevo_estado = 'completada' AND NOT EXISTS (SELECT 1 FROM pagos WHERE orden_id = p_orden_id AND estado = 'completado') THEN
        INSERT INTO pagos (orden_id, metodo_pago, monto, estado, referencia_pago)
        VALUES (p_orden_id, p_metodo_pago, v_total_orden, 'completado', p_referencia_pago);
    END IF;

END //

DELIMITER ;










DELIMITER //

CREATE PROCEDURE sp_obtener_info_cliente(
    IN p_cliente_id INT
)
BEGIN
    
    SELECT
        c.cliente_id,
        dp.nombre,
        dp.apellido_paterno,
        dp.apellido_materno,
        dp.email,
        dp.fecha_nacimiento,
        dp.telefono,
        c.created_at AS cliente_desde
    FROM clientes c
    JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
    WHERE c.cliente_id = p_cliente_id;

    
    SELECT
        d.calle,
        d.numero_exterior,
        d.numero_interior,
        d.colonia,
        d.ciudad,
        d.estado,
        d.codigo_postal,
        d.pais,
        cd.es_principal
    FROM clientes_direcciones cd
    JOIN direcciones d ON cd.direccion_id = d.direccion_id
    WHERE cd.cliente_id = p_cliente_id;

    
    SELECT
        oc.orden_id,
        oc.fecha_orden,
        oc.estado,
        oc.total,
        s.nombre AS sucursal_nombre
    FROM ordenes_compra oc
    LEFT JOIN sucursales s ON oc.sucursal_id = s.sucursal_id
    WHERE oc.cliente_id = p_cliente_id
    ORDER BY oc.fecha_orden DESC
    LIMIT 10; 
END //

DELIMITER ;












DELIMITER //

CREATE PROCEDURE sp_reporte_ventas_por_periodo(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE
)
BEGIN
    SELECT
        oc.orden_id,
        oc.fecha_orden,
        oc.total,
        c.cliente_id,
        CONCAT(dp.nombre, ' ', dp.apellido_paterno) AS nombre_cliente,
        s.nombre AS sucursal,
        e.empleado_id,
        CONCAT(dpe.nombre, ' ', dpe.apellido_paterno) AS nombre_empleado
    FROM ordenes_compra oc
    JOIN clientes c ON oc.cliente_id = c.cliente_id
    JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
    LEFT JOIN sucursales s ON oc.sucursal_id = s.sucursal_id
    LEFT JOIN empleados e ON oc.empleado_id = e.empleado_id
    LEFT JOIN datos_personas dpe ON e.datos_personas_id = dpe.datos_personas_id
    WHERE oc.fecha_orden >= p_fecha_inicio AND oc.fecha_orden <= p_fecha_fin
    ORDER BY oc.fecha_orden ASC;
END //

DELIMITER ;



















DELIMITER //

CREATE PROCEDURE sp_reporte_productos_mas_vendidos(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE,
    IN p_limite INT
)
BEGIN
    SELECT
        p.producto_id,
        p.nombre AS nombre_producto,
        SUM(od.cantidad) AS total_cantidad_vendida,
        SUM(od.cantidad * od.precio_unitario) AS total_ingresos_generados
    FROM ordenes_detalle od
    JOIN productos p ON od.producto_id = p.producto_id
    JOIN ordenes_compra oc ON od.orden_id = oc.orden_id
    WHERE oc.fecha_orden >= p_fecha_inicio AND oc.fecha_orden <= p_fecha_fin
    GROUP BY p.producto_id, p.nombre
    ORDER BY total_cantidad_vendida DESC
    LIMIT p_limite;
END //

DELIMITER ;

















DELIMITER //

CREATE PROCEDURE sp_obtener_estadisticas_generales()
BEGIN
    SELECT COUNT(*) AS total_clientes FROM clientes;

    SELECT COUNT(*) AS total_productos, SUM(stock) AS stock_total_productos FROM productos;

    SELECT COUNT(*) AS total_ordenes_completadas, SUM(total) AS ingresos_totales
    FROM ordenes_compra
    WHERE estado = 'completada';

    SELECT
        producto_id,
        nombre AS nombre_producto,
        stock
    FROM productos
    WHERE stock < 10
    ORDER BY stock ASC;

    SELECT AVG(calificacion) AS calificacion_promedio_reseñas FROM reseñas;

    SELECT
        c.cliente_id,
        CONCAT(dp.nombre, ' ', dp.apellido_paterno) AS nombre_cliente,
        COUNT(oc.orden_id) AS numero_ordenes_compradas
    FROM clientes c
    JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
    JOIN ordenes_compra oc ON c.cliente_id = oc.cliente_id
    GROUP BY c.cliente_id, nombre_cliente
    ORDER BY numero_ordenes_compradas DESC
    LIMIT 5;

END //

DELIMITER ;













DELIMITER //

CREATE PROCEDURE sp_agregar_producto_a_orden(
    IN p_orden_id INT,
    IN p_producto_id INT,
    IN p_cantidad INT
)
BEGIN
    DECLARE v_precio_unitario DECIMAL(10,2);
    DECLARE v_stock_disponible INT;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM ordenes_compra WHERE orden_id = p_orden_id AND estado IN ('pendiente', 'procesando')) THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden no existe o no está en un estado editable.';
    END IF;

    SELECT precio, stock INTO v_precio_unitario, v_stock_disponible
    FROM productos
    WHERE producto_id = p_producto_id;

    IF v_precio_unitario IS NULL THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El producto especificado no existe.';
    END IF;

    IF v_stock_disponible < p_cantidad THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente para el producto solicitado.';
    END IF;

    INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad, precio_unitario)
    VALUES (p_orden_id, p_producto_id, p_cantidad, v_precio_unitario)
    ON DUPLICATE KEY UPDATE
        cantidad = cantidad + VALUES(cantidad); 


    COMMIT;
END //

DELIMITER ;





















DELIMITER //

CREATE PROCEDURE sp_eliminar_producto_de_orden(
    IN p_orden_id INT,
    IN p_producto_id INT
)
BEGIN
    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM ordenes_compra WHERE orden_id = p_orden_id AND estado IN ('pendiente', 'procesando')) THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden no existe o no está en un estado editable.';
    END IF;

    DELETE FROM ordenes_detalle
    WHERE orden_id = p_orden_id AND producto_id = p_producto_id;


    COMMIT;
END //

DELIMITER ;















DELIMITER //

CREATE PROCEDURE sp_registrar_nueva_persona(
    IN p_nombre VARCHAR(100),
    IN p_apellido_paterno VARCHAR(100),
    IN p_apellido_materno VARCHAR(100),
    IN p_email VARCHAR(255),
    IN p_fecha_nacimiento DATE,
    IN p_telefono VARCHAR(20),
    OUT p_datos_personas_id INT
)
BEGIN
    IF EXISTS (SELECT 1 FROM datos_personas WHERE email = p_email) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El email ya está registrado para otra persona.';
    END IF;

    INSERT INTO datos_personas (nombre, apellido_paterno, apellido_materno, email, fecha_nacimiento, telefono)
    VALUES (p_nombre, p_apellido_paterno, p_apellido_materno, p_email, p_fecha_nacimiento, p_telefono);

    SET p_datos_personas_id = LAST_INSERT_ID();
END //

DELIMITER ;















DELIMITER //

CREATE PROCEDURE sp_crear_cliente(
    IN p_nombre VARCHAR(100),
    IN p_apellido_paterno VARCHAR(100),
    IN p_apellido_materno VARCHAR(100),
    IN p_email VARCHAR(255),
    IN p_fecha_nacimiento DATE,
    IN p_telefono VARCHAR(20),
    OUT p_cliente_id INT
)
BEGIN
    DECLARE v_datos_personas_id INT;

    CALL sp_registrar_nueva_persona(p_nombre, p_apellido_paterno, p_apellido_materno, p_email, p_fecha_nacimiento, p_telefono, v_datos_personas_id);

    IF v_datos_personas_id IS NOT NULL THEN
        INSERT INTO clientes (datos_personas_id)
        VALUES (v_datos_personas_id);
        SET p_cliente_id = LAST_INSERT_ID();
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error al crear la persona para el cliente.';
    END IF;
END //

DELIMITER ;

















DELIMITER //

CREATE PROCEDURE sp_crear_empleado(
    IN p_nombre VARCHAR(100),
    IN p_apellido_paterno VARCHAR(100),
    IN p_apellido_materno VARCHAR(100),
    IN p_email VARCHAR(255),
    IN p_fecha_nacimiento DATE,
    IN p_telefono VARCHAR(20),
    IN p_sucursal_id INT,
    IN p_puesto_id INT,
    IN p_fecha_contratacion DATE,
    OUT p_empleado_id INT
)
BEGIN
    DECLARE v_datos_personas_id INT;

    IF NOT EXISTS (SELECT 1 FROM sucursales WHERE sucursal_id = p_sucursal_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La sucursal especificada no existe.';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM puesto WHERE puesto_id = p_puesto_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El puesto especificado no existe.';
    END IF;

    CALL sp_registrar_nueva_persona(p_nombre, p_apellido_paterno, p_apellido_materno, p_email, p_fecha_nacimiento, p_telefono, v_datos_personas_id);

    IF v_datos_personas_id IS NOT NULL THEN
        INSERT INTO empleados (sucursal_id, puesto_id, datos_personas_id, fecha_contratacion)
        VALUES (p_sucursal_id, p_puesto_id, v_datos_personas_id, p_fecha_contratacion);
        SET p_empleado_id = LAST_INSERT_ID();
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error al crear la persona para el empleado.';
    END IF;
END //

DELIMITER ;