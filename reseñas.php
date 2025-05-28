<?php
// reseñas.php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

// --- Lógica para C, U, D ---

// Crear/Actualizar Reseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_reseña'])) {
        $cliente_id = $_POST['cliente_id'];
        $producto_id = $_POST['producto_id'];
        $calificacion = $_POST['calificacion'];
        $comentario = $_POST['comentario'];

        $stmt = $conn->prepare("INSERT INTO reseñas (cliente_id, producto_id, calificacion, comentario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $cliente_id, $producto_id, $calificacion, $comentario);
        if ($stmt->execute()) {
            $message = "<p class='success'>Reseña agregada exitosamente.</p>";
            header("Location: reseñas.php");
            exit();
        } else {
            $message = "<p class='error'>Error al agregar reseña: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } elseif (isset($_POST['update_reseña'])) {
        $reseña_id = $_POST['reseña_id'];
        $cliente_id = $_POST['cliente_id'];
        $producto_id = $_POST['producto_id'];
        $calificacion = $_POST['calificacion'];
        $comentario = $_POST['comentario'];

        $stmt = $conn->prepare("UPDATE reseñas SET cliente_id = ?, producto_id = ?, calificacion = ?, comentario = ? WHERE reseña_id = ?");
        $stmt->bind_param("iissi", $cliente_id, $producto_id, $calificacion, $comentario, $reseña_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Reseña actualizada exitosamente.</p>";
        } else {
            $message = "<p class='error'>Error al actualizar reseña: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// Eliminar Reseña
if (isset($_GET['delete_reseña'])) {
    $reseña_id = $_GET['delete_reseña'];
    $stmt = $conn->prepare("DELETE FROM reseñas WHERE reseña_id = ?");
    $stmt->bind_param("i", $reseña_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Reseña eliminada exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar reseña: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// --- Obtener datos para el formulario de edición ---
$edit_reseña = null;
if (isset($_GET['edit_reseña'])) {
    $reseña_id = $_GET['edit_reseña'];
    $stmt = $conn->prepare("SELECT * FROM reseñas WHERE reseña_id = ?");
    $stmt->bind_param("i", $reseña_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_reseña = $result->fetch_assoc();
    $stmt->close();
}

// --- Obtener clientes para el select ---
$clientes_result = $conn->query("
    SELECT c.cliente_id, dp.nombre, dp.apellido_paterno, dp.apellido_materno
    FROM clientes c
    JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
    ORDER BY dp.nombre
");
$clientes = [];
if ($clientes_result && $clientes_result->num_rows > 0) {
    while ($row = $clientes_result->fetch_assoc()) {
        $clientes[] = $row;
    }
}

// --- Obtener productos para el select ---
$productos_result = $conn->query("SELECT producto_id, nombre, precio FROM productos ORDER BY nombre");
$productos = [];
if ($productos_result->num_rows > 0) {
    while ($row = $productos_result->fetch_assoc()) {
        $productos[] = $row;
    }
}

// --- Leer Reseñas (con nombres de cliente y producto) ---
$sql = "SELECT
            r.reseña_id, r.calificacion, r.comentario, r.fecha_reseña,
            dp.nombre AS nombre_cliente, dp.apellido_paterno AS apellido_cliente,
            p.nombre AS nombre_producto
        FROM reseñas r
        JOIN clientes c ON r.cliente_id = c.cliente_id
        JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
        JOIN productos p ON r.producto_id = p.producto_id
        ORDER BY r.fecha_reseña DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Reseñas</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Reseñas</h1>
        <p><a href="dashboard_admin.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_reseña ? 'Editar Reseña' : 'Agregar Nueva Reseña'; ?></h2>
        <form action="reseñas.php" method="POST">
            <?php if ($edit_reseña): ?>
                <input type="hidden" name="reseña_id" value="<?php echo $edit_reseña['reseña_id']; ?>">
            <?php endif; ?>

            <label for="cliente_id">Cliente:</label>
            <select id="cliente_id" name="cliente_id" required>
                <option value="">Selecciona un cliente</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?php echo $cliente['cliente_id']; ?>"
                        <?php echo ($edit_reseña && $edit_reseña['cliente_id'] == $cliente['cliente_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido_paterno']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p><small>Si el cliente no aparece, <a href="clientes.php" target="_blank">créalo aquí</a> primero.</small></p>

            <label for="producto_id">Producto:</label>
            <select id="producto_id" name="producto_id" required>
                <option value="">Selecciona un producto</option>
                <?php foreach ($productos as $producto): ?>
                    <option value="<?php echo $producto['producto_id']; ?>"
                        <?php echo ($edit_reseña && $edit_reseña['producto_id'] == $producto['producto_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($producto['nombre'] . ' ($' . number_format($producto['precio'], 2) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p><small>Si el producto no aparece, <a href="productos.php" target="_blank">créalo aquí</a> primero.</small></p>

            <label for="calificacion">Calificación (1-5):</label>
            <input type="number" id="calificacion" name="calificacion" min="1" max="5" value="<?php echo $edit_reseña ? htmlspecialchars($edit_reseña['calificacion']) : '5'; ?>" required>

            <label for="comentario">Comentario:</label>
            <textarea id="comentario" name="comentario" rows="4"><?php echo $edit_reseña ? htmlspecialchars($edit_reseña['comentario']) : ''; ?></textarea>

            <button type="submit" name="<?php echo $edit_reseña ? 'update_reseña' : 'add_reseña'; ?>">
                <?php echo $edit_reseña ? 'Actualizar Reseña' : 'Agregar Reseña'; ?>
            </button>
        </form>

        <h2>Listado de Reseñas</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Calificación</th>
                        <th>Comentario</th>
                        <th>Fecha Reseña</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['reseña_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_cliente'] . ' ' . $row['apellido_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_producto']); ?></td>
                            <td><?php echo $row['calificacion']; ?> estrellas</td>
                            <td><?php echo htmlspecialchars($row['comentario']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_reseña']); ?></td>
                            <td>
                                <a href="reseñas.php?edit_reseña=<?php echo $row['reseña_id']; ?>">Editar</a> |
                                <a href="reseñas.php?delete_reseña=<?php echo $row['reseña_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar esta reseña?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay reseñas registradas.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>