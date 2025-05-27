<?php
// productos.php
require_once 'db.php';

$conn = connectDB();

$message = '';

// --- Lógica para C, U, D ---

// Crear/Actualizar Producto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_producto'])) {
        $proveedor_id = $_POST['proveedor_id'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];
        $stock = $_POST['stock'];
        $sku = $_POST['sku'];
        $categorias_seleccionadas = isset($_POST['categorias']) ? $_POST['categorias'] : [];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO productos (proveedor_id, nombre, descripcion, precio, stock, sku) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssis", $proveedor_id, $nombre, $descripcion, $precio, $stock, $sku);
            if (!$stmt->execute()) {
                throw new Exception("Error al agregar producto: " . $stmt->error);
            }
            $producto_id = $stmt->insert_id;
            $stmt->close();

            // Insertar relaciones con categorías
            if (!empty($categorias_seleccionadas)) {
                $stmt_cat = $conn->prepare("INSERT INTO productos_categorias (producto_id, categoria_id) VALUES (?, ?)");
                foreach ($categorias_seleccionadas as $categoria_id) {
                    $stmt_cat->bind_param("ii", $producto_id, $categoria_id);
                    if (!$stmt_cat->execute()) {
                        throw new Exception("Error al asociar categoría: " . $stmt_cat->error);
                    }
                }
                $stmt_cat->close();
            }

            $conn->commit();
            $message = "<p class='success'>Producto agregado exitosamente.</p>";
        } catch (Exception $e) {
            $conn->rollback();
            if ($conn->errno == 1062) { // Error de SKU duplicado
                $message = "<p class='error'>Error al agregar producto: El SKU ya existe.</p>";
            } else {
                $message = "<p class='error'>" . $e->getMessage() . "</p>";
            }
        }
    } elseif (isset($_POST['update_producto'])) {
        $producto_id = $_POST['producto_id'];
        $proveedor_id = $_POST['proveedor_id'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];
        $stock = $_POST['stock'];
        $sku = $_POST['sku'];
        $categorias_seleccionadas = isset($_POST['categorias']) ? $_POST['categorias'] : [];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE productos SET proveedor_id = ?, nombre = ?, descripcion = ?, precio = ?, stock = ?, sku = ? WHERE producto_id = ?");
            $stmt->bind_param("isssisi", $proveedor_id, $nombre, $descripcion, $precio, $stock, $sku, $producto_id);
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar producto: " . $stmt->error);
            }
            $stmt->close();

            // Actualizar relaciones con categorías
            // 1. Eliminar relaciones existentes para este producto
            $stmt_delete_cat = $conn->prepare("DELETE FROM productos_categorias WHERE producto_id = ?");
            $stmt_delete_cat->bind_param("i", $producto_id);
            if (!$stmt_delete_cat->execute()) {
                throw new Exception("Error al eliminar categorías antiguas: " . $stmt_delete_cat->error);
            }
            $stmt_delete_cat->close();

            // 2. Insertar nuevas relaciones
            if (!empty($categorias_seleccionadas)) {
                $stmt_cat = $conn->prepare("INSERT INTO productos_categorias (producto_id, categoria_id) VALUES (?, ?)");
                foreach ($categorias_seleccionadas as $categoria_id) {
                    $stmt_cat->bind_param("ii", $producto_id, $categoria_id);
                    if (!$stmt_cat->execute()) {
                        throw new Exception("Error al asociar nueva categoría: " . $stmt_cat->error);
                    }
                }
                $stmt_cat->close();
            }

            $conn->commit();
            $message = "<p class='success'>Producto actualizado exitosamente.</p>";
        } catch (Exception $e) {
            $conn->rollback();
            if ($conn->errno == 1062) { // Error de SKU duplicado
                $message = "<p class='error'>Error al actualizar producto: El SKU ya existe.</p>";
            } else {
                $message = "<p class='error'>" . $e->getMessage() . "</p>";
            }
        }
    }
}

// Eliminar Producto
if (isset($_GET['delete_producto'])) {
    $producto_id = $_GET['delete_producto'];
    $stmt = $conn->prepare("DELETE FROM productos WHERE producto_id = ?");
    $stmt->bind_param("i", $producto_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Producto eliminado exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar producto: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// --- Obtener datos para el formulario de edición ---
$edit_producto = null;
$producto_categorias_existentes = [];
if (isset($_GET['edit_producto'])) {
    $producto_id = $_GET['edit_producto'];
    $stmt = $conn->prepare("SELECT * FROM productos WHERE producto_id = ?");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_producto = $result->fetch_assoc();
    $stmt->close();

    // Obtener las categorías asociadas al producto para preseleccionar
    $stmt_cats = $conn->prepare("SELECT categoria_id FROM productos_categorias WHERE producto_id = ?");
    $stmt_cats->bind_param("i", $producto_id);
    $stmt_cats->execute();
    $result_cats = $stmt_cats->get_result();
    while($row_cat = $result_cats->fetch_assoc()){
        $producto_categorias_existentes[] = $row_cat['categoria_id'];
    }
    $stmt_cats->close();
}

// --- Obtener proveedores para el select ---
$proveedores_result = $conn->query("SELECT proveedor_id, nombre_empresa FROM proveedores ORDER BY nombre_empresa");
$proveedores = [];
if ($proveedores_result->num_rows > 0) {
    while ($row = $proveedores_result->fetch_assoc()) {
        $proveedores[] = $row;
    }
}

// --- Obtener todas las categorías para el checklist ---
$categorias_result = $conn->query("SELECT categoria_id, nombre FROM categorias ORDER BY nombre");
$categorias = [];
if ($categorias_result->num_rows > 0) {
    while ($row = $categorias_result->fetch_assoc()) {
        $categorias[] = $row;
    }
}


// --- Leer Productos (con proveedor y categorías) ---
$sql = "SELECT
            p.producto_id, p.nombre, p.descripcion, p.precio, p.stock, p.sku,
            prov.nombre_empresa AS nombre_proveedor,
            GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', ') AS categorias_asociadas
        FROM productos p
        JOIN proveedores prov ON p.proveedor_id = prov.proveedor_id
        LEFT JOIN productos_categorias pc ON p.producto_id = pc.producto_id
        LEFT JOIN categorias c ON pc.categoria_id = c.categoria_id
        GROUP BY p.producto_id
        ORDER BY p.producto_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Productos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Productos</h1>
        <p><a href="index.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_producto ? 'Editar Producto' : 'Agregar Nuevo Producto'; ?></h2>
        <form action="productos.php" method="POST">
            <?php if ($edit_producto): ?>
                <input type="hidden" name="producto_id" value="<?php echo $edit_producto['producto_id']; ?>">
            <?php endif; ?>

            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $edit_producto ? htmlspecialchars($edit_producto['nombre']) : ''; ?>" required>

            <label for="proveedor_id">Proveedor:</label>
            <select id="proveedor_id" name="proveedor_id" required>
                <option value="">Selecciona un proveedor</option>
                <?php foreach ($proveedores as $proveedor): ?>
                    <option value="<?php echo $proveedor['proveedor_id']; ?>"
                        <?php echo ($edit_producto && $edit_producto['proveedor_id'] == $proveedor['proveedor_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($proveedor['nombre_empresa']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p><small>Si el proveedor no aparece, <a href="proveedores.php" target="_blank">créalo aquí</a> primero.</small></p>


            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion" rows="4"><?php echo $edit_producto ? htmlspecialchars($edit_producto['descripcion']) : ''; ?></textarea>

            <label for="precio">Precio:</label>
            <input type="number" id="precio" name="precio" step="0.01" min="0" value="<?php echo $edit_producto ? htmlspecialchars($edit_producto['precio']) : ''; ?>" required>

            <label for="stock">Stock:</label>
            <input type="number" id="stock" name="stock" min="0" value="<?php echo $edit_producto ? htmlspecialchars($edit_producto['stock']) : '0'; ?>" required>

            <label for="sku">SKU (Stock Keeping Unit):</label>
            <input type="text" id="sku" name="sku" value="<?php echo $edit_producto ? htmlspecialchars($edit_producto['sku']) : ''; ?>">

            <fieldset>
                <legend>Categorías</legend>
                <?php if (!empty($categorias)): ?>
                    <?php foreach ($categorias as $categoria): ?>
                        <label>
                            <input type="checkbox" name="categorias[]" value="<?php echo $categoria['categoria_id']; ?>"
                                <?php echo in_array($categoria['categoria_id'], $producto_categorias_existentes) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($categoria['nombre']); ?>
                        </label><br>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay categorías disponibles. <a href="categorias.php" target="_blank">Crea una aquí</a>.</p>
                <?php endif; ?>
            </fieldset>

            <button type="submit" name="<?php echo $edit_producto ? 'update_producto' : 'add_producto'; ?>">
                <?php echo $edit_producto ? 'Actualizar Producto' : 'Agregar Producto'; ?>
            </button>
        </form>

        <h2>Listado de Productos</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Proveedor</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>SKU</th>
                        <th>Categorías</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['producto_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_proveedor']); ?></td>
                            <td>$<?php echo number_format($row['precio'], 2); ?></td>
                            <td><?php echo $row['stock']; ?></td>
                            <td><?php echo htmlspecialchars($row['sku']); ?></td>
                            <td><?php echo $row['categorias_asociadas'] ? htmlspecialchars($row['categorias_asociadas']) : 'Ninguna'; ?></td>
                            <td>
                                <a href="productos.php?edit_producto=<?php echo $row['producto_id']; ?>">Editar</a> |
                                <a href="productos.php?delete_producto=<?php echo $row['producto_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este producto?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay productos registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>