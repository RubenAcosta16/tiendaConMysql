DELIMITER //

CREATE TRIGGER tr_actualizar_stock_after_insert_orden_detalle
AFTER INSERT ON ordenes_detalle
FOR EACH ROW
BEGIN
    UPDATE productos
    SET stock = stock - NEW.cantidad
    WHERE producto_id = NEW.producto_id;
END //

DELIMITER ;











DELIMITER //

CREATE TRIGGER tr_actualizar_stock_after_update_orden_detalle
AFTER UPDATE ON ordenes_detalle
FOR EACH ROW
BEGIN
    IF OLD.cantidad <> NEW.cantidad THEN
        UPDATE productos
        SET stock = stock + OLD.cantidad - NEW.cantidad
        WHERE producto_id = NEW.producto_id;
    END IF;
END //

DELIMITER ;









DELIMITER //

CREATE TRIGGER tr_actualizar_stock_after_delete_orden_detalle
AFTER DELETE ON ordenes_detalle
FOR EACH ROW
BEGIN
    UPDATE productos
    SET stock = stock + OLD.cantidad
    WHERE producto_id = OLD.producto_id;
END //

DELIMITER ;











DELIMITER //

CREATE TRIGGER tr_actualizar_total_orden_after_insert_detalle
AFTER INSERT ON ordenes_detalle
FOR EACH ROW
BEGIN
    UPDATE ordenes_compra
    SET total = (SELECT SUM(cantidad * precio_unitario) FROM ordenes_detalle WHERE orden_id = NEW.orden_id)
    WHERE orden_id = NEW.orden_id;
END //

DELIMITER ;











DELIMITER //

CREATE TRIGGER tr_actualizar_total_orden_after_update_detalle
AFTER UPDATE ON ordenes_detalle
FOR EACH ROW
BEGIN
    UPDATE ordenes_compra
    SET total = (SELECT SUM(cantidad * precio_unitario) FROM ordenes_detalle WHERE orden_id = NEW.orden_id)
    WHERE orden_id = NEW.orden_id;
END //

DELIMITER ;














DELIMITER //

CREATE TRIGGER tr_actualizar_total_orden_after_delete_detalle
AFTER DELETE ON ordenes_detalle
FOR EACH ROW
BEGIN
    UPDATE ordenes_compra
    SET total = (SELECT COALESCE(SUM(cantidad * precio_unitario), 0) FROM ordenes_detalle WHERE orden_id = OLD.orden_id)
    WHERE orden_id = OLD.orden_id;
END //

DELIMITER ;














DELIMITER //

CREATE TRIGGER tr_bitacora_productos_insert
AFTER INSERT ON productos
FOR EACH ROW
BEGIN
    INSERT INTO bitacora (tabla_afectada, registro_id, operacion, usuario_db, datos_nuevos)
    VALUES ('productos', NEW.producto_id, 'INSERT', USER(),
            CONCAT('{"proveedor_id":', NEW.proveedor_id,
                   ',"nombre":"', NEW.nombre,
                   '","precio":', NEW.precio,
                   ',"stock":', NEW.stock, '}'));
END //

DELIMITER ;















DELIMITER //

CREATE TRIGGER tr_bitacora_productos_update
AFTER UPDATE ON productos
FOR EACH ROW
BEGIN
    INSERT INTO bitacora (tabla_afectada, registro_id, operacion, usuario_db, datos_viejos, datos_nuevos)
    VALUES ('productos', NEW.producto_id, 'UPDATE', USER(),
            CONCAT('{"proveedor_id":', OLD.proveedor_id,
                   ',"nombre":"', OLD.nombre,
                   '","precio":', OLD.precio,
                   '","stock":', OLD.stock, '}'),
            CONCAT('{"proveedor_id":', NEW.proveedor_id,
                   ',"nombre":"', NEW.nombre,
                   '","precio":', NEW.precio,
                   ',"stock":', NEW.stock, '}'));
END //

DELIMITER ;











DELIMITER //

CREATE TRIGGER tr_bitacora_productos_delete
AFTER DELETE ON productos
FOR EACH ROW
BEGIN
    INSERT INTO bitacora (tabla_afectada, registro_id, operacion, usuario_db, datos_viejos)
    VALUES ('productos', OLD.producto_id, 'DELETE', USER(),
            CONCAT('{"proveedor_id":', OLD.proveedor_id,
                   ',"nombre":"', OLD.nombre,
                   '","precio":', OLD.precio,
                   '","stock":', OLD.stock, '}'));
END //

DELIMITER ;