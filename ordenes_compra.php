<?php
// ordenes_compra.php
require_once 'db.php';

$conn = connectDB();

$message = '';

// --- Lógica para Crear/Actualizar Orden (usando SP) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_orden'])) {
        $cliente_id = $_POST['cliente_id'];
        $empleado_id = $_POST['empleado_id'] ?: NULL; // NULL si está vacío
        $sucursal_id = $_POST['sucursal_id'] ?: NULL; // NULL si está vacío
        $direccion_envio_id = $_POST['direccion_envio_id'] ?: NULL; // NULL si está vacío
        $productos_json = json_encode($_POST['productos_seleccionados']); // Array de {producto_id, cantidad}

        try {
            $stmt = $conn->prepare("CALL sp_registrar_nueva_orden(?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $cliente_id, $empleado_id, $sucursal_id, $direccion_envio_id, $productos_json);
            if ($stmt->execute()) {
                $result_sp = $stmt->get_result();
                $row_sp = $result_sp->fetch_assoc();
                $new_orden_id = $row_sp['new_orden_id'];
                $total_calculated = $row_sp['total_calculated'];
                $message = "<p class='success'>Orden #{$new_orden_id} creada exitosamente con total: $" . number_format($total_calculated, 2) . "</p>";
                // Limpiar los resultados del SP antes de ejecutar otra query
                while($conn->more_results() && $conn->next_result()){}
            } else {
                throw new Exception("Error al ejecutar sp_registrar_nueva_orden: " . $stmt->error);
            }
        } catch (Exception $e) {
            $message = "<p class='error'>Error: " . $e->getMessage() . "</p>";
        } finally {
            if (isset($stmt)) $stmt->close();
        }
    } elseif (isset($_POST['update_estado_orden'])) {
        $orden_id = $_POST['orden_id'];
        $nuevo_estado = $_POST['estado'];
        $metodo_pago = $_POST['metodo_pago'] ?: NULL;
        $referencia_pago = $_POST['referencia_pago'] ?: NULL;

        try {
            $stmt = $conn->prepare("CALL sp_actualizar_estado_orden(?, ?, ?, ?)");
            $stmt->bind_param("isss", $orden_id, $nuevo_estado, $metodo_pago, $referencia_pago);
            if ($stmt->execute()) {
                $message = "<p class='success'>Estado de la orden #{$orden_id} actualizado a '{$nuevo_estado}' exitosamente.</p>";
                while($conn->more_results() && $conn->next_result()){}
            } else {
                throw new Exception("Error al ejecutar sp_actualizar_estado_orden: " . $stmt->error);
            }
        } catch (Exception $e) {
            $message = "<p class='error'>Error: " . $e->getMessage() . "</p>";
        } finally {
            if (isset($stmt)) $stmt->close();
        }
    } elseif (isset($_POST['add_producto_to_orden'])) {
        $orden_id = $_POST['orden_id_detalle'];
        $producto_id = $_POST['producto_id_detalle'];
        $cantidad = $_POST['cantidad_detalle'];

        try {
            $stmt = $conn->prepare("CALL sp_agregar_producto_a_orden(?, ?, ?)");
            $stmt->bind_param("iii", $orden_id, $producto_id, $cantidad);
            if ($stmt->execute()) {
                $result_sp = $stmt->get_result();
                $row_sp = $result_sp->fetch_assoc();
                $new_total = $row_sp['new_total_orden'];
                $message = "<p class='success'>Producto agregado a la orden #{$orden_id}. Nuevo total: $" . number_format($new_total, 2) . "</p>";
                while($conn->more_results() && $conn->next_result()){}
            } else {
                throw new Exception("Error al ejecutar sp_agregar_producto_a_orden: " . $stmt->error);
            }
        } catch (Exception $e) {
            $message = "<p class='error'>Error: " . $e->getMessage() . "</p>";
        } finally {
            if (isset($stmt)) $stmt->close();
        }
    }
}

// --- Lógica para Eliminar Orden/Detalle (con validación de SP si aplica) ---

// Eliminar una Orden completa (la FK en pagos y ordenes_detalle con ON DELETE CASCADE se encargará)
if (isset($_GET['delete_orden'])) {
    $orden_id = $_GET['delete_orden'];
    $stmt = $conn->prepare("DELETE FROM ordenes_compra WHERE orden_id = ?");
    $stmt->bind_param("i", $orden_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Orden #{$orden_id} y sus detalles/pagos eliminados exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar orden: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// Eliminar un producto de una orden usando el SP
if (isset($_GET['delete_detalle_orden'])) {
    list($orden_id, $producto_id) = explode('_', $_GET['delete_detalle_orden']);

    try {
        $stmt = $conn->prepare("CALL sp_eliminar_producto_de_orden(?, ?)");
        $stmt->bind_param("ii", $orden_id, $producto_id);
        if ($stmt->execute()) {
            $result_sp = $stmt->get_result();
            $row_sp = $result_sp->fetch_assoc();
            $new_total = $row_sp['new_total_orden'];
            $message = "<p class='success'>Producto eliminado de la orden #{$orden_id}. Nuevo total: $" . number_format($new_total, 2) . "</p>";
            while($conn->more_results() && $conn->next_result()){}
        } else {
            throw new Exception("Error al ejecutar sp_eliminar_producto_de_orden: " . $stmt->error);
        }
    } catch (Exception $e) {
        $message = "<p class='error'>Error: " . $e->getMessage() . "</p>";
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}


// --- Obtener datos para los selects en el formulario de creación de orden ---
$clientes_result = $conn->query("
    SELECT c.cliente_id, dp.nombre, dp.apellido_paterno
    FROM clientes c
    JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
    ORDER BY dp.nombre
");
$clientes = [];
while ($row = $clientes_result->fetch_assoc()) { $clientes[] = $row; }



$sucursales_result = $conn->query("SELECT sucursal_id, nombre FROM sucursales ORDER BY nombre");
$sucursales = [];
while ($row = $sucursales_result->fetch_assoc()) { $sucursales[] = $row; }

$direcciones_result = $conn->query("SELECT direccion_id, calle, numero_exterior, ciudad FROM direcciones ORDER BY calle");
$direcciones = [];
while ($row = $direcciones_result->fetch_assoc()) { $direcciones[] = $row; }

$productos_result = $conn->query("SELECT producto_id, nombre, precio, stock FROM productos ORDER BY nombre");
$productos_disponibles = [];
while ($row = $productos_result->fetch_assoc()) { $productos_disponibles[] = $row; }

// --- Obtener datos para el formulario de edición de estado de orden ---
$edit_orden_estado = null;
if (isset($_GET['edit_estado_orden'])) {
    $orden_id = $_GET['edit_estado_orden'];
    $stmt = $conn->prepare("SELECT orden_id, estado, total FROM ordenes_compra WHERE orden_id = ?");
    $stmt->bind_param("i", $orden_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_orden_estado = $result->fetch_assoc();
    $stmt->close();
}


// --- Leer Órdenes de Compra (con información adicional) ---

$sql_ordenes = "SELECT
    oc.orden_id,
    oc.fecha_orden,
    oc.estado,
    oc.total,
    dp.nombre AS cliente_nombre,
    dp.apellido_paterno AS cliente_apellido,
    dp_emp.nombre AS empleado_nombre,
    s.nombre AS sucursal_nombre,
    d.calle AS direccion_calle,
    d.numero_exterior AS direccion_numero
FROM ordenes_compra oc
JOIN clientes c ON oc.cliente_id = c.cliente_id
JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
LEFT JOIN empleados e ON oc.empleado_id = e.empleado_id
LEFT JOIN datos_personas dp_emp ON e.datos_personas_id = dp_emp.datos_personas_id
LEFT JOIN sucursales s ON oc.sucursal_id = s.sucursal_id
LEFT JOIN direcciones d ON oc.direccion_envio_id = d.direccion_id
ORDER BY oc.fecha_orden DESC";
$ordenes_result = $conn->query($sql_ordenes);
if (!$ordenes_result) {
    die("<p class='error'>Error en la consulta de órdenes: " . $conn->error . "</p>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Órdenes de Compra</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Órdenes de Compra</h1>
        <p><a href="index.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2>Crear Nueva Orden de Compra</h2>
        <form action="ordenes_compra.php" method="POST">
            <label for="cliente_id">Cliente:</label>
            <select id="cliente_id" name="cliente_id" required>
                <option value="">Selecciona un cliente</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?php echo $cliente['cliente_id']; ?>">
                        <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido_paterno']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="empleado_id">Empleado (Opcional):</label>
            <select id="empleado_id" name="empleado_id">
                <option value="">-- Ninguno --</option>
                <?php foreach ($empleados as $empleado): ?>
                    <option value="<?php echo $empleado['empleado_id']; ?>">
                        <?php echo htmlspecialchars($empleado['nombre_completo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="sucursal_id">Sucursal (Opcional):</label>
            <select id="sucursal_id" name="sucursal_id">
                <option value="">-- Ninguna --</option>
                <?php foreach ($sucursales as $sucursal): ?>
                    <option value="<?php echo $sucursal['sucursal_id']; ?>">
                        <?php echo htmlspecialchars($sucursal['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="direccion_envio_id">Dirección de Envío (Opcional):</label>
            <select id="direccion_envio_id" name="direccion_envio_id">
                <option value="">-- Ninguna --</option>
                <?php foreach ($direcciones as $direccion): ?>
                    <option value="<?php echo $direccion['direccion_id']; ?>">
                        <?php echo htmlspecialchars($direccion['calle'] . ' ' . $direccion['numero_exterior'] . ', ' . $direccion['ciudad']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p><small>Asegúrate de que Clientes, Empleados, Sucursales y Direcciones existen antes de crear una orden.</small></p>

            <fieldset>
                <legend>Productos de la Orden</legend>
                <div id="productos_container">
                    <div class="producto_item">
                        <label for="producto_0">Producto:</label>
                        <select name="productos[0][producto_id]" class="select_producto" required>
                            <option value="">Selecciona un producto</option>
                            <?php foreach ($productos_disponibles as $prod): ?>
                                <option value="<?php echo $prod['producto_id']; ?>" data-precio="<?php echo $prod['precio']; ?>" data-stock="<?php echo $prod['stock']; ?>">
                                    <?php echo htmlspecialchars($prod['nombre']) . ' (Precio: $' . number_format($prod['precio'], 2) . ', Stock: ' . $prod['stock'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="cantidad_0">Cantidad:</label>
                        <input type="number" name="productos[0][cantidad]" value="1" min="1" class="input_cantidad" required>
                        <button type="button" class="remove_producto">Remover</button>
                    </div>
                </div>
                <button type="button" id="add_producto_btn">Agregar Otro Producto</button>
            </fieldset>

            <button type="submit" name="add_orden">Crear Orden</button>
        </form>

        <?php if ($edit_orden_estado): ?>
            <h2>Actualizar Estado de Orden #<?php echo $edit_orden_estado['orden_id']; ?> (Total: $<?php echo number_format($edit_orden_estado['total'], 2); ?>)</h2>
            <form action="ordenes_compra.php" method="POST">
                <input type="hidden" name="orden_id" value="<?php echo $edit_orden_estado['orden_id']; ?>">
                <label for="estado">Estado:</label>
                <select id="estado" name="estado" required>
                    <option value="pendiente" <?php echo ($edit_orden_estado['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="procesando" <?php echo ($edit_orden_estado['estado'] == 'procesando') ? 'selected' : ''; ?>>Procesando</option>
                    <option value="enviado" <?php echo ($edit_orden_estado['estado'] == 'enviado') ? 'selected' : ''; ?>>Enviado</option>
                    <option value="completada" <?php echo ($edit_orden_estado['estado'] == 'completada') ? 'selected' : ''; ?>>Completada</option>
                    <option value="cancelada" <?php echo ($edit_orden_estado['estado'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                </select>
                <p><small>Si el estado es 'Completada', se registrará un pago. Puedes añadir el método de pago y la referencia.</small></p>
                <label for="metodo_pago">Método de Pago (para 'Completada'):</label>
                <select id="metodo_pago" name="metodo_pago">
                    <option value="">-- Seleccionar --</option>
                    <option value="tarjeta_credito">Tarjeta de Crédito</option>
                    <option value="tarjeta_debito">Tarjeta de Débito</option>
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="paypal">PayPal</option>
                    <option value="otro">Otro</option>
                </select>
                <label for="referencia_pago">Referencia de Pago:</label>
                <input type="text" id="referencia_pago" name="referencia_pago" value="">
                <button type="submit" name="update_estado_orden">Actualizar Estado</button>
            </form>
            <hr>
        <?php endif; ?>

        <h2>Listado de Órdenes de Compra</h2>
        <?php if ($ordenes_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Empleado</th>
                        <th>Sucursal</th>
                        <th>Dirección Envío</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($orden = $ordenes_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $orden['orden_id']; ?></td>
                            <td><?php echo htmlspecialchars($orden['fecha_orden']); ?></td>
                            <td><?php echo htmlspecialchars($orden['cliente_nombre'] . ' ' . $orden['cliente_apellido']); ?></td>
                            <td><?php echo htmlspecialchars($orden['empleado_nombre'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($orden['sucursal_nombre'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($orden['direccion_calle'] ? $orden['direccion_calle'] . ' ' . $orden['direccion_numero'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($orden['estado']); ?></td>
                            <td>$<?php echo number_format($orden['total'], 2); ?></td>
                            <td>
                                <a href="ordenes_compra.php?view_details=<?php echo $orden['orden_id']; ?>">Ver Detalles</a> |
                                <a href="ordenes_compra.php?edit_estado_orden=<?php echo $orden['orden_id']; ?>">Editar Estado</a> |
                                <a href="ordenes_compra.php?delete_orden=<?php echo $orden['orden_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar esta orden y todos sus detalles/pagos?');">Eliminar</a>
                            </td>
                        </tr>
                        <?php
                        // Mostrar detalles de la orden si se solicitan
                        if (isset($_GET['view_details']) && $_GET['view_details'] == $orden['orden_id']) {
                            $orden_id_detalle = $orden['orden_id'];
                            $sql_detalles = "SELECT od.*, p.nombre AS producto_nombre, p.sku, p.stock AS producto_stock_actual
                                            FROM ordenes_detalle od
                                            JOIN productos p ON od.producto_id = p.producto_id
                                            WHERE od.orden_id = ?";
                            $stmt_detalles = $conn->prepare($sql_detalles);
                            $stmt_detalles->bind_param("i", $orden_id_detalle);
                            $stmt_detalles->execute();
                            $detalles_result = $stmt_detalles->get_result();
                        ?>
                            <tr class="details_row">
                                <td colspan="9">
                                    <h3>Detalles de la Orden #<?php echo $orden_id_detalle; ?></h3>
                                    <?php if ($detalles_result->num_rows > 0): ?>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>SKU</th>
                                                    <th>Cantidad</th>
                                                    <th>Precio Unitario</th>
                                                    <th>Subtotal</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($detalle = $detalles_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                                                        <td><?php echo htmlspecialchars($detalle['sku']); ?></td>
                                                        <td><?php echo $detalle['cantidad']; ?></td>
                                                        <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                                        <td>$<?php echo number_format($detalle['cantidad'] * $detalle['precio_unitario'], 2); ?></td>
                                                        <td>
                                                            <a href="ordenes_compra.php?delete_detalle_orden=<?php echo $orden_id_detalle . '_' . $detalle['producto_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este producto de la orden?');">Eliminar Producto</a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                        <h4>Agregar Producto a esta Orden</h4>
                                        <form action="ordenes_compra.php" method="POST">
                                            <input type="hidden" name="orden_id_detalle" value="<?php echo $orden_id_detalle; ?>">
                                            <label for="producto_id_detalle">Producto:</label>
                                            <select name="producto_id_detalle" id="producto_id_detalle" required>
                                                <option value="">Selecciona un producto</option>
                                                <?php foreach ($productos_disponibles as $prod): ?>
                                                    <option value="<?php echo $prod['producto_id']; ?>">
                                                        <?php echo htmlspecialchars($prod['nombre']) . ' (Stock: ' . $prod['stock'] . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="cantidad_detalle">Cantidad:</label>
                                            <input type="number" name="cantidad_detalle" value="1" min="1" required>
                                            <button type="submit" name="add_producto_to_orden">Agregar Producto a Orden</button>
                                        </form>

                                    <?php else: ?>
                                        <p>Esta orden no tiene productos asociados.</p>
                                        <h4>Agregar Producto a esta Orden</h4>
                                        <form action="ordenes_compra.php" method="POST">
                                            <input type="hidden" name="orden_id_detalle" value="<?php echo $orden_id_detalle; ?>">
                                            <label for="producto_id_detalle">Producto:</label>
                                            <select name="producto_id_detalle" id="producto_id_detalle" required>
                                                <option value="">Selecciona un producto</option>
                                                <?php foreach ($productos_disponibles as $prod): ?>
                                                    <option value="<?php echo $prod['producto_id']; ?>">
                                                        <?php echo htmlspecialchars($prod['nombre']) . ' (Stock: ' . $prod['stock'] . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="cantidad_detalle">Cantidad:</label>
                                            <input type="number" name="cantidad_detalle" value="1" min="1" required>
                                            <button type="submit" name="add_producto_to_orden">Agregar Producto a Orden</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php
                            $stmt_detalles->close();
                        }
                        ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay órdenes de compra registradas.</p>
        <?php endif; ?>
    </div>

    <script>
        let productoIndex = 1; // Para generar IDs únicos para los campos de productos

        document.getElementById('add_producto_btn').addEventListener('click', function() {
            const container = document.getElementById('productos_container');
            const newDiv = document.createElement('div');
            newDiv.classList.add('producto_item');
            newDiv.innerHTML = `
                <label for="producto_${productoIndex}">Producto:</label>
                <select name="productos[${productoIndex}][producto_id]" class="select_producto" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($productos_disponibles as $prod): ?>
                        <option value="<?php echo $prod['producto_id']; ?>" data-precio="<?php echo $prod['precio']; ?>" data-stock="<?php echo $prod['stock']; ?>">
                            <?php echo htmlspecialchars($prod['nombre']) . ' (Precio: $' . number_format($prod['precio'], 2) . ', Stock: ' . $prod['stock'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="cantidad_${productoIndex}">Cantidad:</label>
                <input type="number" name="productos[${productoIndex}][cantidad]" value="1" min="1" class="input_cantidad" required>
                <button type="button" class="remove_producto">Remover</button>
            `;
            container.appendChild(newDiv);
            productoIndex++;
        });

        document.getElementById('productos_container').addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove_producto')) {
                e.target.closest('.producto_item').remove();
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>