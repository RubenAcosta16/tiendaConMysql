<?php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_sucursal'])) {
        $nombre = $_POST['nombre'];
        $telefono = $_POST['telefono'];
        $direccion_id = $_POST['direccion_id'];

        $stmt = $conn->prepare("INSERT INTO sucursales (nombre, telefono, direccion_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $telefono, $direccion_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Sucursal agregada exitosamente.</p>";
            header("Location: sucursales.php");
            exit();
        } else {
            $message = "<p class='error'>Error al agregar sucursal: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } elseif (isset($_POST['update_sucursal'])) {
        $sucursal_id = $_POST['sucursal_id'];
        $nombre = $_POST['nombre'];
        $telefono = $_POST['telefono'];
        $direccion_id = $_POST['direccion_id'];

        $stmt = $conn->prepare("UPDATE sucursales SET nombre = ?, telefono = ?, direccion_id = ? WHERE sucursal_id = ?");
        $stmt->bind_param("ssii", $nombre, $telefono, $direccion_id, $sucursal_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Sucursal actualizada exitosamente.</p>";
        } else {
            $message = "<p class='error'>Error al actualizar sucursal: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_sucursal'])) {
    $sucursal_id = $_GET['delete_sucursal'];
    $stmt = $conn->prepare("DELETE FROM sucursales WHERE sucursal_id = ?");
    $stmt->bind_param("i", $sucursal_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Sucursal eliminada exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar sucursal: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

$edit_sucursal = null;
if (isset($_GET['edit_sucursal'])) {
    $sucursal_id = $_GET['edit_sucursal'];
    $stmt = $conn->prepare("SELECT * FROM sucursales WHERE sucursal_id = ?");
    $stmt->bind_param("i", $sucursal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_sucursal = $result->fetch_assoc();
    $stmt->close();
}

$direcciones_result = $conn->query("SELECT direccion_id, calle, numero_exterior, colonia FROM direcciones");
$direcciones = [];
if ($direcciones_result->num_rows > 0) {
    while ($row = $direcciones_result->fetch_assoc()) {
        $direcciones[] = $row;
    }
}

$sql = "SELECT s.sucursal_id, s.nombre, s.telefono,
               d.calle, d.numero_exterior, d.numero_interior, d.colonia, d.ciudad, d.estado, d.codigo_postal, d.pais
        FROM sucursales s
        JOIN direcciones d ON s.direccion_id = d.direccion_id
        ORDER BY s.sucursal_id DESC"; 
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Sucursales</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Sucursales</h1>
        <p><a href="dashboard_admin.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_sucursal ? 'Editar Sucursal' : 'Agregar Nueva Sucursal'; ?></h2>
        <form action="sucursales.php" method="POST">
            <?php if ($edit_sucursal): ?>
                <input type="hidden" name="sucursal_id" value="<?php echo $edit_sucursal['sucursal_id']; ?>">
            <?php endif; ?>

            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $edit_sucursal ? htmlspecialchars($edit_sucursal['nombre']) : ''; ?>" required>

            <label for="telefono">Teléfono:</label>
            <input type="text" id="telefono" name="telefono" value="<?php echo $edit_sucursal ? htmlspecialchars($edit_sucursal['telefono']) : ''; ?>">

            <label for="direccion_id">Dirección:</label>
            <select id="direccion_id" name="direccion_id" required>
                <option value="">Selecciona una dirección</option>
                <?php foreach ($direcciones as $direccion): ?>
                    <option value="<?php echo $direccion['direccion_id']; ?>"
                        <?php echo ($edit_sucursal && $edit_sucursal['direccion_id'] == $direccion['direccion_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($direccion['calle'] . ' ' . $direccion['numero_exterior'] . ', ' . $direccion['colonia']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="<?php echo $edit_sucursal ? 'update_sucursal' : 'add_sucursal'; ?>">
                <?php echo $edit_sucursal ? 'Actualizar Sucursal' : 'Agregar Sucursal'; ?>
            </button>
        </form>

        <h2>Listado de Sucursales</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Dirección</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['sucursal_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['calle'] . ' ' . $row['numero_exterior'] .
                                    ($row['numero_interior'] ? ' Int. ' . $row['numero_interior'] : '') .
                                    ', ' . $row['colonia'] . ', ' . $row['ciudad'] . ', ' . $row['estado'] .
                                    ' C.P. ' . $row['codigo_postal'] . ', ' . $row['pais']); ?>
                            </td>
                            <td>
                                <a href="sucursales.php?edit_sucursal=<?php echo $row['sucursal_id']; ?>">Editar</a> |
                                <a href="sucursales.php?delete_sucursal=<?php echo $row['sucursal_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar esta sucursal?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay sucursales registradas.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>