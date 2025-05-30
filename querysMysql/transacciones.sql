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
    
    DECLARE cur_productos CURSOR FOR
        SELECT detalle_id, producto_id, cantidad
        FROM ordenes_detalle
        WHERE orden_id = p_orden_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    SELECT total INTO v_total_orden
    FROM ordenes_compra
    WHERE orden_id = p_orden_id AND estado IN ('pendiente', 'procesando')
    FOR UPDATE; 
    
    IF v_total_orden IS NULL THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La orden no existe o no está en un estado válido para finalizar.';
    END IF;
    
    OPEN cur_productos;
    
    read_loop: LOOP
        FETCH cur_productos INTO v_detalle_id, v_producto_id, v_cantidad;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SELECT stock INTO v_stock_actual 
        FROM productos 
        WHERE producto_id = v_producto_id 
        FOR UPDATE; 
        
        IF v_stock_actual < v_cantidad THEN
            CLOSE cur_productos;
            ROLLBACK;
            SET v_error_msg = CONCAT('Stock insuficiente para el producto ID: ', 
                                   CAST(v_producto_id AS CHAR), 
                                   '. Disponible: ', 
                                   CAST(v_stock_actual AS CHAR), 
                                   ', Solicitado: ', 
                                   CAST(v_cantidad AS CHAR));
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
        END IF;

    END LOOP;
    
    CLOSE cur_productos;
    
    UPDATE ordenes_compra
    SET estado = 'completada'
    WHERE orden_id = p_orden_id;

    INSERT INTO pagos (orden_id, metodo_pago, monto, estado, referencia_pago)
    VALUES (p_orden_id, p_metodo_pago, v_total_orden, 'completado', p_referencia_pago);
    
    COMMIT;
    
END //

DELIMITER ;