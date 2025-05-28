<?php
// pagos.php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

// --- Lógica para C, U, D ---

// Crear Pago (manual, para casos excepcionales o pagos iniciales de órdenes pendientes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_pago'])) {
        $orden_id = $_POST['orden_id'];
        $metodo_pago = $_POST['metodo_pago'];
        $monto = $_POST['monto'];
        $estado = $_POST['estado'];
        $referencia_pago = $_POST['referencia_pago'];

        $stmt = $conn->prepare("INSERT INTO pagos (orden_id, metodo_pago, monto, estado, referencia_pago) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $orden_id, $metodo_pago, $monto, $estado, $referencia_pago);
        if ($stmt->execute()) {
            $message = "<p class='success'>Pago agregado exitosamente.</p>";
            header("Location: pagos.php");
            exit();
        } else {
            $message = "<p class='error'>Error al agregar pago: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } elseif (isset($_POST['update_pago'])) {
    $pago_id = $_POST['pago_id'];
    $orden_id = $_POST['orden_id'];
    $metodo_pago = $_POST['metodo_pago'];
    $monto = $_POST['monto'];
    $estado = $_POST['estado'];
    $referencia_pago = $_POST['referencia_pago'];

    // PREPARA LA SENTENCIA ANTES DEL BIND
    $stmt = $conn->prepare("UPDATE pagos SET orden_id = ?, metodo_pago = ?, monto = ?, estado = ?, referencia_pago = ? WHERE pago_id = ?");
    $stmt->bind_param("isdssi", $orden_id, $metodo_pago, $monto, $estado, $referencia_pago, $pago_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Pago actualizado exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al actualizar pago: " . $stmt->error . "</p>";
    }
    $stmt->close();
}
}

// Eliminar Pago
if (isset($_GET['delete_pago'])) {
    $pago_id = $_GET['delete_pago'];
    $stmt = $conn->prepare("DELETE FROM pagos WHERE pago_id = ?");
    $stmt->bind_param("i", $pago_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Pago eliminado exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar pago: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// --- Obtener datos para el formulario de edición ---
$edit_pago = null;
if (isset($_GET['edit_pago'])) {
    $pago_id = $_GET['edit_pago'];
    $stmt = $conn->prepare("SELECT * FROM pagos WHERE pago_id = ?");
    $stmt->bind_param("i", $pago_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_pago = $result->fetch_assoc();
    $stmt->close();
}

// --- Obtener órdenes para el select (solo órdenes con el total conocido) ---
$ordenes_result = $conn->query("SELECT orden_id, total, fecha_orden FROM ordenes_compra ORDER BY fecha_orden DESC");
$ordenes = [];
while ($row = $ordenes_result->fetch_assoc()) { $ordenes[] = $row; }


// --- Leer Pagos (con información de la orden) ---
$sql_pagos = "SELECT
                p.*,
                oc.fecha_orden,
                oc.cliente_id,
                dp.nombre AS cliente_nombre,
                dp.apellido_paterno AS cliente_apellido
            FROM pagos p
            JOIN ordenes_compra oc ON p.orden_id = oc.orden_id
            JOIN clientes cl ON oc.cliente_id = cl.cliente_id
            JOIN datos_personas dp ON cl.datos_personas_id = dp.datos_personas_id
            ORDER BY p.fecha_pago DESC";
$result = $conn->query($sql_pagos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Pagos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Pagos</h1>
        <p><a href="dashboard_admin.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_pago ? 'Editar Pago' : 'Registrar Nuevo Pago (Manual)'; ?></h2>
        <form action="pagos.php" method="POST">
            <?php if ($edit_pago): ?>
                <input type="hidden" name="pago_id" value="<?php echo $edit_pago['pago_id']; ?>">
            <?php endif; ?>

            <label for="orden_id">Orden de Compra:</label>
            <select id="orden_id" name="orden_id" required>
                <option value="">Selecciona una orden</option>
                <?php foreach ($ordenes as $orden): ?>
                    <option value="<?php echo $orden['orden_id']; ?>"
                        <?php echo ($edit_pago && $edit_pago['orden_id'] == $orden['orden_id']) ? 'selected' : ''; ?>>
                        Orden #<?php echo $orden['orden_id']; ?> (Total: $<?php echo number_format($orden['total'], 2); ?> - <?php echo htmlspecialchars($orden['fecha_orden']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p><small>La mayoría de los pagos se registran al completar una orden desde <a href="ordenes_compra.php">Administrar Órdenes</a>.</small></p>

            <label for="metodo_pago">Método de Pago:</label>
            <select id="metodo_pago" name="metodo_pago" required>
                <option value="tarjeta_credito" <?php echo ($edit_pago && $edit_pago['metodo_pago'] == 'tarjeta_credito') ? 'selected' : ''; ?>>Tarjeta de Crédito</option>
                <option value="tarjeta_debito" <?php echo ($edit_pago && $edit_pago['metodo_pago'] == 'tarjeta_debito') ? 'selected' : ''; ?>>Tarjeta de Débito</option>
                <option value="efectivo" <?php echo ($edit_pago && $edit_pago['metodo_pago'] == 'efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                <option value="transferencia" <?php echo ($edit_pago && $edit_pago['metodo_pago'] == 'transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                <option value="paypal" <?php echo ($edit_pago && $edit_pago['metodo_pago'] == 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                <option value="otro" <?php echo ($edit_pago && $edit_pago['metodo_pago'] == 'otro') ? 'selected' : ''; ?>>Otro</option>
            </select>

            <label for="monto">Monto:</label>
            <input type="number" id="monto" name="monto" step="0.01" min="0" value="<?php echo $edit_pago ? htmlspecialchars($edit_pago['monto']) : ''; ?>" required>

            <label for="estado">Estado del Pago:</label>
            <select id="estado" name="estado" required>
                <option value="pendiente" <?php echo ($edit_pago && $edit_pago['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="completado" <?php echo ($edit_pago && $edit_pago['estado'] == 'completado') ? 'selected' : ''; ?>>Completado</option>
                <option value="fallido" <?php echo ($edit_pago && $edit_pago['estado'] == 'fallido') ? 'selected' : ''; ?>>Fallido</option>
                <option value="reembolsado" <?php echo ($edit_pago && $edit_pago['estado'] == 'reembolsado') ? 'selected' : ''; ?>>Reembolsado</option>
            </select>

            <label for="referencia_pago">Referencia de Pago (ID de transacción, etc.):</label>
            <input type="text" id="referencia_pago" name="referencia_pago" value="<?php echo $edit_pago ? htmlspecialchars($edit_pago['referencia_pago']) : ''; ?>">

            <button type="submit" name="<?php echo $edit_pago ? 'update_pago' : 'add_pago'; ?>">
                <?php echo $edit_pago ? 'Actualizar Pago' : 'Registrar Pago'; ?>
            </button>
        </form>

        <h2>Listado de Pagos</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Pago</th>
                        <th>ID Orden</th>
                        <th>Cliente</th>
                        <th>Método</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Referencia</th>
                        <th>Fecha Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['pago_id']; ?></td>
                            <td><a href="ordenes_compra.php?view_details=<?php echo $row['orden_id']; ?>">#<?php echo $row['orden_id']; ?></a></td>
                            <td><?php echo htmlspecialchars($row['cliente_nombre'] . ' ' . $row['cliente_apellido']); ?></td>
                            <td><?php echo htmlspecialchars($row['metodo_pago']); ?></td>
                            <td>$<?php echo number_format($row['monto'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['estado']); ?></td>
                            <td><?php echo htmlspecialchars($row['referencia_pago']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_pago']); ?></td>
                            <td>
                                <a href="pagos.php?edit_pago=<?php echo $row['pago_id']; ?>">Editar</a> |
                                <a href="pagos.php?delete_pago=<?php echo $row['pago_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este pago?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay pagos registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>