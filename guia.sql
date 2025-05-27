lo que llevo
clientes
empleado
compra
provedores

-------------------------------------------------- primera cosa:

        direciones
        probedores 

********************************************************************************************************

        direciones
        surcursales

********************************************************************************************************

        datos personas
        clientes
        clientes direciones

********************************************************************************************************


puesto
datos personas
empleados

********************************************************************************************************


categorias
producto categorias
productos

********************************************************************************************************


productos
clientes
reseñas



********************************************************************************************************


tienda pagos
ordenes detalle
ordenes compra

----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------






















----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------



tienda pagos
ordenes detalle
ordenes compra

                                                                                                                                         

(ocupo con php me hagas una aplicasion estoy utilizando una base de datos MySQL donde hagas un crud y pueda ver estos datos, aqui te dejo de ejemplo esto


CREATE TABLE pagos (
    pago_id INT PRIMARY KEY AUTO_INCREMENT,
    orden_id INT NOT NULL,
    metodo_pago ENUM('tarjeta_credito', 'tarjeta_debito', 'efectivo', 'transferencia', 'paypal', 'otro') NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'completado', 'fallido', 'reembolsado') DEFAULT 'pendiente',
    referencia_pago VARCHAR(255), -- Para guardar IDs de transacción, etc.
    FOREIGN KEY (orden_id) REFERENCES ordenes_compra(orden_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ordenes_detalle (
    detalle_id INT PRIMARY KEY AUTO_INCREMENT,
    orden_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes_compra(orden_id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(producto_id) ON DELETE RESTRICT -- Evita borrar productos en órdenes
) ENGINE=InnoDB;

CREATE TABLE ordenes_compra (
    orden_id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    empleado_id INT, -- Puede ser nulo (p.ej., compra online sin vendedor asignado)
    sucursal_id INT, -- Sucursal donde se realizó o procesó la orden
    direccion_envio_id INT, -- Dirección de envío para esta orden
    fecha_orden DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'procesando', 'enviado', 'completada', 'cancelada') DEFAULT 'pendiente',
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id) ON DELETE RESTRICT, -- Evita borrar clientes con órdenes
    FOREIGN KEY (empleado_id) REFERENCES empleados(empleado_id) ON DELETE SET NULL,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(sucursal_id) ON DELETE SET NULL,
    FOREIGN KEY (direccion_envio_id) REFERENCES direcciones(direccion_id) ON DELETE SET NULL
) ENGINE=InnoDB;



puedes usar estos procedimientos almacenados, pero si no los ocupas pues no:

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

CALL sp_agregar_producto_a_orden(
    2,    -- orden_id
    4,    -- producto_id (Playera)
    1     -- cantidad
);


CALL sp_eliminar_producto_de_orden(
    2,    -- orden_id
    6     -- producto_id (Balón de Fútbol)
);

CALL sp_finalizar_compra_transaccion(
    2,                        -- orden_id
    'tarjeta_credito',        -- metodo_pago
    'TXN-20241202-003'        -- referencia_pago
);


CALL sp_actualizar_estado_orden(
    3,                        -- orden_id
    'enviado',               -- nuevo_estado
    'transferencia',         -- metodo_pago
    'TRANSFER-20241202-001'  -- referencia_pago
);



DELIMITER //

CREATE PROCEDURE sp_eliminar_producto_de_orden(
    IN p_orden_id INT,
    IN p_producto_id INT
)
BEGIN
    -- Iniciar transacción
    START TRANSACTION;

    -- Verificar si la orden existe y no está completada o cancelada
    IF NOT EXISTS (SELECT 1 FROM ordenes_compra WHERE orden_id = p_orden_id AND estado IN ('pendiente', 'procesando')) THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden no existe o no está en un estado editable.';
    END IF;

    -- Eliminar el detalle de la orden
    DELETE FROM ordenes_detalle
    WHERE orden_id = p_orden_id AND producto_id = p_producto_id;

    -- Los triggers AFTER DELETE en ordenes_detalle se encargarán de:
    -- 1. Devolver el stock a la tabla `productos`.
    -- 2. Recalcular el `total` en `ordenes_compra`.

    COMMIT;
END //



DELIMITER //

CREATE PROCEDURE sp_agregar_producto_a_orden(
    IN p_orden_id INT,
    IN p_producto_id INT,
    IN p_cantidad INT
)
BEGIN
    DECLARE v_precio_unitario DECIMAL(10,2);
    DECLARE v_stock_disponible INT;

    -- Iniciar transacción para asegurar la consistencia
    START TRANSACTION;

    -- Verificar si la orden existe y no está completada o cancelada
    IF NOT EXISTS (SELECT 1 FROM ordenes_compra WHERE orden_id = p_orden_id AND estado IN ('pendiente', 'procesando')) THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden no existe o no está en un estado editable.';
    END IF;

    -- Obtener el precio y stock del producto
    SELECT precio, stock INTO v_precio_unitario, v_stock_disponible
    FROM productos
    WHERE producto_id = p_producto_id;

    -- Validar que el producto exista
    IF v_precio_unitario IS NULL THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El producto especificado no existe.';
    END IF;

    -- Validar stock
    IF v_stock_disponible < p_cantidad THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente para el producto solicitado.';
    END IF;

    -- Insertar o actualizar el detalle de la orden
    INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad, precio_unitario)
    VALUES (p_orden_id, p_producto_id, p_cantidad, v_precio_unitario)
    ON DUPLICATE KEY UPDATE
        cantidad = cantidad + VALUES(cantidad); -- Si el producto ya está en la orden, solo suma la cantidad

    -- Los triggers AFTER INSERT/UPDATE en ordenes_detalle se encargarán de:
    -- 1. Actualizar el stock en la tabla `productos`.
    -- 2. Recalcular el `total` en `ordenes_compra`.

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

    -- Validar que la orden exista
    IF NOT EXISTS (SELECT 1 FROM ordenes_compra WHERE orden_id = p_orden_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden especificada no existe.';
    END IF;

    -- Obtener el total de la orden para el pago
    SELECT total INTO v_total_orden FROM ordenes_compra WHERE orden_id = p_orden_id;

    -- Actualizar el estado de la orden
    UPDATE ordenes_compra
    SET estado = p_nuevo_estado
    WHERE orden_id = p_orden_id;

    -- Si el estado es 'completada' y no hay un pago asociado, registrar un pago
    IF p_nuevo_estado = 'completada' AND NOT EXISTS (SELECT 1 FROM pagos WHERE orden_id = p_orden_id AND estado = 'completado') THEN
        INSERT INTO pagos (orden_id, metodo_pago, monto, estado, referencia_pago)
        VALUES (p_orden_id, p_metodo_pago, v_total_orden, 'completado', p_referencia_pago);
    END IF;

END //

DELIMITER ;



DELIMITER //

CREATE PROCEDURE sp_registrar_nueva_orden(
    IN p_cliente_id INT,
    IN p_empleado_id INT,
    IN p_sucursal_id INT,
    IN p_direccion_envio_id INT,
    IN p_productos_json JSON -- Formato: [{"producto_id": 1, "cantidad": 2}, {"producto_id": 3, "cantidad": 1}]
)
BEGIN
    DECLARE v_orden_id INT;
    DECLARE v_producto_id INT;
    DECLARE v_cantidad INT;
    DECLARE v_precio_unitario DECIMAL(10,2);
    DECLARE i INT DEFAULT 0;
    DECLARE num_productos INT;

    -- Iniciar transacción para asegurar la consistencia
    START TRANSACTION;

    -- Insertar la orden de compra
    INSERT INTO ordenes_compra (cliente_id, empleado_id, sucursal_id, direccion_envio_id, estado)
    VALUES (p_cliente_id, p_empleado_id, p_sucursal_id, p_direccion_envio_id, 'pendiente');

    SET v_orden_id = LAST_INSERT_ID();

    -- Obtener el número de productos en el JSON
    SET num_productos = JSON_LENGTH(p_productos_json);

    -- Recorrer los productos y agregarlos a los detalles de la orden
    WHILE i < num_productos DO
        SET v_producto_id = JSON_EXTRACT(p_productos_json, CONCAT('$[', i, '].producto_id'));
        SET v_cantidad = JSON_EXTRACT(p_productos_json, CONCAT('$[', i, '].cantidad'));

        -- Obtener el precio unitario actual del producto
        SELECT precio INTO v_precio_unitario FROM productos WHERE producto_id = v_producto_id;

        -- Verificar que haya suficiente stock
        IF (SELECT stock FROM productos WHERE producto_id = v_producto_id) >= v_cantidad THEN
            INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad, precio_unitario)
            VALUES (v_orden_id, v_producto_id, v_cantidad, v_precio_unitario);
        ELSE
            -- Si no hay suficiente stock, hacer ROLLBACK y lanzar un error
            ROLLBACK;
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente para uno de los productos.';
        END IF;

        SET i = i + 1;
    END WHILE;

    -- Confirmar la transacción
    COMMIT;

END //



DELIMITER //

CREATE PROCEDURE sp_finalizar_compra_transaccion(
    IN p_orden_id INT,
    IN p_metodo_pago ENUM('tarjeta_credito', 'tarjeta_debito', 'efectivo', 'transferencia', 'paypal', 'otro'),
    IN p_referencia_pago VARCHAR(255)
)
BEGIN
    DECLARE v_total_orden DECIMAL(12,2);
    DECLARE v_detalle_id INT;
    DECLARE v_producto_id INT;
    DECLARE v_cantidad INT;
    DECLARE v_stock_actual INT;
    DECLARE v_error_msg VARCHAR(500);
    DECLARE done INT DEFAULT FALSE;
    
    -- Cursor para iterar sobre los productos en la orden
    DECLARE cur_productos CURSOR FOR
        SELECT detalle_id, producto_id, cantidad
        FROM ordenes_detalle
        WHERE orden_id = p_orden_id;
    
    -- Handler para cuando no hay más filas en el cursor
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Iniciar la transacción
    START TRANSACTION;
    
    -- 1. Verificar si la orden existe y está en estado "pendiente" o "procesando"
    SELECT total INTO v_total_orden
    FROM ordenes_compra
    WHERE orden_id = p_orden_id AND estado IN ('pendiente', 'procesando')
    FOR UPDATE; -- Bloquear la fila para evitar concurrencia
    
    IF v_total_orden IS NULL THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden no existe o no está en un estado válido para finalizar.';
    END IF;
    
    -- 2. Validar stock para cada producto en la orden
    OPEN cur_productos;
    
    read_loop: LOOP
        FETCH cur_productos INTO v_detalle_id, v_producto_id, v_cantidad;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SELECT stock INTO v_stock_actual 
        FROM productos 
        WHERE producto_id = v_producto_id 
        FOR UPDATE; -- Bloquear stock
        
        IF v_stock_actual < v_cantidad THEN
            -- Si el stock es insuficiente, se deshace toda la transacción
            CLOSE cur_productos;
            ROLLBACK;
            -- Crear mensaje de error usando variables
            SET v_error_msg = CONCAT('Stock insuficiente para el producto ID: ', 
                                   CAST(v_producto_id AS CHAR), 
                                   '. Disponible: ', 
                                   CAST(v_stock_actual AS CHAR), 
                                   ', Solicitado: ', 
                                   CAST(v_cantidad AS CHAR));
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
        END IF;
        
        -- Actualizar stock (esto también lo hace el trigger, pero es buena práctica hacerlo aquí para el rollback inmediato)
        -- UPDATE productos SET stock = stock - v_cantidad WHERE producto_id = v_producto_id;
        -- NOTA: Los triggers ya se encargan de esto al insertar/actualizar/eliminar ordenes_detalle.
        -- Pero si decides no usar los triggers para stock, esta línea sería necesaria aquí.
    END LOOP;
    
    CLOSE cur_productos;
    
    -- 3. Actualizar el estado de la orden a 'completada'
    UPDATE ordenes_compra
    SET estado = 'completada'
    WHERE orden_id = p_orden_id;
    
    -- 4. Registrar el pago
    INSERT INTO pagos (orden_id, metodo_pago, monto, estado, referencia_pago)
    VALUES (p_orden_id, p_metodo_pago, v_total_orden, 'completado', p_referencia_pago);
    
    -- Confirmar todos los cambios
    COMMIT;
    
END //