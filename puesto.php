<?php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_puesto'])) {
        $nombre_puesto = $_POST['nombre_puesto'];
        $descripcion = $_POST['descripcion'];

        $stmt = $conn->prepare("INSERT INTO puesto (nombre_puesto, descripcion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre_puesto, $descripcion);
        if ($stmt->execute()) {
            $message = "<p class='success'>Puesto agregado exitosamente.</p>";
            header("Location: puesto.php");
            exit();
        } else {
            if ($conn->errno == 1062) { 
                $message = "<p class='error'>Error: El nombre del puesto ya existe.</p>";
            } else {
                $message = "<p class='error'>Error al agregar puesto: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    } elseif (isset($_POST['update_puesto'])) {
        $puesto_id = $_POST['puesto_id'];
        $nombre_puesto = $_POST['nombre_puesto'];
        $descripcion = $_POST['descripcion'];

        $stmt = $conn->prepare("UPDATE puesto SET nombre_puesto = ?, descripcion = ? WHERE puesto_id = ?");
        $stmt->bind_param("ssi", $nombre_puesto, $descripcion, $puesto_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Puesto actualizado exitosamente.</p>";
        } else {
            if ($conn->errno == 1062) {
                $message = "<p class='error'>Error: El nombre del puesto ya existe.</p>";
            } else {
                $message = "<p class='error'>Error al actualizar puesto: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_puesto'])) {
    $puesto_id = $_GET['delete_puesto'];
    $stmt = $conn->prepare("DELETE FROM puesto WHERE puesto_id = ?");
    $stmt->bind_param("i", $puesto_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Puesto eliminado exitosamente.</p>";
    } else {
        if ($conn->errno == 1451) {
            $message = "<p class='error'>Error al eliminar puesto: Hay empleados asignados a este puesto. Primero reasigne o elimine a los empleados.</p>";
        } else {
            $message = "<p class='error'>Error al eliminar puesto: " . $stmt->error . "</p>";
        }
    }
    $stmt->close();
}

$edit_puesto = null;
if (isset($_GET['edit_puesto'])) {
    $puesto_id = $_GET['edit_puesto'];
    $stmt = $conn->prepare("SELECT * FROM puesto WHERE puesto_id = ?");
    $stmt->bind_param("i", $puesto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_puesto = $result->fetch_assoc();
    $stmt->close();
}

$sql = "SELECT * FROM puesto ORDER BY puesto_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Puestos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Puestos</h1>
        <p><a href="dashboard_admin.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_puesto ? 'Editar Puesto' : 'Agregar Nuevo Puesto'; ?></h2>
        <form action="puesto.php" method="POST">
            <?php if ($edit_puesto): ?>
                <input type="hidden" name="puesto_id" value="<?php echo $edit_puesto['puesto_id']; ?>">
            <?php endif; ?>

            <label for="nombre_puesto">Nombre del Puesto:</label>
            <input type="text" id="nombre_puesto" name="nombre_puesto" value="<?php echo $edit_puesto ? htmlspecialchars($edit_puesto['nombre_puesto']) : ''; ?>" required>

            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion" rows="4" required><?php echo $edit_puesto ? htmlspecialchars($edit_puesto['descripcion']) : ''; ?></textarea>

            <button type="submit" name="<?php echo $edit_puesto ? 'update_puesto' : 'add_puesto'; ?>">
                <?php echo $edit_puesto ? 'Actualizar Puesto' : 'Agregar Puesto'; ?>
            </button>
        </form>

        <h2>Listado de Puestos</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Puesto</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['puesto_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_puesto']); ?></td>
                            <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                            <td>
                                <a href="puesto.php?edit_puesto=<?php echo $row['puesto_id']; ?>">Editar</a> |
                                <a href="puesto.php?delete_puesto=<?php echo $row['puesto_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este puesto? No se podrá eliminar si hay empleados asignados.');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay puestos registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>