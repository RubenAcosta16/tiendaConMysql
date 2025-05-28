<?php

require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_categoria'])) {
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];

        $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $descripcion);
        if ($stmt->execute()) {
            $message = "<p class='success'>Categoría agregada exitosamente.</p>";
            header("Location: categorias.php");
            exit();
        } else {
            if ($conn->errno == 1062) { 
                $message = "<p class='error'>Error: El nombre de la categoría ya existe.</p>";
            } else {
                $message = "<p class='error'>Error al agregar categoría: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    } elseif (isset($_POST['update_categoria'])) {
        $categoria_id = $_POST['categoria_id'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];

        $stmt = $conn->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE categoria_id = ?");
        $stmt->bind_param("ssi", $nombre, $descripcion, $categoria_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Categoría actualizada exitosamente.</p>";
        } else {
            if ($conn->errno == 1062) {
                $message = "<p class='error'>Error: El nombre de la categoría ya existe.</p>";
            } else {
                $message = "<p class='error'>Error al actualizar categoría: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_categoria'])) {
    $categoria_id = $_GET['delete_categoria'];

    $check_products_stmt = $conn->prepare("SELECT COUNT(*) FROM productos_categorias WHERE categoria_id = ?");
    $check_products_stmt->bind_param("i", $categoria_id);
    $check_products_stmt->execute();
    $check_products_result = $check_products_stmt->get_result();
    $is_associated = $check_products_result->fetch_row()[0] > 0;
    $check_products_stmt->close();

    if ($is_associated) {
        $message = "<p class='error'>No se puede eliminar la categoría porque está asociada a uno o más productos. Desvincule los productos primero.</p>";
    } else {
        $stmt = $conn->prepare("DELETE FROM categorias WHERE categoria_id = ?");
        $stmt->bind_param("i", $categoria_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Categoría eliminada exitosamente.</p>";
        } else {
            $message = "<p class='error'>Error al eliminar categoría: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

$edit_categoria = null;
if (isset($_GET['edit_categoria'])) {
    $categoria_id = $_GET['edit_categoria'];
    $stmt = $conn->prepare("SELECT * FROM categorias WHERE categoria_id = ?");
    $stmt->bind_param("i", $categoria_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_categoria = $result->fetch_assoc();
    $stmt->close();
}

$sql = "SELECT * FROM categorias ORDER BY categoria_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Categorías</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Categorías</h1>
        <p><a href="dashboard_admin.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_categoria ? 'Editar Categoría' : 'Agregar Nueva Categoría'; ?></h2>
        <form action="categorias.php" method="POST">
            <?php if ($edit_categoria): ?>
                <input type="hidden" name="categoria_id" value="<?php echo $edit_categoria['categoria_id']; ?>">
            <?php endif; ?>

            <label for="nombre">Nombre de la Categoría:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $edit_categoria ? htmlspecialchars($edit_categoria['nombre']) : ''; ?>" required>

            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion" rows="4"><?php echo $edit_categoria ? htmlspecialchars($edit_categoria['descripcion']) : ''; ?></textarea>

            <button type="submit" name="<?php echo $edit_categoria ? 'update_categoria' : 'add_categoria'; ?>">
                <?php echo $edit_categoria ? 'Actualizar Categoría' : 'Agregar Categoría'; ?>
            </button>
        </form>

        <h2>Listado de Categorías</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['categoria_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                            <td>
                                <a href="categorias.php?edit_categoria=<?php echo $row['categoria_id']; ?>">Editar</a> |
                                <a href="categorias.php?delete_categoria=<?php echo $row['categoria_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar esta categoría? Solo se podrá eliminar si no está asociada a ningún producto.');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay categorías registradas.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>